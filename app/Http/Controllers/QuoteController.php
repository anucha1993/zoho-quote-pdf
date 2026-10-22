<?php

namespace App\Http\Controllers;

use App\Exceptions\QuoteNotFoundException;
use App\Exceptions\ZohoApiException;
use App\Services\QuotePdfService;
use App\Services\ZohoCrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint สำหรับแสดง/ดาวน์โหลด/แนบ PDF ใบเสนอราคา
 *
 * signature ถูกตรวจแล้วใน middleware VerifyQuoteSignature (ผิด → 404)
 */
class QuoteController extends Controller
{
    public function __construct(
        protected QuotePdfService $pdf,
        protected ZohoCrmService $zoho,
    ) {}

    /**
     * GET /quote/{id}.{sig}            → แสดง PDF inline
     * GET /quote/{id}.{sig}?download=1 → ดาวน์โหลด (ชื่อไฟล์ = Quote Number)
     */
    public function show(Request $request, string $id): Response
    {
        try {
            $quote = $this->pdf->load($id);
            $path = $this->pdf->getPdfPath($quote);
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
        ]);
    }

    /**
     * POST /quote/{id}.{sig}/attach → สร้าง PDF แล้วแนบกลับเข้า Quote ใน CRM
     */
    public function attach(string $id): JsonResponse
    {
        try {
            $quote = $this->pdf->load($id);
            $binary = $this->pdf->getPdfBinary($quote);
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
