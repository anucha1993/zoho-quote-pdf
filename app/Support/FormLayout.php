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
    public static function vars(PrintableRecord $q, array $doc, array $company): array
    {
        // สัดส่วนคอลัมน์ Excel (A..L = 157.8 หน่วย) → เปอร์เซ็นต์ของความกว้างกระดาษ
        // (คอลัมน์ I ขยายเล็กน้อยจาก 12.58 → 14.3 เพื่อให้ "ใบเสนอราคาเลขที่ :" อยู่บรรทัดเดียว)
        $wA = 1.45;
        $wB = 7.97;
        $wCH = 54.38;
        $wI = 15.50;
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
        // กล่องสรุปยอดกว้างเท่า 3 คอลัมน์ขวาของตารางพอดี (เส้นตรงกัน) — tExtra ขยายคอลัมน์ป้ายไทยไปทางซ้ายได้ถ้าป้ายยาวเกิน
        // ("จำนวนเงินรวมทั้งสิ้น" ตัวหนา 14pt กว้าง 27.9mm พอดีกับคอลัมน์ ราคา 31mm เมื่อ padding 3pt)
        $tExtra = 0.0;
        $tTotW = round($tSum + $tExtra, 2);
        $tT1 = round(($tI + $tExtra) / $tTotW * 100, 2);
        $tT2 = round($tJ / $tTotW * 100, 2);
        $tT3 = round(100 - $tT1 - $tT2, 2);

        // ตัดขอบขาวซ้าย/ขวาในตัวไฟล์ภาพออก ให้เนื้อหา header และ footer ยาวเท่ากันเมื่อ render 200mm
        $trim = (bool) ($company['trim_image_sides'] ?? true);
        // ตารางข้อมูลหัวฟอร์ม 4 แถว: ป้ายซ้าย | ค่าซ้าย | ป้ายขวา | ค่าขวา (รวม = 100 − wA − wL)
        // ป้ายกว้างพอให้ "ฝ่ายขายผู้เสนอราคา :" / "ใบเสนอราคาเลขที่ :" ตัวหนา 16pt อยู่บรรทัดเดียว
        // เส้นแบ่งตรงกับตารางรายการ: ป้ายซ้าย = คอลัมน์ ลำดับที่ (wB), ป้ายขวา = คอลัมน์ ราคา (tI),
        // ค่าขวา = คอลัมน์ จำนวน + จำนวนเงิน (tJ + tK) — ป้ายขวาใช้ 14pt (td.lr) ให้ "ฝ่ายขายผู้เสนอราคา :" อยู่บรรทัดเดียวใน 31mm
        $iLabelL = $wB;
        $iLabelR = $tI;
        $iValR = round($tJ + $tK, 2);
        $iValL = round(100 - $wA - $wL - $iLabelL - $iLabelR - $iValR, 2);

        $headerImg = $company['header_image'] ?? null;
        $footerImg = $company['footer_image'] ?? null;
        if ($trim) {
            $headerImg = $headerImg ? ImageTrim::sides($headerImg) : null;
            $footerImg = $footerImg ? ImageTrim::sides($footerImg) : null;
        }

        return [
            'wA' => $wA, 'wB' => $wB, 'wCH' => $wCH, 'wI' => $wI, 'wJ' => $wJ, 'wK' => $wK, 'wL' => $wL,
            'tI' => $tI, 'tJ' => $tJ, 'tK' => $tK, 'tSum' => $tSum, 'tCH' => $tCH,
            'iLabelL' => $iLabelL, 'iValL' => $iValL, 'iLabelR' => $iLabelR, 'iValR' => $iValR,
            'tTotW' => $tTotW, 'tT1' => $tT1, 'tT2' => $tT2, 'tT3' => $tT3,
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
