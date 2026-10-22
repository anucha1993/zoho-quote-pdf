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
    section.preview { flex: 1; padding: 12px; min-width: 0; position: relative; }
    iframe { width: 100%; height: 100%; border: 1px solid var(--line); background: #fff; }

    /* overlay ตอนกำลังสร้าง PDF ของฟอร์มที่เลือก: skeleton หน้ากระดาษ + spinner */
    .loading { position: absolute; inset: 12px; background: #f7f8fa; border: 1px solid var(--line); display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding-top: 48px; z-index: 2; }
    .loading[hidden] { display: none; }
    .spinner { width: 36px; height: 36px; border: 4px solid #d6dbe3; border-top-color: var(--accent); border-radius: 50%; animation: spin 0.9s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading .msg { margin-top: 14px; font-size: 15px; color: var(--text); font-weight: 600; }
    .loading .sub { margin-top: 4px; font-size: 13px; color: var(--muted); }
    .skeleton { width: min(560px, 90%); margin-top: 28px; background: #fff; border: 1px solid var(--line); padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .sk { height: 12px; border-radius: 4px; background: linear-gradient(90deg, #e9ecf1 25%, #f4f6f9 50%, #e9ecf1 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; margin-bottom: 12px; }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    .sk.h { height: 56px; margin-bottom: 20px; }
    .sk.w60 { width: 60%; } .sk.w80 { width: 80%; } .sk.w40 { width: 40%; }
    .sk.table { height: 120px; margin-top: 18px; }
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
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <div class="msg">กำลังสร้าง PDF <span id="loading-form"></span>…</div>
            <div class="sub">ดึงข้อมูลล่าสุดจาก Zoho CRM และจัดหน้าเอกสาร (ครั้งแรกอาจใช้เวลาสักครู่)</div>
            <div class="skeleton" aria-hidden="true">
                <div class="sk h"></div>
                <div class="sk w80"></div><div class="sk w60"></div><div class="sk w40"></div>
                <div class="sk table"></div>
                <div class="sk w60"></div><div class="sk w80"></div>
            </div>
        </div>
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
        var loadingForm = document.getElementById('loading-form');

        function apply() {
            var opt = sel.options[sel.selectedIndex];
            desc.textContent = opt.getAttribute('data-label') || '';
            open.href = opt.getAttribute('data-view');
            dl.href = opt.getAttribute('data-download');
            loadingForm.textContent = opt.value;
            loading.hidden = false;
            sel.disabled = true;
            // โหลด PDF ของฟอร์มที่เลือกใหม่ทันที: ใส่ค่าเวลาใน query กัน browser ใช้ไฟล์เดิมใน cache
            // (#toolbar=1 ให้ viewer ของเบราว์เซอร์แสดงปุ่มพิมพ์/บันทึก)
            var url = opt.getAttribute('data-view') + '?v=' + Date.now() + '#toolbar=1&view=FitH';
            frame.removeAttribute('src');
            setTimeout(function () { frame.src = url; }, 0);
        }

        function done() { loading.hidden = true; sel.disabled = false; }
        frame.addEventListener('load', done);
        // กันกรณี viewer ไม่ยิง load event (บางเบราว์เซอร์) → ซ่อน overlay เองหลัง 90 วินาที
        var guard = null;
        sel.addEventListener('change', function () { clearTimeout(guard); guard = setTimeout(done, 90000); });
        sel.addEventListener('change', apply);
        sel.addEventListener('input', apply);
        apply();
    })();
</script>
</body>
</html>
