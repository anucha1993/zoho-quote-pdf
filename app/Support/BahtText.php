<?php

namespace App\Support;

/**
 * แปลงจำนวนเงินเป็นคำอ่านภาษาไทย เช่น 54900.50 → "ห้าหมื่นสี่พันเก้าร้อยบาทห้าสิบสตางค์"
 */
class BahtText
{
    private const DIGITS = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];

    private const UNITS = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];

    /**
     * @param  float|int|string  $amount  จำนวนเงิน (รองรับค่าติดลบและทศนิยม)
     */
    public static function convert(float|int|string $amount): string
    {
        $amount = round((float) $amount, 2);

        $negative = $amount < 0;
        $amount = abs($amount);

        $baht = (int) floor($amount);
        $satang = (int) round(($amount - $baht) * 100);

        // กันกรณีปัดเศษแล้วสตางค์เป็น 100
        if ($satang >= 100) {
            $baht++;
            $satang -= 100;
        }

        if ($baht === 0 && $satang === 0) {
            return 'ศูนย์บาทถ้วน';
        }

        $text = '';

        if ($baht > 0) {
            $text .= self::readInteger($baht).'บาท';
        }

        $text .= $satang > 0
            ? self::readInteger($satang).'สตางค์'
            : 'ถ้วน';

        return ($negative ? 'ลบ' : '').$text;
    }

    /**
     * อ่านจำนวนเต็ม (รองรับหลักล้านซ้อนกัน เช่น "ล้านล้าน")
     */
    private static function readInteger(int $number): string
    {
        if ($number === 0) {
            return self::DIGITS[0];
        }

        // แบ่งเป็นกลุ่มละ 6 หลัก (หลักล้าน)
        $groups = [];
        while ($number > 0) {
            $groups[] = $number % 1_000_000;
            $number = intdiv($number, 1_000_000);
        }

        $result = '';
        $count = count($groups);

        for ($i = $count - 1; $i >= 0; $i--) {
            $chunk = $groups[$i];

            if ($chunk > 0) {
                // กลุ่มที่ต่ำกว่ากลุ่มบนสุดจะมีหลักที่สูงกว่านำหน้าเสมอ (เช่น 1,000,001 = หนึ่งล้านเอ็ด)
                $result .= self::readChunk($chunk, hasHigherDigits: $i < $count - 1);
            }

            // ทุกกลุ่มที่อยู่สูงกว่ากลุ่มหน่วยต้องตามด้วย "ล้าน"
            if ($i > 0) {
                $result .= 'ล้าน';
            }
        }

        return $result;
    }

    /**
     * อ่านตัวเลขไม่เกิน 6 หลัก (0 - 999,999)
     */
    private static function readChunk(int $number, bool $hasHigherDigits = false): string
    {
        $text = '';
        $digits = str_split((string) $number);
        $len = count($digits);

        foreach ($digits as $index => $char) {
            $digit = (int) $char;
            $position = $len - $index - 1; // 0 = หน่วย, 1 = สิบ, 2 = ร้อย ...

            if ($digit === 0) {
                continue;
            }

            if ($position === 0 && $digit === 1 && ($len > 1 || $hasHigherDigits)) {
                // หลักหน่วยเป็น 1 และมีหลักสิบขึ้นไป → "เอ็ด" (เช่น 11 = สิบเอ็ด, 101 = หนึ่งร้อยเอ็ด)
                $text .= 'เอ็ด';
            } elseif ($position === 1 && $digit === 2) {
                // หลักสิบเป็น 2 → "ยี่สิบ"
                $text .= 'ยี่สิบ';
            } elseif ($position === 1 && $digit === 1) {
                // หลักสิบเป็น 1 → "สิบ" (ไม่อ่านว่า "หนึ่งสิบ")
                $text .= 'สิบ';
            } else {
                $text .= self::DIGITS[$digit].self::UNITS[$position];
            }
        }

        return $text;
    }
}
