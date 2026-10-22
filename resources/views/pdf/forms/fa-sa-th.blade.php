@php
    /**
     * Template ใบเสนอราคา — จำลอง sheet "คนเมียวดี (เขียว-แดง)" ของ FA-SA.xlsx
     * สัดส่วนคอลัมน์/ความสูงแถว/ขนาดฟอนต์ ย่อจาก Excel (zoom 67%) ให้พอดี A4
     *
     * @var \App\Support\QuoteData $q
     */
    use App\Support\QuoteData as F;

    $items = $q->items();
    $minRows = (int) ($doc['min_item_rows'] ?? 3);
    $blankRows = max(0, $minRows - count($items));

    $headerImg = $company['header_image'] ?? null;
    $footerImg = $company['footer_image'] ?? null;
    $hasHeader = $headerImg && is_file($headerImg);
    $hasFooter = $footerImg && is_file($footerImg);

    // สัดส่วนคอลัมน์ Excel (A..L = 157.8 หน่วย) → เปอร์เซ็นต์ของความกว้างกระดาษ
    $wA = 1.45; $wB = 7.97; $wCH = 57.30; $wI = 12.58; $wJ = 7.79; $wK = 12.58; $wL = 1.63;
@endphp
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>{{ $q->number() }}</title>
<style>
    @page { sheet-size: A4; }
    body {
        font-family: '{{ $doc['font'] }}', sans-serif;
        font-size: 11.7pt;        /* Angsana New 18pt × 0.65 */
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

    .bd  { border: 0.5pt solid #000; }
    .bl  { border-left: 0.5pt solid #000; }
    .br  { border-right: 0.5pt solid #000; }
    .bt  { border-top: 0.5pt solid #000; }
    .bb  { border-bottom: 0.5pt solid #000; }
    .bb-thick { border-bottom: 1.5pt solid #000; }

    .fill-pink { background: #E6B8B7; }
    .fill-grey { background: #D9D9D9; }

    .f24 { font-size: 15.6pt; }   /* 24pt × 0.65 */
    .f22 { font-size: 14.3pt; }   /* 22pt × 0.65 */
    .f18 { font-size: 11.7pt; }   /* 18pt × 0.65 */
    .f16 { font-size: 10.4pt; }   /* 16pt × 0.65 */
    .f15 { font-size: 9.75pt; }   /* 15pt × 0.65 */

    /* กรอบนอกทั้งฟอร์ม (A1:L23) — mPDF ไม่รองรับ child selector จึงใช้ class ตรง ๆ */
    .frame-cell { border: 0.5pt solid #000; padding: 0; }

    .sig-box { border: 0.5pt solid #000; height: 64pt; text-align: center; vertical-align: middle; }
    .sig-line { text-decoration: underline; }
    .pre { white-space: pre-wrap; }
</style>
</head>
<body>

<table class="frame">
<tr>
<td class="frame-cell">

    {{-- ============ แถว 1-2 : หัวกระดาษ (Picture 4 ที่ A1:K2) ============ --}}
    <table>
        <tr>
            <td class="nopad" style="width: 1%; height: 72pt;"></td>
            <td class="nopad l" style="width: 99%; height: 72pt; vertical-align: middle;">
                @if($hasHeader)
                    <img src="{{ $headerImg }}" style="width: 177mm;">
                @else
                    <div class="b f24">{{ $company['name_th'] }}</div>
                    <div class="f16">{{ $company['name_en'] }} &nbsp; เลขประจำตัวผู้เสียภาษี {{ $company['tax_id'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ============ แถว 3-6 : ข้อมูลลูกค้า / เลขที่ / วันที่ / ผู้เสนอ ============ --}}
    <table>
        <tr style="height: 24.4pt;">
            <td class="nopad" style="width: {{ $wA }}%;"></td>
            <td class="bd b l" style="width: {{ $wB }}%;">เรียน</td>
            <td class="bd b l" style="width: {{ $wCH }}%;">{{ $q->customerName() }}</td>
            <td class="bd b l" style="width: {{ $wI }}%;">ใบเสนอราคาเลขที่ :</td>
            <td class="bd b c" style="width: {{ $wJ + $wK }}%;">{{ $q->number() }}</td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>
        <tr style="height: 28.8pt;">
            <td class="nopad"></td>
            <td class="bd b l top" style="padding-top: 4pt;">ที่อยู่</td>
            <td class="bd b l" rowspan="2">
                {{ $q->billingLine1() }}<br>
                {{ $q->billingLine2() }}
            </td>
            <td class="bd b l">วันที่ :</td>
            {{-- วันที่ dd/mm/yyyy แสดงเฉพาะเมื่อ Quote Stage = อนุมัติ --}}
            <td class="bd b c">{{ $q->isApproved() ? F::dateFormat($q->quoteDate()) : '' }}&nbsp;</td>
            <td class="nopad"></td>
        </tr>
        <tr style="height: 24.4pt;">
            <td class="nopad"></td>
            <td class="bd"></td>
            <td class="bd b l">ผู้เสนอราคา :</td>
            <td class="bd b c">{{ $q->salesName() }}</td>
            <td class="nopad"></td>
        </tr>
        <tr style="height: 24.4pt;">
            <td class="nopad"></td>
            <td class="bd b l">ติดต่อ</td>
            <td class="bd b l">{{ $q->contactName() }}</td>
            <td class="bd b l">โทรศัพท์ :</td>
            <td class="bd b c">{{ $q->contactMobile() }}</td>
            <td class="nopad"></td>
        </tr>
    </table>

    {{-- ============ แถว 7 : หัวข้อการให้บริการ (พื้นชมพู) / แถว 8 : ข้อความนำ ============ --}}
    <table>
        <tr style="height: 53pt;">
            <td class="nopad" style="width: {{ $wA }}%;"></td>
            <td class="bd b c f24 fill-pink" style="width: {{ 100 - $wA - $wL }}%; height: 53pt;">{{ $q->serviceSubject() !== '' ? $q->serviceSubject() : ' ' }}&nbsp;</td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>
        <tr style="height: 19pt;">
            <td class="nopad"></td>
            <td class="bd c f16">{{ $texts['intro'] }}</td>
            <td class="nopad"></td>
        </tr>
    </table>

    {{-- ============ แถว 9-14 : ตารางรายการ ============ --}}
    <table>
        <tr style="height: 38pt;">
            <td class="nopad" style="width: {{ $wA }}%;"></td>
            <td class="bd b c f15 fill-grey" style="width: {{ $wB }}%;">ลำดับที่<br>ITEM</td>
            <td class="bd b c f15 fill-grey" style="width: {{ $wCH }}%;">รายการ<br>DESCRIPTION</td>
            <td class="bd b c f15 fill-grey" style="width: {{ $wI }}%;">ราคา<br>Price</td>
            <td class="bd b c f15 fill-grey" style="width: {{ $wJ }}%;">จำนวน<br>Quantity</td>
            <td class="bd b c f15 fill-grey" style="width: {{ $wK }}%;">จำนวนเงิน<br>Amount</td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>

        @foreach($items as $item)
            <tr style="height: 25.4pt;">
                <td class="nopad"></td>
                <td class="bd c f22">{{ $item['no'] }}</td>
                <td class="bd l f22">
                    {{ $item['name'] }}
                    @if($item['description'])<br><span class="f16">{!! nl2br(e($item['description'])) !!}</span>@endif
                    @if($item['recurring_amount'] !== null && $item['recurring_amount'] > 0)
                        <br><span class="f16">ราคาค่าใช้งานปีถัดไป (Recurring Amount {{ F::money($item['recurring_amount']) }})</span>
                    @endif
                </td>
                <td class="bd r f22">{{ F::money($item['list_price'], false) }}</td>
                <td class="bd c f22">{{ F::qty($item['quantity']) }}@if($item['unit']) <span class="f16">{{ $item['unit'] }}</span>@endif</td>
                <td class="bd r f22">{{ F::money($item['total'], false) }}</td>
                <td class="nopad"></td>
            </tr>
        @endforeach

        @for($i = 0; $i < $blankRows; $i++)
            <tr style="height: 25.4pt;">
                <td class="nopad"></td>
                <td class="bd">&nbsp;</td>
                <td class="bd"></td>
                <td class="bd"></td>
                <td class="bd"></td>
                <td class="bd"></td>
                <td class="nopad"></td>
            </tr>
        @endfor

        {{-- แถว 14 : ตัวอักษร + ยอดรวม (เส้นล่างหนา) --}}
        <tr style="height: 25.4pt;">
            <td class="nopad"></td>
            <td class="bd bb-thick c bi f22" colspan="2">{{ $q->grandTotalText() }}</td>
            <td class="bd bb-thick c bi f22 fill-grey" colspan="2">ยอดรวมทั้งหมด</td>
            <td class="bd bb-thick r bi f22 fill-grey">{{ F::money($q->grandTotal(), false) }}</td>
            <td class="nopad"></td>
        </tr>
    </table>

    {{-- ============ แถว 15 : เว้น ============ --}}
    <div style="height: 9pt;"></div>

    {{-- ============ แถว 16 : เงื่อนไข และรายละเอียดการชำระเงิน ============ --}}
    <table>
        <tr>
            <td class="l top f18 pre" style="padding: 0 4pt; min-height: 13pt;">{!! nl2br(e($q->paymentTerms())) !!}</td>
        </tr>
    </table>

    {{-- ============ แถว 17-20 : ข้อความปิดท้าย (กึ่งกลาง) ============ --}}
    <table>
        @foreach($texts['closing'] as $line)
            <tr style="height: 21pt;">
                <td class="c f18">{{ $line }}</td>
            </tr>
        @endforeach
    </table>

    {{-- ============ แถว 21-22 : การยืนยันการใช้บริการ ============ --}}
    <table>
        <tr style="height: 21pt;">
            <td class="nopad" style="width: {{ $wA }}%;"></td>
            <td class="l b f18">{{ $texts['confirm_title'] }}</td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>
        <tr style="height: 21pt;">
            <td class="nopad"></td>
            <td class="l top f18">{{ $texts['confirm_body'] }}</td>
            <td class="nopad"></td>
        </tr>
    </table>

    {{-- ============ แถว 23 : กล่องลงนาม 3 กล่อง (Rectangle 8 / 4 / 3) ============ --}}
    <table style="page-break-inside: avoid; margin-top: 4pt; margin-bottom: 10pt;">
        <tr style="height: 76pt;">
            <td class="nopad" style="width: 3.6%;"></td>
            <td class="sig-box f16" style="width: 26.2%;">
                <div>(<span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>)</div>
                <div class="b">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</div>
                <div class="b">{{ $texts['sign_customer'] }}</div>
            </td>
            <td class="nopad" style="width: 2.9%;"></td>
            <td class="sig-box f16" style="width: 32.5%;">
                <div>(<span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>)</div>
                <div class="b">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</div>
                <div class="b">{{ $texts['sign_sales'] }}</div>
            </td>
            <td class="nopad" style="width: 3.8%;"></td>
            <td class="sig-box f16" style="width: 22.9%;">
                @php $sigImg = $approver['signature'] ?? null; @endphp
                @if($sigImg && is_file($sigImg))
                    {{-- ลายเซ็นผู้อนุมัติ วางเหนือเส้นลงนาม --}}
                    <div style="margin-bottom: -6pt;"><img src="{{ $sigImg }}" style="height: {{ $approver['signature_height_pt'] ?? 34 }}pt;"></div>
                @endif
                <div>(<span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>)</div>
                <div class="b">( {{ $approver['name'] }} )</div>
                <div class="b">{{ $approver['title'] }}</div>
            </td>
            <td class="nopad" style="width: 8.1%;"></td>
        </tr>
    </table>

</td>
</tr>
</table>

{{-- ============ แถว 25-28 : ท้ายกระดาษ (รูปภาพ 3) + รหัสแบบฟอร์ม ${Quotes.ISO}
     ตรึงไว้ล่างสุดของทุกหน้าด้วย htmlpagefooter (พื้นที่จองโดย margin_bottom ใน QuotePdfService) ============ --}}
<htmlpagefooter name="formFooter">
    <table>
        <tr>
            <td class="nopad c">
                @if($hasFooter)
                    <img src="{{ $footerImg }}" style="width: 194mm;">
                @endif
            </td>
        </tr>
    </table>
    <table>
        <tr>
            <td class="r f18" style="width: {{ 100 - $wL }}%; padding-top: 2pt;">{{ $q->isoCode() }}</td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>
    </table>
</htmlpagefooter>
<sethtmlpagefooter name="formFooter" value="on" />

@if($trackingUrl)
    <div style="position: absolute; right: 6mm; bottom: 4mm;">
        <barcode code="{{ $trackingUrl }}" type="QR" size="0.45" error="M" disableborder="1" />
    </div>
@endif

</body>
</html>
