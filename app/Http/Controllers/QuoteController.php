<?php

namespace App\Http\Controllers;

use App\Exceptions\QuoteNotFoundException;
use App\Exceptions\ZohoApiException;
use App\Services\QuotePdfService;
use App\Services\ZohoCrmService;
use App\Support\QuoteForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint สำหรับแสดง/ดาวน์โหลด/แนบ PDF ใบเสนอราคา ตามแบบฟอร์มที่เลือก
 *
 * - {form} = slug ของแบบฟอร์ม เช่น FA-SA-TH (ไม่ระบุ = quote.default_form) ไม่พบ → 404
 * - signature / key ถูกตรวจแล้วใน middleware (ผิด → 404)
 */
class QuoteController extends Controller
{
    public function __construct(
        protected QuotePdfService $pdf,
        protected ZohoCrmService $zoho,
    ) {}

    /**
     * GET /{form}/{id}            → แสดง PDF inline
     * GET /{form}/{id}?download=1 → ดาวน์โหลด (ชื่อไฟล์ = เลขที่ใบเสนอราคา)
     */
    public function show(Request $request): Response
    {
        // อ่านจาก route โดยตรง เพราะ Laravel ส่ง parameter ตามลำดับใน URL ไม่ใช่ตามชื่อ
        // (route /{id}.{sig} ไม่มี form, route /{form}/{id} มี form อยู่หน้า id)
        $id = (string) $request->route('id');
        $quoteForm = QuoteForm::find($request->route('form')) ?? abort(404);

        try {
            $quote = $this->pdf->load($id);
            $path = $this->pdf->getPdfPath($quote, $quoteForm);
        } catch (QuoteNotFoundException) {
            abort(404);
        } catch (ZohoApiException $e) {
            abort(502, 'ไม่สามารถดึงข้อมูลจาก Zoho CRM ได้ในขณะนี้');
        }

        $download = $request->boolean('download');
        $filename = $quote->filename();

        // ใช้ ASCII fallback สำหรับ browser เก่า + filename* (RFC 5987) สำหรับชื่อไทย
        $disposition = ($download ? 'attachment' : 'inline')
            .'; filename="'.$this->asciiFilename($filename).'"'
            ."; filename*=UTF-8''".rawurlencode($filename);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Quote-Form' => $quoteForm->slug,
        ]);
    }

    /**
     * POST /{form}/{id}/attach → สร้าง PDF แล้วแนบกลับเข้า Quote ใน CRM
     */
    public function attach(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');
        $quoteForm = QuoteForm::find($request->route('form')) ?? abort(404);

        try {
            $quote = $this->pdf->load($id);
            $binary = $this->pdf->getPdfBinary($quote, $quoteForm);
            $result = $this->zoho->uploadAttachment($id, $binary, $quote->filename());
        } catch (QuoteNotFoundException) {
            abort(404);
        } catch (ZohoApiException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Zoho CRM error: '.$e->getMessage(),
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'quote_id' => $id,
            'form' => $quoteForm->slug,
            'filename' => $quote->filename(),
            'attachment_id' => $result['details']['id'] ?? null,
        ]);
    }

    /**
     * แปลงชื่อไฟล์ให้เหลือเฉพาะ ASCII สำหรับ header filename= แบบเก่า
     */
    protected function asciiFilename(string $filename): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $filename) ?? '';
        $ascii = str_replace(['"', '\\'], '', trim($ascii));

        return $ascii !== '' && $ascii !== '.pdf' ? $ascii : 'quote.pdf';
    }
}
