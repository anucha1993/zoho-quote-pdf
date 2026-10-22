<?php

namespace App\Services;

use App\Pdf\ThaiLanguageToFont;
use App\Support\QuoteData;
use App\Support\QuoteLink;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * สร้างไฟล์ PDF ใบเสนอราคาจากข้อมูล Zoho และจัดการ cache ไฟล์
 *
 * cache key = quote:{id}:{Modified_Time}
 * → ถ้า Quote ไม่ถูกแก้ไข จะใช้ไฟล์เดิมโดยไม่ render ใหม่
 */
class QuotePdfService
{
    public function __construct(
        protected ZohoCrmService $zoho,
    ) {}

    /**
     * โหลดข้อมูล Quote (พร้อม related) และห่อเป็น QuoteData
     */
    public function load(string $id): QuoteData
    {
        $data = $this->zoho->getQuoteWithRelations($id);

        return new QuoteData(
            $data['quote'],
            $data['account'],
            $data['contact'],
            $data['owner'],
            $data['employee'] ?? null,
        );
    }

    /**
     * คืน path ของไฟล์ PDF — สร้างใหม่เฉพาะเมื่อยังไม่มีไฟล์ของเวอร์ชันนี้ (cache miss)
     */
    public function getPdfPath(QuoteData $quote): string
    {
        $path = $this->cachePath($quote);

        if (is_file($path) && filesize($path) > 0) {
            Log::channel('zoho')->debug('PDF cache hit', ['quote' => $quote->id(), 'path' => basename($path)]);

            return $path;
        }

        $binary = $this->render($quote);

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // เขียนลงไฟล์ชั่วคราวก่อนแล้วค่อย rename เพื่อกันไฟล์ครึ่ง ๆ กลาง ๆ เวลามี request พร้อมกัน
        $tmp = $path.'.'.uniqid('', true).'.tmp';
        file_put_contents($tmp, $binary);
        rename($tmp, $path);

        Log::channel('zoho')->info('PDF rendered', ['quote' => $quote->id(), 'bytes' => strlen($binary)]);

        return $path;
    }

    /**
     * อ่านเนื้อหา PDF (ใช้ cache ถ้ามี)
     */
    public function getPdfBinary(QuoteData $quote): string
    {
        return (string) file_get_contents($this->getPdfPath($quote));
    }

    /**
     * สร้าง PDF จาก Blade view ด้วย mPDF และคืนค่าเป็น binary string
     */
    public function render(QuoteData $quote): string
    {
        $html = View::make('pdf.quote', [
            'q' => $quote,
            'company' => config('quote.company'),
            'approver' => config('quote.approver'),
            'texts' => config('quote.texts'),
            'doc' => config('quote.document'),
            'formCode' => config('quote.form_code'),
            'trackingUrl' => config('quote.show_qr') ? QuoteLink::url($quote->id()) : null,
        ])->render();

        $mpdf = $this->makeMpdf();
        $mpdf->SetTitle($quote->number());
        $mpdf->SetAuthor(config('quote.company.name_th', ''));
        $mpdf->SetSubject($quote->serviceSubject());

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * cache key ตามข้อกำหนด: quote:{id}:{Modified_Time}
     */
    public function cacheKey(QuoteData $quote): string
    {
        return "quote:{$quote->id()}:{$quote->modifiedTime()}";
    }

    /**
     * path ไฟล์ใน storage/app/quotes/ — ใช้ hash ของ cache key เป็นชื่อไฟล์
     */
    public function cachePath(QuoteData $quote): string
    {
        $dir = storage_path('app/'.trim(config('quote.storage_dir', 'quotes'), '/'));

        return $dir.DIRECTORY_SEPARATOR.$quote->id().'_'.substr(sha1($this->cacheKey($quote)), 0, 16).'.pdf';
    }

    /**
     * ตั้งค่า mPDF: กระดาษ A4, margin ตาม Excel ต้นแบบ, ฟอนต์ไทย Angsana New / TH Sarabun New
     */
    protected function makeMpdf(): Mpdf
    {
        $doc = config('quote.document');
        $font = $doc['font'] ?? 'angsananew';

        // ถ้าไม่มีไฟล์ฟอนต์ที่เลือก ให้ถอยไปใช้ TH Sarabun New
        if ($font === 'angsananew' && ! is_file(resource_path('fonts/AngsanaNew.ttf'))) {
            $font = 'thsarabunnew';
        }

        $defaultConfig = (new ConfigVariables)->getDefaults();
        $defaultFontConfig = (new FontVariables)->getDefaults();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $doc['paper'] ?? 'A4',
            'orientation' => 'P',
            'margin_left' => (float) ($doc['margin_left_mm'] ?? 6),
            'margin_right' => (float) ($doc['margin_right_mm'] ?? 4),
            'margin_top' => (float) ($doc['margin_top_mm'] ?? 4.5),
            'margin_bottom' => (float) ($doc['margin_bottom_mm'] ?? 6),
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => storage_path('app/mpdf'),

            // ---- ฟอนต์ไทย ----
            'fontDir' => array_merge($defaultConfig['fontDir'], [resource_path('fonts')]),
            'fontdata' => $defaultFontConfig['fontdata'] + [
                // ฟอนต์ตามแบบฟอร์มต้นแบบ (Excel ใช้ Angsana New)
                'angsananew' => [
                    'R' => 'AngsanaNew.ttf',
                    'B' => 'AngsanaNewBold.ttf',
                    'I' => 'AngsanaNewItalic.ttf',
                    'BI' => 'AngsanaNewBoldItalic.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
                'thsarabunnew' => [
                    'R' => 'THSarabunNew.ttf',
                    'B' => 'THSarabunNew-Bold.ttf',
                    'I' => 'THSarabunNew-Italic.ttf',
                    'BI' => 'THSarabunNew-BoldItalic.ttf',
                    'useOTL' => 0xFF,      // เปิด OpenType layout ให้สระ/วรรณยุกต์วางตำแหน่งถูกต้อง
                    'useKashida' => 75,
                ],
            ],
            'default_font' => $font,
            'default_font_size' => (float) ($doc['font_size'] ?? 12),

            // ---- ภาษา/การตัดคำ ----
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'languageToFont' => new ThaiLanguageToFont($font), // ให้ th → ฟอนต์ที่เลือก แทน garuda
            'useDictionaryLBR' => true,                         // ตัดคำไทยด้วย dictionary ของ mPDF
            'useSubstitutions' => false,
            'shrink_tables_to_fit' => 1,
            'img_dpi' => 300,
        ]);

        $mpdf->SetDisplayMode('fullpage');

        return $mpdf;
    }

    /**
     * ลบไฟล์ PDF ที่เก่ากว่าจำนวนวันที่กำหนด — คืนจำนวนไฟล์ที่ลบ
     */
    public function purgeOlderThan(int $days): int
    {
        $dir = storage_path('app/'.trim(config('quote.storage_dir', 'quotes'), '/'));

        if (! is_dir($dir)) {
            return 0;
        }

        $threshold = time() - ($days * 86400);
        $deleted = 0;

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.pdf') ?: [] as $file) {
            if (filemtime($file) < $threshold && @unlink($file)) {
                $deleted++;
            }
        }

        // ลบไฟล์ temp ที่ค้าง
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.tmp') ?: [] as $file) {
            if (filemtime($file) < time() - 3600) {
                @unlink($file);
            }
        }

        return $deleted;
    }
}
