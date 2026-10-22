<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * สร้าง/ตรวจสอบ signature ของลิงก์ใบเสนอราคา
 *
 * signature = HMAC-SHA256(id, QUOTE_LINK_SECRET) แบบ hex ตัวพิมพ์เล็ก
 * ตัดความยาวตาม config quote.signature_length (ค่าเริ่มต้น 64 = เต็ม)
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
     * สร้าง URL เต็มของเอกสาร เช่น https://host/quote/1234.abcd...
     */
    public static function url(string $id, bool $download = false): string
    {
        $base = rtrim((string) config('quote.base_url'), '/');

        $url = "{$base}/quote/{$id}.".self::sign($id);

        return $download ? $url.'?download=1' : $url;
    }

    /**
     * URL แบบสั้น (ไม่มี signature) เช่น https://pdf.trackingmouthefirst.com/6274719000184443042
     * ใช้ได้เมื่อ QUOTE_ALLOW_UNSIGNED=true
     */
    public static function shortUrl(string $id, bool $download = false): string
    {
        $base = rtrim((string) config('quote.base_url'), '/');

        return "{$base}/{$id}".($download ? '?download=1' : '');
    }

    public static function length(): int
    {
        $length = (int) config('quote.signature_length', 64);

        return max(16, min(64, $length));
    }
}
