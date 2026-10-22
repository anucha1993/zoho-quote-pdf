<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * สร้าง/ตรวจสอบ signature ของลิงก์ใบเสนอราคา
 *
 * signature = HMAC-SHA256(id, QUOTE_LINK_SECRET) แบบ hex ตัวพิมพ์เล็ก
 * ตัดความยาวตาม config quote.signature_length (ค่าเริ่มต้น 64 = เต็ม)
 * signature ผูกกับ record id อย่างเดียว จึงใช้ได้กับทุกแบบฟอร์มของ Quote เดียวกัน
 *
 * ฝั่ง Deluge ต้องสร้างด้วยวิธีเดียวกัน (ดู docs/deluge/)
 */
class QuoteLink
{
    /**
     * สร้าง signature สำหรับ record id
     */
    public static function sign(string $id): string
    {
        $secret = (string) config('quote.link_secret');

        if ($secret === '') {
            throw new InvalidArgumentException('QUOTE_LINK_SECRET is not configured');
        }

        $hex = hash_hmac('sha256', $id, $secret);

        return substr($hex, 0, self::length());
    }

    /**
     * ตรวจสอบ signature แบบ timing-safe
     */
    public static function verify(string $id, string $signature): bool
    {
        if (empty(config('quote.link_secret'))) {
            return false;
        }

        return hash_equals(self::sign($id), strtolower($signature));
    }

    /**
     * URL แบบมี signature
     *   ไม่ระบุ form → https://host/quote/{id}.{sig}            (ฟอร์มเริ่มต้น)
     *   ระบุ form   → https://host/quote/{form}/{id}.{sig}
     */
    public static function url(string $id, bool $download = false, ?string $form = null): string
    {
        $base = rtrim((string) config('quote.base_url'), '/');
        $prefix = $form ? "/quote/{$form}/" : '/quote/';

        $url = "{$base}{$prefix}{$id}.".self::sign($id);

        return $download ? $url.'?download=1' : $url;
    }

    /**
     * URL แบบสั้น (ไม่มี signature) ใช้ได้เมื่อ QUOTE_ALLOW_UNSIGNED=true
     *   https://pdf.trackingmouthefirst.com/FA-SA-TH/6274719000184443042
     */
    public static function shortUrl(string $id, bool $download = false, ?string $form = null): string
    {
        $base = rtrim((string) config('quote.base_url'), '/');
        $form ??= (string) config('quote.default_form');

        return "{$base}/{$form}/{$id}".($download ? '?download=1' : '');
    }

    public static function length(): int
    {
        $length = (int) config('quote.signature_length', 64);

        return max(16, min(64, $length));
    }
}
