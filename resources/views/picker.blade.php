<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>พิมพ์ใบเสนอราคา {{ $quote->number() }}</title>
<style>
    :root { --bg: #eef0f3; --panel: #fff; --line: #d9dce1; --text: #1f2328; --muted: #6b7280; --accent: #1a4d8f; }
    * { box-sizing: border-box; }
    html, body { height: 100%; margin: 0; }
    body { font-family: "Segoe UI", "Sarabun", Tahoma, sans-serif; background: var(--bg); color: var(--text); display: flex; flex-direction: column; }
    header { background: var(--panel); border-bottom: 1px solid var(--line); padding: 10px 16px; display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
    header .title { font-weight: 700; font-size: 16px; }
    header .meta { color: var(--muted); font-size: 13px; }
    header .meta b { color: var(--text); }
    main { flex: 1; display: flex; min-height: 0; }
    aside { width: 300px; flex: 0 0 300px; background: var(--panel); border-right: 1px solid var(--line); padding: 16px; overflow: auto; }
    aside label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 6px; }
    select { width: 100%; font: inherit; font-size: 14px; padding: 8px 10px; border: 1px solid var(--accent); border-radius: 6px; background: #fff; }
    .desc { font-size: 13px; color: var(--muted); margin: 8px 0 16px; min-height: 18px; }
    .actions { display: flex; flex-direction: column; gap: 8px; }
    a.btn { text-decoration: none; text-align: center; padding: 9px 14px; border-radius: 6px; font-size: 14px; border: 1px solid var(--accent); color: var(--accent); background: #fff; }
    a.btn.primary { background: var(--accent); color: #fff; }
    a.btn:hover { filter: brightness(0.95); }
    .hint { font-size: 12px; color: var(--muted); margin-top: 20px; line-height: 1.5; }
    .tag { display: inline-block; font-size: 11px; color: var(--accent); border: 1px solid var(--accent); border-radius: 999px; padding: 0 7px; margin-left: 4px; vertical-align: middle; }
    section.preview { flex: 1; padding: 12px; min-width: 0; }
    iframe { width: 100%; height: 100%; border: 1px solid var(--line); background: #fff; }
    .loading { position: absolute; margin: 12px; color: var(--muted); font-size: 13px; }
    @media (max-width: 760px) {
        main { flex-direction: column; }
        aside { width: auto; flex: none; border-right: 0; border-bottom: 1px solid var(--line); }
        section.preview { height: 70vh; }
    }
</style>
</head>
<body>
<header>
    <span class="title">พิมพ์ใบเสนอราคา</span>
    <span class="meta">
        เลขที่ <b>{{ $quote->number() }}</b>
        @if($quote->customerName()) · {{ $quote->customerName() }}@endif
        @if($quote->isoCode()) · รหัสฟอร์มใน CRM <b>{{ $quote->isoCode() }}</b>@endif
    </span>
</header>
<main>
    <aside>
        <label for="form">เลือกแบบฟอร์ม (Mail Merge Template)</label>
        <select id="form">
            @foreach($forms as $form)
                <option value="{{ $form->slug }}"
                        data-label="{{ $form->label() }}"
                        data-view="{{ $urlFor($form) }}"
                        data-download="{{ $urlFor($form, true) }}"
                        @selected($form->slug === $suggested->slug)>
                    {{ $form->slug }}@if($form->slug === $suggested->slug) (แนะนำ)@endif
                </option>
            @endforeach
        </select>
        <div class="desc" id="desc"></div>

        <div class="actions">
            <a class="btn primary" id="open" href="#" target="_blank" rel="noopener">เปิด PDF ในแท็บใหม่</a>
            <a class="btn" id="download" href="#">ดาวน์โหลด PDF</a>
        </div>

        <p class="hint">
            ตัวอย่างด้านขวาเป็น PDF จริงจากข้อมูลล่าสุดใน CRM<br>
            ฟอร์มที่ "แนะนำ" เลือกจากฟิลด์ {{ config('quote.auto_form.field', 'ISO') }} ของ Quote
        </p>
    </aside>
    <section class="preview">
        <span class="loading" id="loading">กำลังสร้าง PDF…</span>
        <iframe id="preview" title="PDF preview"></iframe>
    </section>
</main>
<script>
    (function () {
        var sel = document.getElementById('form');
        var frame = document.getElementById('preview');
        var open = document.getElementById('open');
        var dl = document.getElementById('download');
        var desc = document.getElementById('desc');
        var loading = document.getElementById('loading');

        function apply() {
            var opt = sel.options[sel.selectedIndex];
            desc.textContent = opt.getAttribute('data-label') || '';
            open.href = opt.getAttribute('data-view');
            dl.href = opt.getAttribute('data-download');
            loading.style.display = '';
            // โหลด PDF ของฟอร์มที่เลือกใหม่ทันที: ใส่ค่าเวลาใน query กัน browser ใช้ไฟล์เดิมใน cache
            // (#toolbar=1 ให้ viewer ของเบราว์เซอร์แสดงปุ่มพิมพ์/บันทึก)
            var url = opt.getAttribute('data-view') + '?v=' + Date.now() + '#toolbar=1&view=FitH';
            frame.removeAttribute('src');
            setTimeout(function () { frame.src = url; }, 0);
        }

        frame.addEventListener('load', function () { loading.style.display = 'none'; });
        sel.addEventListener('change', apply);
        sel.addEventListener('input', apply);
        apply();
    })();
</script>
</body>
</html>
