<?php

namespace App\Pdf;

use Mpdf\Language\LanguageToFont;

/**
 * บังคับให้ mPDF ใช้ฟอนต์ TH Sarabun New กับข้อความภาษาไทย
 *
 * ค่าเริ่มต้นของ mPDF เมื่อเปิด autoScriptToLang + autoLangToFont
 * จะจับคู่ภาษาไทย (th) กับฟอนต์ "garuda" ซึ่งไม่ได้ติดตั้งในระบบนี้
 */
class ThaiLanguageToFont extends LanguageToFont
{
    public function __construct(
        protected string $thaiFont = 'thsarabunnew',
    ) {}

    public function getLanguageOptions($llcc, $adobeCJK)
    {
        $lang = strtolower(explode('-', (string) $llcc)[0]);

        if (in_array($lang, ['th', 'tha'], true)) {
            // [coreSuitable, unifont]
            return [false, $this->thaiFont];
        }

        return parent::getLanguageOptions($llcc, $adobeCJK);
    }
}
