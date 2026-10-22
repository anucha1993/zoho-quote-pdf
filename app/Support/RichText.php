<?php

namespace App\Support;

/**
 * แปลง rich text จาก Zoho CRM (field ชนิด Rich Text ส่ง HTML มา) ให้ปลอดภัยสำหรับ mPDF
 *
 * - เก็บเฉพาะ tag จัดรูปแบบพื้นฐาน (ตัวหนา เอียง ขีดเส้นใต้ รายการ ย่อหน้า ขึ้นบรรทัด)
 * - ตัด attribute ทั้งหมดทิ้ง (style/color/class/onclick ฯลฯ) เพื่อไม่ให้ CSS ของ CRM มาทับ layout
 * - ข้อความธรรมดา (ไม่มี tag) จะ escape และแปลง \n เป็น <br> เหมือนเดิม
 */
class RichText
{
    /** tag ที่อนุญาตในบล็อกข้อความ (เงื่อนไข, รายละเอียด) */
    private const BLOCK_TAGS = ['b', 'strong', 'i', 'em', 'u', 's', 'strike', 'sub', 'sup', 'br', 'p', 'div', 'ol', 'ul', 'li', 'span'];

    /** tag ที่อนุญาตในข้อความบรรทัดเดียว (หัวข้อ, ชื่อรายการ) */
    private const INLINE_TAGS = ['b', 'strong', 'i', 'em', 'u', 'br', 'span'];

    /**
     * ข้อความหลายบรรทัด เช่น เงื่อนไขการชำระเงิน
     */
    public static function block(?string $value): string
    {
        return self::clean($value, self::BLOCK_TAGS);
    }

    /**
     * ข้อความสั้น เช่น หัวข้อการให้บริการ (ตัด p/div/li ออก เหลือแต่ inline)
     */
    public static function inline(?string $value): string
    {
        return self::clean($value, self::INLINE_TAGS);
    }

    /**
     * ข้อความล้วน ไม่มี tag เลย (ใช้กับ metadata เช่น SetSubject ของ PDF)
     */
    public static function plain(?string $value): string
    {
        $value = (string) $value;

        if (! self::looksLikeHtml($value)) {
            return trim($value);
        }

        $value = preg_replace('/<br\s*\/?>|<\/(p|div|li)>/i', "\n", $value) ?? $value;

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public static function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<\s*\/?\s*[a-z][a-z0-9]*[^>]*>/i', $value);
    }

    /**
     * @param  string[]  $allowed  tag ที่อนุญาต (ตัวพิมพ์เล็ก)
     */
    protected static function clean(?string $value, array $allowed): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        // ข้อความธรรมดา → escape + ขึ้นบรรทัดตาม \n
        if (! self::looksLikeHtml($value)) {
            return nl2br(e($value));
        }

        // ตัด script/style ทั้งก้อน
        $value = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $value) ?? $value;

        // เก็บเฉพาะ tag ที่อนุญาต
        $value = strip_tags($value, array_map(fn ($t) => "<{$t}>", $allowed));

        // ตัด attribute ทั้งหมดออกจาก tag ที่เหลือ: <b style="..."> → <b>
        $value = preg_replace_callback('/<(\/?)([a-z][a-z0-9]*)\b[^>]*?(\/?)>/i', function ($m) {
            $tag = strtolower($m[2]);
            $selfClose = $tag === 'br' ? ' /' : $m[3];

            return "<{$m[1]}{$tag}{$selfClose}>";
        }, $value) ?? $value;

        // <p>/<div> ว่าง ๆ ที่ Zoho ชอบใส่ → ตัดทิ้ง
        $value = preg_replace('/<(p|div)>\s*(&nbsp;|\s)*<\/\1>/i', '', $value) ?? $value;

        // Zoho ห่อข้อความใน <li> ด้วย <p> (display:inline) → ถ้าปล่อยไว้ mPDF จะขึ้นบรรทัดใหม่หลังเลขข้อ
        $value = preg_replace_callback('/<li>(.*?)<\/li>/is', function ($m) {
            return '<li>'.preg_replace('/<\/?(p|div)>/i', '', $m[1]).'</li>';
        }, $value) ?? $value;

        // ข้อความใน inline mode: แปลงท้ายย่อหน้าที่ถูกตัด tag ไปแล้วให้เป็นช่องว่างเดียว
        $value = preg_replace('/[ \t]*\r?\n[ \t]*/', ' ', $value) ?? $value;

        return trim($value);
    }
}
