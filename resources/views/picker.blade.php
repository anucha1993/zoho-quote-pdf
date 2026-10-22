<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>เลือกแบบฟอร์ม {{ $quote->number() }}</title>
<style>
    :root { --bg: #f4f5f7; --card: #fff; --line: #d9dce1; --text: #1f2328; --muted: #6b7280; --accent: #1a4d8f; --accent-soft: #e8eef8; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Segoe UI", "Sarabun", Tahoma, sans-serif; background: var(--bg); color: var(--text); }
    .wrap { max-width: 640px; margin: 0 auto; padding: 24px 16px 48px; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    .meta { color: var(--muted); font-size: 14px; margin-bottom: 20px; }
    .meta b { color: var(--text); }
    .form { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px; margin-bottom: 12px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .form.suggested { border-color: var(--accent); background: var(--accent-soft); }
    .form .info { flex: 1 1 240px; }
    .form .slug { font-weight: 700; font-size: 16px; }
    .form .label { color: var(--muted); font-size: 14px; }
    .form .tag { display: inline-block; font-size: 12px; color: var(--accent); border: 1px solid var(--accent); border-radius: 999px; padding: 1px 8px; margin-left: 6px; vertical-align: middle; }
    .actions { display: flex; gap: 8px; }
    a.btn { text-decoration: none; padding: 8px 14px; border-radius: 8px; font-size: 14px; border: 1px solid var(--accent); color: var(--accent); background: #fff; white-space: nowrap; }
    a.btn.primary { background: var(--accent); color: #fff; }
    a.btn:hover { filter: brightness(0.95); }
    .hint { color: var(--muted); font-size: 13px; margin-top: 20px; }
</style>
</head>
<body>
<div class="wrap">
    <h1>เลือกแบบฟอร์ม PDF</h1>
    <div class="meta">
        ใบเสนอราคา <b>{{ $quote->number() }}</b>
        @if($quote->customerName()) · {{ $quote->customerName() }}@endif
        @if($quote->isoCode()) · รหัสฟอร์มใน CRM <b>{{ $quote->isoCode() }}</b>@endif
    </div>

    @foreach($forms as $form)
        <div class="form {{ $form->slug === $suggested->slug ? 'suggested' : '' }}">
            <div class="info">
                <div class="slug">{{ $form->slug }}@if($form->slug === $suggested->slug)<span class="tag">แนะนำ</span>@endif</div>
                <div class="label">{{ $form->label() }}</div>
            </div>
            <div class="actions">
                <a class="btn primary" href="{{ $urlFor($form) }}" target="_blank" rel="noopener">เปิด PDF</a>
                <a class="btn" href="{{ $urlFor($form, true) }}">ดาวน์โหลด</a>
            </div>
        </div>
    @endforeach

    <p class="hint">ฟอร์มที่ "แนะนำ" เลือกจากค่าในฟิลด์ {{ config('quote.auto_form.field', 'ISO') }} ของ Quote ตามตารางใน config/quote.php</p>
</div>
</body>
</html>
