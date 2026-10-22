<?php

namespace App\Services;

use App\Pdf\ThaiLanguageToFont;
use App\Support\DealData;
use App\Support\PrintableRecord;
use App\Support\QuoteData;
use App\Support\QuoteForm;
use App\Support\QuoteLink;
use App\Support\RichText;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
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
    public function load(string $id, ?QuoteForm $form = null): PrintableRecord
    {
        // ฟอร์มของ module Deals (เช่น TRACKING) → DealData, นอกนั้น → QuoteData
        if ($form !== null && strcasecmp($form->module(), 'Deals') === 0) {
            $data = $this->zoho->getDealWithRelations($id);

            return new DealData($data['deal'], $data['account'], $data['contact'], $data['employee']);
        }

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
    public function getPdfPath(PrintableRecord $quote, ?QuoteForm $form = null): string
    {
        $form ??= QuoteForm::default();
        $path = $this->cachePath($quote, $form);

        if (is_file($path) && filesize($path) > 0) {
            Log::channel('zoho')->debug('PDF cache hit', ['quote' => $quote->id(), 'form' => $form->slug, 'path' => basename($path)]);
            $this->lastRenderWasCached = true;
            // สถานะลายเซ็นตอน cache hit: มีไฟล์ cache ของลายเซ็นหรือไม่
            $this->signatureStatus = $this->salesSignaturePath($quote) ? 'cached' : $this->signatureStatus;

            return $path;
        }

        $this->lastRenderWasCached = false;
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
     * ลบไฟล์ PDF ที่ cache ไว้ของ Quote/ฟอร์มนี้ (ทุกเวอร์ชัน) เพื่อบังคับสร้างใหม่ใน request ถัดไป
     */
    public function forget(PrintableRecord $quote, ?QuoteForm $form = null): int
    {
        $form ??= QuoteForm::default();
        $dir = storage_path('app/'.trim(config('quote.storage_dir', 'quotes'), '/'));
        $slug = strtolower(preg_replace('/[^A-Za-z0-9_-]/', '', $form->slug));
        $deleted = 0;

        foreach (glob($dir.DIRECTORY_SEPARATOR.$quote->id().'_'.$slug.'_*.pdf') ?: [] as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * อ่านเนื้อหา PDF (ใช้ cache ถ้ามี)
     */
    public function getPdfBinary(PrintableRecord $quote, ?QuoteForm $form = null): string
    {
        return (string) file_get_contents($this->getPdfPath($quote, $form));
    }

    /**
     * สร้าง PDF จาก Blade view ของฟอร์มด้วย mPDF และคืนค่าเป็น binary string
     */
    public function render(PrintableRecord $quote, ?QuoteForm $form = null): string
    {
        $form ??= QuoteForm::default();

        $docOptions = array_merge(config('quote.document'), $form->option('document', []));
        $docOptions['cjk_font_available'] = $this->cjkFontFile($docOptions) !== null;

        $html = View::make($form->view, [
            'q' => $quote,
            'form' => $form,
            'company' => array_merge(config('quote.company'), $form->option('company', [])),
            'approver' => array_merge(config('quote.approver'), $form->option('approver', [])),
            'texts' => array_merge(config('quote.texts'), $form->option('texts', [])),
            // ป้ายช่องในฟอร์ม (ไทย/อังกฤษ หรือ ไทย/จีน ตามฟอร์ม)
            'L' => array_merge(config('quote.labels', []), $form->option('labels', [])),
            'doc' => $docOptions,
            'formCode' => $form->option('form_code', config('quote.form_code')),
            // รูปลายเซ็นพนักงานขาย (ดาวน์โหลดจาก Staffs.Image_Sing แล้ว cache ไว้) — null ถ้าไม่มี/ดาวน์โหลดไม่ได้
            'salesSignature' => $this->salesSignaturePath($quote),
            'trackingUrl' => config('quote.show_qr') ? QuoteLink::url($quote->id(), form: $form->slug) : null,
        ])->render();

        $mpdf = $this->makeMpdf($form);
        $mpdf->SetTitle($quote->number());
        $mpdf->SetAuthor(config('quote.company.name_th', ''));
        $mpdf->SetSubject(RichText::plain($quote->serviceSubject()));

        $doc = array_merge(config('quote.document'), $form->option('document', []));

        // template แบ่งส่วนด้วย marker:
        //   <!--HEADER-->…<!--/HEADER-->  หัวกระดาษ → ตั้งเป็น page header ซ้ำทุกหน้า (SetHTMLHeader เชื่อถือได้กว่า tag ใน HTML)
        //   <!--CLOSING-->                เนื้อหา | ส่วนปิดท้าย (ข้อความ + กล่องลงนาม) ที่ต้องตรึงล่างสุด
        if (preg_match('/<!--HEADER-->(.*?)<!--\/HEADER-->/s', $html, $m)) {
            $html = str_replace($m[0], '', $html);
            // header render แยกจาก <style> ของเอกสาร → template ต้องใช้ inline style (ห้ามใส่ <style> ที่นี่ จะทำให้ header หาย)
            $mpdf->SetHTMLHeader(trim($m[1]), 'O', true);
        }

        //   <!--FOOTER-->…<!--/FOOTER-->  ท้ายกระดาษ (รูป ISO/QR + เลขหน้า + รหัสฟอร์ม) → page footer ทุกหน้าตั้งแต่หน้าแรก
        if (preg_match('/<!--FOOTER-->(.*?)<!--\/FOOTER-->/s', $html, $f)) {
            $html = str_replace($f[0], '', $html);
            $mpdf->SetHTMLFooter(trim($f[1]), 'O');
        }

        [$body, $closing] = array_pad(explode('<!--CLOSING-->', $html, 2), 2, null);

        // mPDF จะล้าง page header ที่ตั้งไว้ถ้าเขียนเอกสารเต็ม (<html><head>…) ทีเดียว
        // จึงเขียน CSS กับเนื้อหาแยกเป็น fragment แทน (HTMLParserMode::HEADER_CSS แล้วค่อย body)
        $css = preg_match('/<style>(.*?)<\/style>/s', $body, $s) ? $s[1] : '';
        // กฎ @page ใน CSS ทำให้ mPDF ล้าง page header ที่ตั้งไว้ → ตัดทิ้ง (ขนาด/ขอบกระดาษตั้งผ่าน config อยู่แล้ว)
        $css = preg_replace('/^\s*@page\b[^{]*\{[^}]*\}/m', '', $css) ?? $css;
        $inner = preg_match('/<body[^>]*>(.*)$/s', $body, $b) ? $b[1] : $body;

        if ($css !== '') {
            $mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
        }

        $this->writeBodyWithFilledRows($mpdf, $inner, $doc);

        if ($closing !== null && trim($closing) !== '') {
            // วัดพื้นที่ที่เหลือจริงบนหน้าปัจจุบัน (mm) ถ้าไม่พอสำหรับส่วนปิดท้าย → ขึ้นหน้าใหม่ก่อน
            $remaining = $mpdf->h - $mpdf->bMargin - $mpdf->y;
            $needed = (float) ($doc['closing_height_mm'] ?? 70);

            if ($remaining < $needed) {
                if (($doc['closing_mode'] ?? 'pin') === 'pin-or-flow') {
                    // ที่ไม่พอ → ไม่เปิดหน้าใหม่เพื่อใส่แค่ส่วนปิดท้าย แต่วางต่อท้ายเนื้อหาแบบไหลตามปกติ
                    // (ถอด position:absolute ออก ถ้าล้นจริงจะไปต่อหน้าถัดไปพร้อม page-break-inside: avoid)
                    $closing = str_replace('class="closing-pinned"', 'class="closing-flow"', $closing);
                } else {
                    $mpdf->AddPage();
                }
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
     * เขียนเนื้อหา โดยหยุดที่ marker <!--ITEMS_END count=N terms=0|1--> หลังตารางรายการ
     * วัดตำแหน่ง y จริง แล้วเติมแถวว่าง (snippet <!--BLANKROWS-->) ให้ตารางยาวพอดีกรอบ/พอดีก่อนส่วนลงนาม
     * ก่อนเขียนส่วนที่เหลือ (แถวยอดรวม, เงื่อนไข)
     */
    protected function writeBodyWithFilledRows(Mpdf $mpdf, string $inner, array $doc): void
    {
        // ดึง snippet แถวว่างออกจากเนื้อหา
        $rowTable = null;
        if (preg_match('/<!--BLANKROWS-->(.*?)<!--\/BLANKROWS-->/s', $inner, $b)) {
            $inner = str_replace($b[0], '', $inner);
            $rowTable = $b[1];
        }

        if ($rowTable === null || ! preg_match('/<!--ITEMS_END count=(\d+) terms=(\d)-->/', $inner, $m)) {
            $mpdf->WriteHTML($inner);

            return;
        }

        [$before, $after] = explode($m[0], $inner, 2);
        $itemCount = (int) $m[1];
        $termsOnNextPage = $m[2] === '1';

        $mpdf->WriteHTML($before);

        $minRows = (int) ($doc['min_item_rows'] ?? 3);
        $hasRowTpl = preg_match('/<!--ROW-->(.*?)<!--\/ROW-->/s', $rowTable, $r) === 1;
        $makeRows = fn (int $n) => str_replace($r[0], str_repeat($r[1], $n), $rowTable);

        if ($hasRowTpl && ! empty($doc['fill_item_rows'])) {
            // 1) เขียนแถวว่าง 1 แถวก่อนเพื่อวัดความสูงจริงที่ mPDF ใช้ (CSS height ของ tr ไม่ตรงกับที่ตั้งเสมอ)
            $yBefore = $mpdf->y;
            $pageBefore = $mpdf->page;
            $mpdf->WriteHTML($makeRows(1));
            $rowHeight = $mpdf->y - $yBefore;

            if ($mpdf->page !== $pageBefore || $rowHeight <= 0.5) {
                $rowHeight = (float) ($doc['item_row_height_mm'] ?? 9.0);
            }

            // 2) พื้นที่ที่เหลือ = ขอบล่างกรอบ − y − แถวยอดรวม (สูงกว่าแถวปกติเล็กน้อย) − (ส่วนลงนาม ถ้าอยู่หน้าเดียวกัน)
            $frameBottom = $mpdf->h - $mpdf->bMargin - 1.5;
            $reserve = $rowHeight * 1.2 + 1.0
                + ($termsOnNextPage ? 0.0 : (float) ($doc['closing_height_mm'] ?? 67) + 3.0);
            $more = (int) floor(($frameBottom - $mpdf->y - $reserve) / $rowHeight);

            if ($more > 0) {
                $mpdf->WriteHTML($makeRows($more));
            }
        } elseif ($hasRowTpl) {
            $rows = max(0, $minRows - $itemCount);
            if ($rows > 0) {
                $mpdf->WriteHTML($makeRows($rows));
            }
        }

        $mpdf->WriteHTML($after);
    }

    /**
     * วาดกรอบสี่เหลี่ยมรอบเนื้อหาในทุกหน้า: จากขอบบนถึงเหนือ footer เล็กน้อย
     */
    protected function drawFrames(Mpdf $mpdf): void
    {
        $lastPage = $mpdf->page;
        $x = $mpdf->lMargin;
        $y = $mpdf->margin_header;   // กรอบเริ่มเหนือหัวกระดาษ (ครอบโลโก้ด้วยเหมือนต้นแบบ)
        $w = $mpdf->w - $mpdf->lMargin - $mpdf->rMargin;
        $h = $mpdf->h - $y - $mpdf->bMargin - 1.5;

        $mpdf->SetDrawColor(0);
        $mpdf->SetLineWidth(0.11); // ≈ 0.3pt (บางเท่าเส้นตาราง)

        for ($p = 1; $p <= $lastPage; $p++) {
            $mpdf->page = $p;
            $mpdf->Rect($x, $y, $w, $h);
        }

        $mpdf->page = $lastPage;
    }

    /**
     * cache key: quote:{id}:{Modified_Time}:{form}:{templateVersion}
     */
    public function cacheKey(PrintableRecord $quote, ?QuoteForm $form = null): string
    {
        $form ??= QuoteForm::default();

        // รวมสถานะลายเซ็นพนักงานขายด้วย: ใช้ File_Id เฉพาะเมื่อมีไฟล์รูปจริง (ดาวน์โหลดได้แล้ว)
        // → เปลี่ยนรูปใน Staffs หรือดาวน์โหลดสำเร็จครั้งแรก PDF จะถูกสร้างใหม่ ไม่ค้าง PDF ที่ไม่มีลายเซ็น
        $sig = $this->salesSignaturePath($quote) ? ($quote->salesSignatureFileId() ?? '-') : 'nosig';

        return "quote:{$quote->id()}:{$quote->modifiedTime()}:{$form->slug}:{$sig}:".$this->templateVersion($form);
    }

    /**
     * path รูปลายเซ็นพนักงานขาย: ดาวน์โหลดจาก Zoho Files API ครั้งแรกแล้วเก็บที่ storage/app/signatures/{File_Id}
     * คืน null เมื่อไม่มีรูป หรือดาวน์โหลดไม่ได้ (เช่น ยังไม่มี scope ZohoCRM.Files.READ) → ฟอร์มแสดงช่องว่างตามปกติ
     */
    /** สถานะลายเซ็นพนักงานขายของการ render ล่าสุด (ส่งเป็น header X-Quote-Signature เพื่อวินิจฉัยบนเซิร์ฟเวอร์) */
    public string $signatureStatus = 'none';

    /** request ล่าสุดใช้ไฟล์ PDF จาก cache หรือไม่ (header X-Quote-Cache) */
    public bool $lastRenderWasCached = false;

    public function salesSignaturePath(PrintableRecord $quote): ?string
    {
        $fileId = $quote->salesSignatureFileId();

        if ($fileId === null || ! preg_match('/^[A-Za-z0-9_-]+$/', $fileId)) {
            $this->signatureStatus = $quote->employeeRecord() === null ? 'no-staff-record' : 'no-image-field';

            return null;
        }

        $dir = storage_path('app/signatures');
        foreach (glob($dir.DIRECTORY_SEPARATOR.$fileId.'.*') ?: [] as $existing) {
            $this->signatureStatus = 'cached';

            return $existing;
        }

        $file = $this->zoho->downloadFile($fileId);

        if ($file === null) {
            $this->signatureStatus = 'download-failed (check scope ZohoCRM.Files.READ / token / zoho log)';

            return null;
        }

        $this->signatureStatus = 'downloaded';

        $ext = match (true) {
            str_contains($file['type'], 'png') => 'png',
            str_contains($file['type'], 'gif') => 'gif',
            str_contains($file['type'], 'jpeg'), str_contains($file['type'], 'jpg') => 'jpg',
            default => (preg_match('/^\x89PNG/', $file['body']) ? 'png' : 'jpg'),
        };

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.$fileId.'.'.$ext;

        if (@file_put_contents($path, $file['body']) === false) {
            $this->signatureStatus = 'write-failed (storage/app/signatures not writable)';
            Log::channel('zoho')->error('Cannot write signature cache', ['path' => $path]);

            return null;
        }

        Log::channel('zoho')->info('Sales signature cached', ['file_id' => $fileId, 'bytes' => strlen($file['body'])]);

        return $path;
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
    public function cachePath(PrintableRecord $quote, ?QuoteForm $form = null): string
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

        // ฟอนต์อักษรจีน (ถ้ามีไฟล์): SimSun เป็น TrueType Collection → ใช้ face แรก (TTCfontID = 1)
        $cjkFont = null;
        $cjkFontData = [];
        if (($cjkFile = $this->cjkFontFile($doc)) !== null) {
            $cjkFont = $doc['cjk_font'];
            // mPDF อ่าน TTCfontID แยกตาม style key (R/B/I/BI)
            $cjkFontData[$cjkFont] = str_ends_with(strtolower($cjkFile), '.ttc')
                ? ['R' => $cjkFile, 'TTCfontID' => ['R' => 1]]
                : ['R' => $cjkFile];
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $doc['paper'] ?? 'A4',
            'orientation' => 'P',
            'margin_left' => (float) ($doc['margin_left_mm'] ?? 6),
            'margin_right' => (float) ($doc['margin_right_mm'] ?? 4),
            // หัวกระดาษซ้ำทุกหน้า (htmlpageheader) อยู่ในพื้นที่ margin_top: เริ่มที่ margin_header และกินสูง header_height_mm
            'margin_top' => (float) ($doc['margin_top_mm'] ?? 4.5) + (float) ($doc['header_height_mm'] ?? 31),
            'margin_bottom' => (float) ($doc['margin_bottom_mm'] ?? 30),
            'margin_header' => (float) ($doc['margin_top_mm'] ?? 4.5),
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
            ] + $cjkFontData,
            'default_font' => $font,
            'default_font_size' => (float) ($doc['font_size'] ?? 12),

            // ---- ภาษา/การตัดคำ ----
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'languageToFont' => new ThaiLanguageToFont($font, $cjkFont), // th → ฟอนต์ไทยที่เลือก, อักษรจีน → SimSun
            'useDictionaryLBR' => true,                         // ตัดคำไทยด้วย dictionary ของ mPDF
            'useSubstitutions' => false,
            'shrink_tables_to_fit' => 1,
            'img_dpi' => 300,
        ]);

        $mpdf->SetDisplayMode('fullpage');

        return $mpdf;
    }

    /**
     * ชื่อไฟล์ฟอนต์ CJK ใน resources/fonts (simsun.ttc / <cjk_font>.ttf) — null ถ้าไม่มี
     */
    protected function cjkFontFile(array $doc): ?string
    {
        $name = (string) ($doc['cjk_font'] ?? '');

        if ($name === '') {
            return null;
        }

        foreach ([$name.'.ttc', $name.'.ttf', $name.'.otf'] as $candidate) {
            if (is_file(resource_path('fonts/'.$candidate))) {
                return $candidate;
            }
        }

        return null;
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
