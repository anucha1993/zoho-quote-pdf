<?php

namespace Tests\Unit;

use App\Support\BahtText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BahtTextTest extends TestCase
{
    public static function amounts(): array
    {
        return [
            'zero' => [0, 'ศูนย์บาทถ้วน'],
            'one' => [1, 'หนึ่งบาทถ้วน'],
            'ten' => [10, 'สิบบาทถ้วน'],
            'eleven uses et' => [11, 'สิบเอ็ดบาทถ้วน'],
            'twenty uses yi' => [20, 'ยี่สิบบาทถ้วน'],
            'twenty one' => [21, 'ยี่สิบเอ็ดบาทถ้วน'],
            'hundred' => [100, 'หนึ่งร้อยบาทถ้วน'],
            'hundred and one uses et' => [101, 'หนึ่งร้อยเอ็ดบาทถ้วน'],
            'hundred and ten' => [110, 'หนึ่งร้อยสิบบาทถ้วน'],
            'thousand' => [1000, 'หนึ่งพันบาทถ้วน'],
            'typical quote' => [54900, 'ห้าหมื่นสี่พันเก้าร้อยบาทถ้วน'],
            'with satang' => [54900.50, 'ห้าหมื่นสี่พันเก้าร้อยบาทห้าสิบสตางค์'],
            'satang only' => [0.25, 'ยี่สิบห้าสตางค์'],
            'one satang' => [0.01, 'หนึ่งสตางค์'],
            'eleven satang' => [0.11, 'สิบเอ็ดสตางค์'],
            'rounding 2.999' => [2.999, 'สามบาทถ้วน'],
            'one million' => [1_000_000, 'หนึ่งล้านบาทถ้วน'],
            'million with remainder' => [1_234_567.89, 'หนึ่งล้านสองแสนสามหมื่นสี่พันห้าร้อยหกสิบเจ็ดบาทแปดสิบเก้าสตางค์'],
            'million and one' => [1_000_001, 'หนึ่งล้านเอ็ดบาทถ้วน'],
            'two million million' => [2_000_000_000_000, 'สองล้านล้านบาทถ้วน'],
            'eleven million' => [11_000_000, 'สิบเอ็ดล้านบาทถ้วน'],
            'string input' => ['1250.75', 'หนึ่งพันสองร้อยห้าสิบบาทเจ็ดสิบห้าสตางค์'],
            'negative' => [-15, 'ลบสิบห้าบาทถ้วน'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_converts_amount_to_thai_text(float|int|string $amount, string $expected): void
    {
        $this->assertSame($expected, BahtText::convert($amount));
    }
}
