<?php

namespace App\Support;

/**
 * ค่าคงที่ของ layout ฟอร์ม FA-SA (สัดส่วนคอลัมน์, รูปภาพ) ใช้ร่วมกันทุก template ในตระกูลนี้
 *
 * ใน Blade: @php extract(FormLayout::vars($q, $doc, $company)); @endphp
 * (partial ที่ @include จะได้รับตัวแปรเหล่านี้ต่อโดยอัตโนมัติ)
 */
class FormLayout
{
    /**
     * @return array<string, mixed>
     */
    public static function vars(QuoteData $q, array $doc, array $company): array
    {
        // สัดส่วนคอลัมน์ Excel (A..L = 157.8 หน่วย) → เปอร์เซ็นต์ของความกว้างกระดาษ
        // (คอลัมน์ I ขยายเล็กน้อยจาก 12.58 → 14.3 เพื่อให้ "ใบเสนอราคาเลขที่ :" อยู่บรรทัดเดียว)
        $wA = 1.45;
        $wB = 7.97;
        $wCH = 55.58;
        $wI = 14.30;
        $wJ = 7.79;
        $wK = 12.58;
        $wL = 1.63;

        // ตารางรายการใช้สัดส่วน ราคา/จำนวน/จำนวนเงิน ของตัวเอง (ผลรวมเท่า wI+wJ+wK)
        // ช่อง "จำนวน" ต้องกว้างพอให้ "Total Discount" ในกล่องสรุปยอดอยู่บรรทัดเดียว ไม่ดันคอลัมน์ให้เบี้ยว
        $tI = 15.50;
        $tJ = 11.50;
        $tK = 11.58;
        $tSum = $tI + $tJ + $tK;
        $tCH = round(100 - $wA - $wB - $tSum - $wL, 2);

        $headerImg = $company['header_image'] ?? null;
        $footerImg = $company['footer_image'] ?? null;

        return [
            'wA' => $wA, 'wB' => $wB, 'wCH' => $wCH, 'wI' => $wI, 'wJ' => $wJ, 'wK' => $wK, 'wL' => $wL,
            'tI' => $tI, 'tJ' => $tJ, 'tK' => $tK, 'tSum' => $tSum, 'tCH' => $tCH,
            'headerImg' => $headerImg,
            'footerImg' => $footerImg,
            'hasHeader' => $headerImg && is_file($headerImg),
            'hasFooter' => $footerImg && is_file($footerImg),
            // เงื่อนไขขึ้นหน้าใหม่หรือไม่ (ใช้ใน marker ITEMS_END ให้ service รู้ว่าต้องกันที่ให้ส่วนลงนามหรือไม่)
            'hasTermsPage' => ($doc['terms_on_new_page'] ?? true) && trim($q->paymentTerms()) !== '',
            'termsHtml' => RichText::block($q->paymentTerms()),
        ];
    }
}
