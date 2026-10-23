<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * ตัดขอบขาวซ้าย/ขวาของภาพหัวกระดาษ/ท้ายกระดาษอัตโนมัติ
 *
 * ภาพที่ฝ่ายออกแบบส่งมามีขอบขาวในตัวไฟล์ไม่เท่ากัน (header ~1% / footer ~7%) ทำให้เมื่อ render
 * กว้าง 200mm เท่ากัน เนื้อหากลับยาวไม่เท่ากัน → ตัดให้เหลือเฉพาะเนื้อหา + ขอบเล็กน้อยเท่ากันทุกภาพ
 * ความสูงไม่ตัด (เผื่อการจัดวางแนวตั้งที่ออกแบบไว้) ผลลัพธ์ cache ที่ storage/app/images/ ตามชื่อ+mtime+size
 */
class ImageTrim
{
    /** ความเข้มที่ถือว่า "มีเนื้อหา" (0-255, ขาวสนิท = 255) */
    public const THRESHOLD = 235;

    /**
     * @param  string  $path  ไฟล์ต้นฉบับ (jpg/png)
     * @param  float  $pad  ขอบที่เว้นไว้ข้างละ (สัดส่วนของความกว้างเนื้อหา) เช่น 0.013 = 1.3%
     * @return string path ของภาพที่ตัดแล้ว หรือ path เดิมถ้าตัดไม่ได้/ไม่จำเป็น
     */
    public static function sides(string $path, float $pad = 0.013): string
    {
        if (! is_file($path) || ! function_exists('imagecreatefromstring')) {
            return $path;
        }

        $dir = storage_path('app/images');
        $key = substr(sha1($path.'|'.filemtime($path).'|'.filesize($path).'|'.$pad), 0, 10);
        $out = $dir.'/'.pathinfo($path, PATHINFO_FILENAME)."-trim-{$key}.jpg";

        if (is_file($out)) {
            return $out;
        }

        try {
            $src = @imagecreatefromstring((string) file_get_contents($path));
            if ($src === false) {
                return $path;
            }

            [$x0, $x1] = self::contentColumns($src);
            $w = imagesx($src);
            $h = imagesy($src);

            if ($x0 === null || $x1 <= $x0) {
                imagedestroy($src);

                return $path;
            }

            $contentW = $x1 - $x0 + 1;
            $margin = (int) round($contentW * $pad);
            $left = max(0, $x0 - $margin);
            $right = min($w - 1, $x1 + $margin);

            // ขอบขาวน้อยกว่าที่ต้องการอยู่แล้ว → ใช้ภาพเดิม ไม่ต้องสร้างไฟล์ใหม่
            if ($left === 0 && $right === $w - 1) {
                imagedestroy($src);

                return $path;
            }

            $dst = imagecreatetruecolor($right - $left + 1, $h);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);
            imagecopy($dst, $src, 0, 0, $left, 0, $right - $left + 1, $h);

            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            imagejpeg($dst, $out, 95);
            imagedestroy($src);
            imagedestroy($dst);

            return is_file($out) ? $out : $path;
        } catch (\Throwable $e) {
            Log::warning('ImageTrim failed: '.$e->getMessage(), ['path' => $path]);

            return $path;
        }
    }

    /**
     * ย่อ/ขยายรูป (PNG โปร่งใส) ให้สูง $heightPt เมื่อวาดที่ $dpi — ใช้กับรูปลายเซ็นที่วาดเป็น background ของช่องตาราง
     * (mPDF ไม่ใช้ background-size กับ td แต่วาดตามขนาดพิกเซลจริง ÷ img_dpi) ผลลัพธ์ cache ที่ storage/app/images/
     *
     * @return string path ของรูปที่ปรับขนาดแล้ว หรือ path เดิมถ้าทำไม่ได้
     */
    public static function scaledToHeight(string $path, float $heightPt, int $dpi = 300): string
    {
        if (! is_file($path) || ! function_exists('imagecreatefromstring')) {
            return $path;
        }

        $targetH = max(1, (int) round($heightPt / 72 * $dpi));
        $dir = storage_path('app/images');
        $key = substr(sha1($path.'|'.filemtime($path).'|'.filesize($path).'|'.$targetH), 0, 10);
        $out = $dir.'/'.pathinfo($path, PATHINFO_FILENAME)."-h{$targetH}-{$key}.png";

        if (is_file($out)) {
            return $out;
        }

        try {
            $src = @imagecreatefromstring((string) file_get_contents($path));
            if ($src === false) {
                return $path;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $targetW = max(1, (int) round($w * $targetH / $h));

            $dst = imagecreatetruecolor($targetW, $targetH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $transparent);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $w, $h);

            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            imagepng($dst, $out, 6);
            imagedestroy($src);
            imagedestroy($dst);

            return is_file($out) ? $out : $path;
        } catch (\Throwable $e) {
            Log::warning('ImageTrim scale failed: '.$e->getMessage(), ['path' => $path]);

            return $path;
        }
    }

    /**
     * หาคอลัมน์แรก/สุดท้ายที่มีพิกเซลไม่ขาว (สุ่มตรวจทุก ๆ 2 แถวเพื่อความเร็ว)
     *
     * @return array{0: int|null, 1: int|null}
     */
    private static function contentColumns(\GdImage $img): array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $step = max(1, intdiv($h, 150));

        $first = null;
        $last = null;

        for ($x = 0; $x < $w; $x++) {
            if (self::columnHasContent($img, $x, $h, $step)) {
                $first = $x;
                break;
            }
        }

        if ($first === null) {
            return [null, null];
        }

        for ($x = $w - 1; $x >= $first; $x--) {
            if (self::columnHasContent($img, $x, $h, $step)) {
                $last = $x;
                break;
            }
        }

        return [$first, $last];
    }

    private static function columnHasContent(\GdImage $img, int $x, int $h, int $step): bool
    {
        for ($y = 0; $y < $h; $y += $step) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            if (min($r, $g, $b) < self::THRESHOLD) {
                return true;
            }
        }

        return false;
    }
}
