{{-- ส่วนหัวเอกสาร HTML + CSS ที่ใช้ร่วมกันทุกฟอร์มตระกูล FA-SA (ตัวแปรมาจาก FormLayout::vars) --}}
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>{{ $q->number() }}</title>
<style>
    /* ห้ามใส่กฎ at-page ที่นี่: mPDF จะล้าง page header (หัวกระดาษซ้ำทุกหน้า) เมื่อเจอ — ขนาดกระดาษตั้งใน QuotePdfService แล้ว */
    @php
        $fs = (float) ($doc['font_size_pt'] ?? 16);    // ขนาดตัวอักษรทั่วไป
        $fh = (float) ($doc['heading_size_pt'] ?? 18); // หัวข้อ
        $fsm = round($fs * 0.875, 1);                  // หมายเหตุย่อย (เช่น Recurring Amount)
    @endphp
    body {
        font-family: '{{ $doc['font'] }}', sans-serif;
        font-size: {{ $fs }}pt;
        color: #000;
        line-height: 1.15;
    }
    .lang_th, :lang(th), :lang(tha) { font-family: '{{ $doc['font'] }}'; }

    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: middle; padding: 0 3pt; }
    .b { font-weight: bold; }
    .bi { font-weight: bold; font-style: italic; }
    .c { text-align: center; }
    .r { text-align: right; }
    .l { text-align: left; }
    .top { vertical-align: top; }
    .nopad { padding: 0; }
    .nowrap { white-space: nowrap; }

    /* rich text จาก CRM: ไม่ให้ p/ol มี margin ใหญ่เกินฟอร์ม */
    .rich p, .rich div { margin: 0; padding: 0; }
    .rich ol, .rich ul { margin: 0 0 0 14pt; padding: 0; }
    .rich li { margin: 0; }

    /* เส้นตารางบาง 0.3pt (ต้นแบบ Excel "thin") */
    .bd  { border: 0.3pt solid #000; }
    .bl  { border-left: 0.3pt solid #000; }
    .br  { border-right: 0.3pt solid #000; }
    .bt  { border-top: 0.3pt solid #000; }
    .bb  { border-bottom: 0.3pt solid #000; }
    .bb-thick { border-bottom: 1.1pt solid #000; }

    .fill-pink { background: #E6B8B7; }
    .fill-subject { background: {{ $doc['subject_fill'] ?? '#E6B8B7' }}; }
    .fill-grey { background: #D9D9D9; }

    /* อักษรจีนที่ mPDF ตรวจพบ (autoScriptToLang → <span lang="und-Hans" class="lang_und-Hans">) ใช้ฟอนต์ CJK
       และย่อขนาดลง (cjk_scale) เพราะ SimSun ที่ pt เท่ากันสูงกว่าตัวไทยของ TH Sarabun New เกือบ 2 เท่า */
    @if(!empty($doc['cjk_font_available']))
    .lang_und-Hans, .lang_und-Hant, .lang_zh, :lang(und-Hans), :lang(und-Hant), :lang(zh) {
        font-family: '{{ $doc['cjk_font_name'] }}';
        font-size: {{ (int) round(($doc['cjk_scale'] ?? 0.75) * 100) }}%;
    }
    @endif

    /* ขนาดตัวอักษร: ทั่วไป = font_size_pt (16), หัวข้อ = heading_size_pt (18), หมายเหตุย่อย = 87.5% */
    .f24 { font-size: {{ $fh }}pt; }
    .f22 { font-size: {{ $fs }}pt; }
    .f18 { font-size: {{ $fs }}pt; }
    .f16 { font-size: {{ $fs }}pt; }
    .f15 { font-size: {{ $fs }}pt; }
    .fh  { font-size: {{ $fh }}pt; }
    .fsm { font-size: {{ $fsm }}pt; }

    /* ส่วนปิดท้าย (ข้อความ + กล่องลงนาม) ตรึงไว้ล่างสุดของหน้า */
    .closing-pinned { position: absolute; left: {{ $doc['margin_left_mm'] ?? 6 }}mm; bottom: {{ ($doc['margin_bottom_mm'] ?? 30) + 1 }}mm; width: {{ 210 - ($doc['margin_left_mm'] ?? 6) - ($doc['margin_right_mm'] ?? 4) }}mm; }

    /* ข้อความในกล่องลงนามชิดล่างเสมอ ให้ชื่อ/ตำแหน่งของทั้ง 3 กล่องอยู่บรรทัดเดียวกัน ไม่ว่ากล่องนั้นจะมีรูปลายเซ็นหรือไม่ */
    .sig-box { border: 0.3pt solid #000; height: 88pt; text-align: center; vertical-align: bottom; padding-bottom: 5pt; }

    /* ตารางข้อมูลลูกค้า: เว้นระยะในช่องให้ข้อความไม่ชิดเส้น */
    table.info td.bd { padding: 3pt 7pt; }
    /* ช่องป้าย (ชิดขวา) แคบเท่าคอลัมน์ ลำดับที่ → ลด padding ข้างให้ "ผู้ติดต่อ :" 16pt อยู่บรรทัดเดียว */
    table.info td.r { padding: 3pt 2pt; }
    /* ป้ายขวากว้างเท่าคอลัมน์ ราคา (31mm): "ฝ่ายขายผู้เสนอราคา :" ตัวหนา 16pt กว้าง 33mm จึงใช้ 14pt (29mm) */
    table.info td.lr { font-size: {{ min($fs, 14) }}pt; padding: 3pt 1pt; }

    /* กล่องสรุปยอดท้ายตาราง: ไทย | อังกฤษ | จำนวนเงิน — ความกว้างคอลัมน์ = คอลัมน์ ราคา/จำนวน/จำนวนเงิน ของตาราง */
    table.totals td { height: 22pt; padding: 0 3pt; font-size: {{ $fs }}pt; white-space: nowrap; }
    table.totals td.t1 { width: {{ $tT1 }}%; white-space: nowrap; }
    table.totals td.t2 { width: {{ $tT2 }}%; padding: 0 1pt; }
    table.totals td.t3 { width: {{ $tT3 }}%; }

    /* แถบชื่อส่วน (เช่น QT Destination) เหนือตารางรายการชุดที่ 2 */
    .section-title { background: #D9D9D9; font-weight: bold; text-align: center; height: 24pt; }

    .sig-line { text-decoration: underline; }
    .pre { white-space: pre-wrap; }
</style>
</head>
<body>
