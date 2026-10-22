<?php

namespace App\Services;

use App\Pdf\ThaiLanguageToFont;
use App\Support\QuoteData;
use App\Support\QuoteForm;
use App\Support\QuoteLink;
use App\Support\RichText;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * สร้างไฟล์ PDF ใบเสนอราคาจากข้อมูล Zoho ตามแบบฟอร์มที่เลือก และจัดการ cache ไฟล์
 *
 * cache key = quote:{id}:{Modified_Time}:{form}:{templateVersion}
 * → สร้างใหม่เมื่อ Quote ถูกแก้ใน CRM หรือ template/config/รูป ของฟอร์มเปลี่ยน
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
    public function getPdfPath(QuoteData $quote, ?QuoteForm $form = null): string
    {
        $form ??= QuoteForm::default();
        $path = $this->cachePath($quote, $form);

        if (is_file($path) && filesize($path) > 0) {
            Log::channel('zoho')->debug('PDF cache hit', ['quote' => $quote->id(), 'form' => $form->slug, 'path' => basename($path)]);

            return $path;
        }

        $binary = $this->render($quote, $form);

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // เขียนลงไฟล์ชั่วคราวก่อนแล้วค่อย rename เพื่อกันไฟล์ครึ่ง ๆ กลาง ๆ เวลามี request พร้อมกัน
        $tmp = $path.'.'.uniqid('', true).'.tmp';
        file_put_contents($tmp, $binary);
        rename($tmp, $path);

        Log::channel('zoho')->info('PDF rendered', ['quote' => $quote->id(), 'form' => $form->slug, 'bytes' => strlen($binary)]);

        return $path;
    }

    /**
     * อ่านเนื้อหา PDF (ใช้ cache ถ้ามี)
     */
    public function getPdfBinary(QuoteData $quote, ?QuoteForm $form = null): string
    {
        return (string) file_get_contents($this->getPdfPath($quote, $form));
    }

    /**
     * สร้าง PDF จาก Blade view ของฟอร์มด้วย mPDF และคืนค่าเป็น binary string
     */
    public function render(QuoteData $quote, ?QuoteForm $form = null): string
    {
        $form ??= QuoteForm::default();

        $html = View::make($form->view, [
            'q' => $quote,
            'form' => $form,
            'company' => array_merge(config('quote.company'), $form->option('company', [])),
            'approver' => array_merge(config('quote.approver'), $form->option('approver', [])),
            'texts' => array_merge(config('quote.texts'), $form->option('texts', [])),
            'doc' => array_merge(config('quote.document'), $form->option('document', [])),
            'formCode' => $form->option('form_code', config('quote.form_code')),
            'trackingUrl' => config('quote.show_qr') ? QuoteLink::url($quote->id(), form: $form->slug) : null,
        ])->render();

        $mpdf = $this->makeMpdf($form);
        $mpdf->SetTitle($quote->number());
        $mpdf->SetAuthor(config('quote.company.name_th', ''));
        $mpdf->SetSubject(RichText::plain($quote->serviceSubject()));

        $doc = array_merge(config('quote.document'), $form->option('document', []));

        // template แบ่ง 2 ส่วนด้วย marker: เนื้อหา | ส่วนปิดท้าย (ข้อความ + กล่องลงนาม) ที่ต้องตรึงล่างสุด
        [$body, $closing] = array_pad(explode('<!--CLOSING-->', $html, 2), 2, null);

        $mpdf->WriteHTML($body);

        if ($closing !== null && trim($closing) !== '') {
            // วัดพื้นที่ที่เหลือจริงบนหน้าปัจจุบัน (mm) ถ้าไม่พอสำหรับส่วนปิดท้าย → ขึ้นหน้าใหม่ก่อน
            $remaining = $mpdf->h - $mpdf->bMargin - $mpdf->y;
            $needed = (float) ($doc['closing_height_mm'] ?? 70);

            if ($remaining < $needed) {
                $mpdf->AddPage();
            }

            // ส่วนปิดท้ายใช้ position:absolute (bottom) จึงไปเกาะล่างสุดของหน้าปัจจุบัน
            $mpdf->WriteHTML($closing);
        }

        // วาดกรอบรอบพื้นที่เนื้อหาให้ครบทุกหน้า (เหนือ footer)
        if (! empty($doc['frame'])) {
            $this->drawFrames($mpdf);
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * วาดกรอบสี่เหลี่ยมรอบเนื้อหาในทุกหน้า: จากขอบบนถึงเหนือ footer เล็กน้อย
     */
    protected function drawFrames(Mpdf $mpdf): void
    {
        $lastPage = $mpdf->page;
        $x = $mpdf->lMargin;
        $y = $mpdf->tMargin;
        $w = $mpdf->w - $mpdf->lMargin - $mpdf->rMargin;
        $h = $mpdf->h - $mpdf->tMargin - $mpdf->bMargin - 1.5;

        $mpdf->SetDrawColor(0);
        $mpdf->SetLineWidth(0.18); // ≈ 0.5pt

        for ($p = 1; $p <= $lastPage; $p++) {
            $mpdf->page = $p;
            $mpdf->Rect($x, $y, $w, $h);
        }

        $mpdf->page = $lastPage;
    }

    /**
     * cache key: quote:{id}:{Modified_Time}:{form}:{templateVersion}
     */
    public function cacheKey(QuoteData $quote, ?QuoteForm $form = null): string
    {
        $form ??= QuoteForm::default();

        return "quote:{$quote->id()}:{$quote->modifiedTime()}:{$form->slug}:".$this->templateVersion($form);
    }

    /**
     * ลายนิ้วมือของ "เวอร์ชันเอกสาร" = template Blade ของฟอร์ม + config quote.* + ไฟล์รูปที่ใช้
     * เปลี่ยนเมื่อ deploy เวอร์ชันใหม่ → PDF ถูกสร้างใหม่โดยไม่ต้องรอให้ Quote ถูกแก้ใน CRM
     */
    public function templateVersion(?QuoteForm $form = null): string
    {
        $form ??= QuoteForm::default();

        // คำนวณใหม่ทุกครั้ง (ราคาถูก: filemtime ไม่กี่ไฟล์) เพื่อให้เห็นการเปลี่ยน config ทันที
        $parts = [json_encode(config('quote')), $form->slug];

        $files = [
            $form->viewPath(),
            config('quote.company.header_image'),
            config('quote.company.footer_image'),
            config('quote.approver.signature'),
        ];

        foreach ($files as $file) {
            if ($file && is_file($file)) {
                $parts[] = $file.'|'.filemtime($file).'|'.filesize($file);
            }
        }

        return substr(sha1(implode("\n", $parts)), 0, 10);
    }

    /**
     * path ไฟล์ใน storage/app/quotes/ — {id}_{form}_{hash}.pdf
     */
    public function cachePath(QuoteData $quote, ?QuoteForm $form = null): string
    {
        $form ??= QuoteForm::default();
        $dir = storage_path('app/'.trim(config('quote.storage_dir', 'quotes'), '/'));
        $slug = strtolower(preg_replace('/[^A-Za-z0-9_-]/', '', $form->slug));

        return $dir.DIRECTORY_SEPARATOR.$quote->id().'_'.$slug.'_'.substr(sha1($this->cacheKey($quote, $form)), 0, 16).'.pdf';
    }

    /**
     * ตั้งค่า mPDF: กระดาษ A4, margin ตาม Excel ต้นแบบ, ฟอนต์ไทย Angsana New / TH Sarabun New
     * ฟอร์มสามารถ override ค่าใน 'document' ได้ (paper, margin, font, font_size)
     */
    protected function makeMpdf(QuoteForm $form): Mpdf
    {
        $doc = array_merge(config('quote.document'), $form->option('document', []));
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
            'margin_bottom' => (float) ($doc['margin_bottom_mm'] ?? 30),
            'margin_header' => 0,
            'margin_footer' => (float) ($doc['margin_footer_mm'] ?? 3),
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

    /**
     * ลบไฟล์ PDF ที่ cache ไว้ทั้งหมด — คืนจำนวนไฟล์ที่ลบ
     */
    public function clearAll(): int
    {
        $dir = storage_path('app/'.trim(config('quote.storage_dir', 'quotes'), '/'));
        $deleted = 0;

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.{pdf,tmp}', GLOB_BRACE) ?: [] as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
