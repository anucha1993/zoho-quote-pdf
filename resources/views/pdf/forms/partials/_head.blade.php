{{-- ส่วนหัวเอกสาร HTML + CSS ที่ใช้ร่วมกันทุกฟอร์มตระกูล FA-SA (ตัวแปรมาจาก FormLayout::vars) --}}
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>{{ $q->number() }}</title>
<style>
    /* ห้ามใส่กฎ at-page ที่นี่: mPDF จะล้าง page header (หัวกระดาษซ้ำทุกหน้า) เมื่อเจอ — ขนาดกระดาษตั้งใน QuotePdfService แล้ว */
    body {
        font-family: '{{ $doc['font'] }}', sans-serif;
        font-size: 13pt;
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
    .fill-grey { background: #D9D9D9; }

    /* ขนาดตัวอักษร 3 ระดับให้สม่ำเสมอ: 13pt ทั่วไป, 14pt รายการสินค้า, 16pt หัวข้อบริการ (ป้ายรอง 11pt) */
    .f24 { font-size: 16pt; }
    .f22 { font-size: 14pt; }
    .f18 { font-size: 13pt; }
    .f16 { font-size: 11pt; }
    .f15 { font-size: 11pt; }

    /* ส่วนปิดท้าย (ข้อความ + กล่องลงนาม) ตรึงไว้ล่างสุดของหน้า */
    .closing-pinned { position: absolute; left: {{ $doc['margin_left_mm'] ?? 6 }}mm; bottom: {{ ($doc['margin_bottom_mm'] ?? 30) + 1 }}mm; width: {{ 210 - ($doc['margin_left_mm'] ?? 6) - ($doc['margin_right_mm'] ?? 4) }}mm; }

    .sig-box { border: 0.3pt solid #000; height: 70pt; text-align: center; vertical-align: middle; }

    /* ตารางข้อมูลลูกค้า: เว้นระยะในช่องให้ข้อความไม่ชิดเส้น */
    table.info td.bd { padding: 3pt 7pt; }

    /* กล่องสรุปยอดท้ายตาราง: ไทย | อังกฤษ | จำนวนเงิน — ความกว้างคอลัมน์ = คอลัมน์ ราคา/จำนวน/จำนวนเงิน ของตาราง */
    table.totals td { height: 21pt; padding: 0 4pt; font-size: 13pt; white-space: nowrap; }
    table.totals td.t1 { width: {{ round($tI / $tSum * 100, 2) }}%; }
    table.totals td.t2 { width: {{ round($tJ / $tSum * 100, 2) }}%; padding: 0 2pt; }
    table.totals td.t3 { width: {{ round($tK / $tSum * 100, 2) }}%; }

    /* แถบชื่อส่วน (เช่น QT Destination) เหนือตารางรายการชุดที่ 2 */
    .section-title { background: #D9D9D9; font-weight: bold; text-align: center; height: 24pt; }

    .sig-line { text-decoration: underline; }
    .pre { white-space: pre-wrap; }
</style>
</head>
<body>
