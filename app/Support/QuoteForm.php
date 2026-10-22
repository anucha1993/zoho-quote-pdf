<?php

namespace App\Support;

use Illuminate\Support\Facades\View;

/**
 * แบบฟอร์ม PDF 1 แบบ เช่น "FA-SA-TH"
 *
 * วิธีเพิ่มฟอร์มใหม่:
 *   1. สร้าง resources/views/pdf/forms/<slug ตัวพิมพ์เล็ก>.blade.php  (เช่น fa-sa-cn.blade.php)
 *   2. (ไม่บังคับ) เพิ่ม override ใน config/quote.php → 'forms' => ['FA-SA-CN' => [...]]
 *   แค่นี้ URL /FA-SA-CN/{id} ก็ใช้ได้ทันที
 */
class QuoteForm
{
    public function __construct(
        public readonly string $slug,
        public readonly string $view,
        public readonly array $options = [],
    ) {}

    /**
     * หาแบบฟอร์มจาก slug ใน URL — คืน null ถ้าไม่มี (controller จะตอบ 404)
     */
    public static function find(?string $slug): ?self
    {
        $slug = trim((string) ($slug ?: config('quote.default_form')));

        if ($slug === '' || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,40}$/', $slug)) {
            return null;
        }

        $forms = config('quote.forms', []);

        // จับคู่แบบไม่สนตัวพิมพ์ (FA-SA-TH = fa-sa-th)
        $key = collect(array_keys($forms))->first(fn ($k) => strcasecmp($k, $slug) === 0);
        $options = $key !== null ? ($forms[$key] ?? []) : [];
        $canonical = $key ?? strtoupper($slug);

        $view = $options['view'] ?? 'pdf.forms.'.strtolower($slug);

        if (! View::exists($view)) {
            return null;
        }

        return new self($canonical, $view, $options);
    }

    public static function default(): self
    {
        return self::find(config('quote.default_form'))
            ?? throw new \RuntimeException('Default quote form is not configured');
    }

    /** slug พิเศษใน URL ที่ให้ระบบเลือกฟอร์มเองจากข้อมูล Quote */
    public const AUTO = 'auto';

    public static function isAuto(?string $slug): bool
    {
        return strcasecmp(trim((string) $slug), self::AUTO) === 0;
    }

    /**
     * เลือกฟอร์มจากข้อมูล Quote ตาม config quote.auto_form (field → map) — ไม่ตรงใช้ฟอร์มเริ่มต้น
     */
    public static function resolveAuto(QuoteData $quote): self
    {
        $field = (string) config('quote.auto_form.field', 'ISO');
        $value = $quote->quote[$field] ?? null;
        $value = is_array($value) ? ($value['name'] ?? '') : trim((string) $value);

        if ($value !== '') {
            foreach ((array) config('quote.auto_form.map', []) as $pattern => $slug) {
                $regex = '/^'.str_replace('\*', '.*', preg_quote((string) $pattern, '/')).'$/i';
                if (preg_match($regex, $value) && ($form = self::find($slug))) {
                    return $form;
                }
            }
        }

        return self::default();
    }

    /**
     * รายการฟอร์มทั้งหมดที่ใช้ได้ = config forms + ไฟล์ใน resources/views/pdf/forms/
     *
     * @return self[]
     */
    public static function all(): array
    {
        $slugs = array_keys(config('quote.forms', []));

        foreach (glob(resource_path('views/pdf/forms/*.blade.php')) ?: [] as $file) {
            $slugs[] = strtoupper(basename($file, '.blade.php'));
        }

        $forms = [];
        foreach (array_unique(array_map('strtoupper', $slugs)) as $slug) {
            if ($form = self::find($slug)) {
                $forms[$form->slug] = $form;
            }
        }

        ksort($forms);

        return array_values($forms);
    }

    public function label(): string
    {
        return (string) $this->option('label', $this->slug);
    }

    /**
     * path ของไฟล์ Blade (ใช้คำนวณเวอร์ชัน template สำหรับ cache)
     */
    public function viewPath(): ?string
    {
        try {
            return View::getFinder()->find($this->view);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * ค่า option ของฟอร์ม (ถ้าไม่กำหนดใช้ค่าจาก config quote.* เดิม)
     */
    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
