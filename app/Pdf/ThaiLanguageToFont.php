<?php

namespace App\Pdf;

use Mpdf\Language\LanguageToFont;

/**
 * จับคู่ภาษา → ฟอนต์ ให้ mPDF (ใช้ร่วมกับ autoScriptToLang + autoLangToFont)
 *
 * - ภาษาไทย (th) → ฟอนต์ไทยที่เลือก (ค่าเริ่มต้นของ mPDF คือ "garuda" ซึ่งไม่มีในระบบ)
 * - ภาษาจีน/ญี่ปุ่น/เกาหลี และอักษรจีน (und-Hans / und-Hant) → ฟอนต์ CJK ที่เลือก (เช่น SimSun)
 *   ถ้าไม่ตั้งฟอนต์ CJK จะใช้ค่าเริ่มต้นของ mPDF
 */
class ThaiLanguageToFont extends LanguageToFont
{
    public function __construct(
        protected string $thaiFont = 'thsarabunnew',
        protected ?string $cjkFont = null,
    ) {}

    public function getLanguageOptions($llcc, $adobeCJK)
    {
        $tags = explode('-', strtolower((string) $llcc));
        $lang = $tags[0];
        $script = $tags[1] ?? '';

        if (in_array($lang, ['th', 'tha'], true)) {
            // [coreSuitable, unifont]
            return [false, $this->thaiFont];
        }

        if ($this->cjkFont !== null && (
            in_array($lang, ['zh', 'zho', 'ja', 'jpn', 'ko', 'kor'], true)
            || ($lang === 'und' && in_array($script, ['hans', 'hant', 'hani'], true))
        )) {
            return [false, $this->cjkFont];
        }

        return parent::getLanguageOptions($llcc, $adobeCJK);
    }
}
