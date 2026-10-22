@php
    /**
     * Template ใบเสนอราคา — จำลอง sheet "คนเมียวดี (เขียว-แดง)" ของ FA-SA.xlsx
     * สัดส่วนคอลัมน์/ความสูงแถว/ขนาดฟอนต์ ย่อจาก Excel (zoom 67%) ให้พอดี A4
     *
     * @var \App\Support\QuoteData $q
     */
    use App\Support\QuoteData as F;
    use App\Support\RichText;

    $items = $q->items();
    // จำนวนแถวขั้นต่ำของตาราง:
    //  - เมื่อเงื่อนไขขึ้นหน้า 2 (หน้า 1 มีแต่ตารางรายการ) → เติมแถวว่างให้เต็มหน้า (min_item_rows_full_page)
    //  - เมื่อทุกอย่างอยู่หน้าเดียว → ใช้ค่าเดิมตามต้นแบบ (min_item_rows) ไม่ให้ดันส่วนลงนามตกหน้า
    // แถวว่างในตารางถูกเติมโดย QuotePdfService (วัดพื้นที่จริง) — ที่นี่แค่บอกว่าเงื่อนไขจะขึ้นหน้าใหม่หรือไม่
    $hasTermsPage = ($doc['terms_on_new_page'] ?? true) && trim($q->paymentTerms()) !== '';

    $headerImg = $company['header_image'] ?? null;
    $footerImg = $company['footer_image'] ?? null;
    $hasHeader = $headerImg && is_file($headerImg);
    $hasFooter = $footerImg && is_file($footerImg);

    // สัดส่วนคอลัมน์ Excel (A..L = 157.8 หน่วย) → เปอร์เซ็นต์ของความกว้างกระดาษ
    // (คอลัมน์ I ขยายเล็กน้อยจาก 12.58 → 14.3 เพื่อให้ "ใบเสนอราคาเลขที่ :" อยู่บรรทัดเดียวหลังขยายฟอนต์)
    $wA = 1.45; $wB = 7.97; $wCH = 55.58; $wI = 14.30; $wJ = 7.79; $wK = 12.58; $wL = 1.63;
    // ตารางรายการใช้สัดส่วน ราคา/จำนวน/จำนวนเงิน ของตัวเอง (ผลรวมเท่า wI+wJ+wK): ขยายช่องจำนวนให้ "Total Discount" ในกล่องสรุปยอดอยู่บรรทัดเดียว
    // (ช่อง "จำนวน" ต้องกว้างพอให้ "Total Discount" ไม่ดันคอลัมน์ของกล่องสรุปยอดให้เบี้ยว)
    $tI = 15.50; $tJ = 11.50; $tK = 11.58;
    $tSum = $tI + $tJ + $tK;
    $tCH = round(100 - $wA - $wB - $tSum - $wL, 2);   // คอลัมน์รายการของตารางสินค้า (แคบลงเล็กน้อยจาก wCH)
@endphp
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>{{ $q->number() }}</title>
<style>
    /* ห้ามใส่กฎ at-page ที่นี่: mPDF จะล้าง page header (หัวกระดาษซ้ำทุกหน้า) เมื่อเจอ — ขนาดกระดาษตั้งใน QuotePdfService แล้ว */
    body {
        font-family: '{{ $doc['font'] }}', sans-serif;
        font-size: 13pt;          /* Angsana New 18pt × 0.65 × 1.12 (ขยายจากต้นแบบเล็กน้อยให้อ่านง่าย) */
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

    /* เส้นตารางบาง 0.3pt (ต้นแบบ Excel "thin") เส้นล่างแถวยอดรวมหนากว่าเล็กน้อย */
    .bd  { border: 0.3pt solid #000; }
    .bl  { border-left: 0.3pt solid #000; }
    .br  { border-right: 0.3pt solid #000; }
    .bt  { border-top: 0.3pt solid #000; }
    .bb  { border-bottom: 0.3pt solid #000; }
    .bb-thick { border-bottom: 1.1pt solid #000; }

    .fill-pink { background: #E6B8B7; }
    .fill-grey { background: #D9D9D9; }

    /* ขนาดตัวอักษรใช้ 3 ระดับให้สม่ำเสมอ: 13pt ทั่วไป, 14pt รายการสินค้า, 16pt หัวข้อบริการ (ป้ายรอง 11pt) */
    .f24 { font-size: 16pt; }     /* หัวข้อการให้บริการ */
    .f22 { font-size: 14pt; }     /* แถวรายการสินค้า */
    .f18 { font-size: 13pt; }     /* ข้อความทั่วไป / ป้ายช่อง / เงื่อนไข */
    .f16 { font-size: 11pt; }     /* ข้อความรอง / กล่องลงนาม */
    .f15 { font-size: 11pt; }     /* หัวตารางภาษาอังกฤษ */

    /* กรอบนอกทั้งฟอร์ม (A1:L23) ยืดลงถึงเหนือ footer — mPDF ไม่รองรับ child selector จึงใช้ class ตรง ๆ */
    .frame { border: 0.5pt solid #000; padding: 0; }

    /* ส่วนปิดท้าย (ข้อความ + กล่องลงนาม) ตรึงไว้ล่างสุดของกรอบ */
    .closing-pinned { position: absolute; left: {{ $doc['margin_left_mm'] ?? 6 }}mm; bottom: {{ ($doc['margin_bottom_mm'] ?? 30) + 1 }}mm; width: {{ 210 - ($doc['margin_left_mm'] ?? 6) - ($doc['margin_right_mm'] ?? 4) }}mm; }

    .sig-box { border: 0.3pt solid #000; height: 70pt; text-align: center; vertical-align: middle; }

    /* ตารางข้อมูลลูกค้า: เว้นระยะในช่องให้ข้อความไม่ชิดเส้น */
    table.info td.bd { padding: 3pt 7pt; }

    /* กล่องสรุปยอดท้ายตาราง: ไทย | อังกฤษ | จำนวนเงิน */
    /* ความกว้างคอลัมน์ของกล่อง = คอลัมน์ ราคา / จำนวน / จำนวนเงิน ของตารางรายการ ให้เส้นตรงกัน */
    table.totals td { height: 21pt; padding: 0 4pt; font-size: 13pt; white-space: nowrap; }
    table.totals td.t1 { width: {{ round($tI / $tSum * 100, 2) }}%; }
    /* ไทย/อังกฤษในกล่องใช้ 13pt เท่ากัน (วัดแล้ว "Total Discount" 13pt = 19.1mm < ช่อง 23mm) */
    table.totals td.t2 { width: {{ round($tJ / $tSum * 100, 2) }}%; padding: 0 2pt; }
    table.totals td.t3 { width: {{ round($tK / $tSum * 100, 2) }}%; }
    .sig-line { text-decoration: underline; }
    .pre { white-space: pre-wrap; }
</style>
</head>
<body>

@php
    $termsHtml = RichText::block($q->paymentTerms());
@endphp
{{--
    โครงสร้างหน้า: เนื้อหา (หัวกระดาษ → เงื่อนไข) ไหลตามปกติ ข้ามหน้าได้
    ส่วนปิดท้าย (หลัง marker CLOSING) ถูก QuotePdfService วางตรึงไว้ล่างสุดของหน้าสุดท้าย
    โดยวัดพื้นที่ที่เหลือจริง ถ้าไม่พอจะขึ้นหน้าใหม่ให้ก่อน และกรอบนอกถูกวาดให้ทุกหน้า
--}}
{{-- ============ แถว 1-2 : หัวกระดาษ (Picture 4 ที่ A1:K2) — ซ้ำทุกหน้าด้วย htmlpageheader
     (พื้นที่จองโดย margin_top = margin_top_mm + header_height_mm ใน QuotePdfService) ============ --}}
<!--HEADER-->
    {{-- เว้นระยะรอบหัวกระดาษ: ขอบบน 6pt, ซ้าย 2% --}}
    {{-- ใช้ inline style ล้วน เพราะ page header ถูก render แยกจาก <style> ของเอกสาร --}}
    <table style="margin-top: 6pt; width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 2%; height: 72pt; padding: 0;"></td>
            <td style="width: 98%; height: 72pt; padding: 0; text-align: left; vertical-align: middle;">
                @if($hasHeader)
                    <img src="{{ $headerImg }}" style="width: 175mm;">
                @else
                    <div style="font-weight: bold; font-size: 17.5pt;">{{ $company['name_th'] }}</div>
                    <div style="font-size: 11.5pt;">{{ $company['name_en'] }} &nbsp; เลขประจำตัวผู้เสียภาษี {{ $company['tax_id'] }}</div>
                @endif
            </td>
        </tr>
    </table>
<!--/HEADER-->

<div class="content">

    {{-- ============ แถว 3-6 : ข้อมูลลูกค้า / เลขที่ / วันที่ / ผู้เสนอ ============ --}}
    <table class="info">
        <tr style="height: 26pt; margin-top: 4pt;">
            <td class="nopad" style="width: {{ $wA }}%;"></td>
            <td class="bd b l" style="width: {{ $wB }}%;">เรียน</td>
            <td class="bd b l" style="width: {{ $wCH }}%;">{{ $q->customerName() }}</td>
            <td class="bd b l nowrap" style="width: {{ $wI }}%;">ใบเสนอราคาเลขที่ :</td>
            <td class="bd b c" style="width: {{ $wJ + $wK }}%;">{{ $q->number() }}</td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>
        <tr style="height: 28.8pt;">
            <td class="nopad"></td>
            {{-- ป้าย "ที่อยู่" รวมเป็นเซลล์เดียวคู่กับช่องที่อยู่ 2 บรรทัด --}}
            <td class="bd b l" rowspan="2">ที่อยู่</td>
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
            {{-- หัวข้อการให้บริการเป็น rich text จาก CRM → กรองเหลือ tag พื้นฐานแล้ว render --}}
            <td class="bd b c f24 fill-pink" style="width: {{ 100 - $wA - $wL }}%; height: 53pt;">{!! RichText::inline($q->serviceSubject()) !!}&nbsp;</td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>
        <tr style="height: 19pt;">
            <td class="nopad"></td>
            <td class="bd c f16">{{ $texts['intro'] }}</td>
            <td class="nopad"></td>
        </tr>
    </table>

    {{-- ============ แถว 9-14 : ตารางรายการ (thead ซ้ำทุกหน้าเมื่อรายการยาวข้ามหน้า) ============ --}}
    <table>
        <thead>
        <tr style="height: 38pt;">
            <td class="nopad" style="width: {{ $wA }}%;"></td>
            <td class="bd b c f15 fill-grey" style="width: {{ $wB }}%;">ลำดับที่<br>ITEM</td>
            <td class="bd b c f15 fill-grey" style="width: {{ $tCH }}%;">รายการ<br>DESCRIPTION</td>
            <td class="bd b c f15 fill-grey" style="width: {{ $tI }}%;">ราคา<br>Price</td>
            <td class="bd b c f15 fill-grey" style="width: {{ $tJ }}%;">จำนวน<br>Quantity</td>
            <td class="bd b c f15 fill-grey" style="width: {{ $tK }}%;">จำนวนเงิน<br>Amount</td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>
        </thead>
        <tbody>
        @foreach($items as $item)
            <tr style="height: 25.4pt;">
                <td class="nopad"></td>
                <td class="bd c f22">{{ $item['no'] }}</td>
                <td class="bd l f22">
                    {{ $item['name'] }}
                   
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
        </tbody>
    </table>
    {{-- QuotePdfService หยุดตรงนี้เพื่อวัดตำแหน่งจริง แล้วเติมแถวว่าง (BLANKROWS) ให้พอดีกรอบก่อนเขียนแถวยอดรวม --}}
    <!--ITEMS_END count={{ count($items) }} terms={{ $hasTermsPage ? 1 : 0 }}-->
    <!--BLANKROWS-->
    <table>
        <!--ROW-->
        <tr style="height: 25.4pt;">
            <td class="nopad" style="width: {{ $wA }}%;"></td>
            <td class="bd" style="width: {{ $wB }}%;">&nbsp;</td>
            <td class="bd" style="width: {{ $tCH }}%;"></td>
            <td class="bd" style="width: {{ $tI }}%;"></td>
            <td class="bd" style="width: {{ $tJ }}%;"></td>
            <td class="bd" style="width: {{ $tK }}%;"></td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>
        <!--/ROW-->
    </table>
    <!--/BLANKROWS-->

    {{-- แถว 14 : กล่องสรุปยอด (ขวา) + จำนวนเงินเป็นตัวอักษร (ซ้าย) --}}
    <table style="margin-top: 6pt;">
        <tr>
            <td class="nopad" style="width: {{ $wA }}%;"></td>
            {{-- พื้นที่ซ้ายของกล่องสรุปยอดเว้นว่าง (ไม่แสดงจำนวนเงินเป็นตัวอักษร) --}}
            <td class="nopad" style="width: {{ round(100 - $wA - $wL - $tSum, 2) }}%;"></td>
            {{-- กล่องสรุปยอดกว้างเท่า 3 คอลัมน์ขวาของตารางรายการพอดี เส้นแบ่งจึงตรงกัน --}}
            <td class="nopad" style="width: {{ $tSum }}%;">
                <table class="totals">
                    <tr>
                        <td class="bd r b t1">รวมเป็นเงิน</td>
                        <td class="bd c b t2 nowrap">Total Amount</td>
                        <td class="bd r t3">{{ F::money($q->totalAmount(), false) }}</td>
                    </tr>
                    <tr>
                        <td class="bd r b t1">ส่วนลด</td>
                        <td class="bd c b t2 nowrap">Total Discount</td>
                        <td class="bd r t3">{{ F::money($q->totalDiscount(), false) }}</td>
                    </tr>
                    <tr>
                        <td class="bd r b t1">ภาษีมูลค่าเพิ่ม 7%</td>
                        <td class="bd c b t2 nowrap">Total Tax</td>
                        <td class="bd r t3">{{ F::money($q->totalTax(), false) }}</td>
                    </tr>
                    <tr>
                        <td class="bd r b t1">จำนวนเงินรวมทั้งสิ้น</td>
                        <td class="bd c b t2 nowrap">Grand Total</td>
                        <td class="bd r b t3">{{ F::money($q->grandTotal(), false) }}</td>
                    </tr>
                </table>
            </td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>
    </table>

    @if($termsHtml !== '')
        {{-- หน้า 1 จบที่ตารางรายการ/ยอดรวม → เงื่อนไข/สิทธิประโยชน์ขึ้นหน้าใหม่ (ปิดได้ด้วย document.terms_on_new_page = false) --}}
        @if($doc['terms_on_new_page'] ?? true)
            <pagebreak />
        @else
            {{-- ============ แถว 15 : เว้น ============ --}}
            <div style="height: 9pt;"></div>
        @endif

        {{-- ============ แถว 16 : เงื่อนไข และรายละเอียดการชำระเงิน ============ --}}
        <table>
            <tr>
                <td class="l top f18 rich" style="padding: 2pt 4pt 0 4pt; min-height: 13pt;">{!! $termsHtml !!}</td>
            </tr>
        </table>
    @endif

</div>
<!--CLOSING-->
<div class="closing-pinned">

    {{-- ============ แถว 17-20 : ข้อความปิดท้าย (กึ่งกลาง ตัวหนา เว้นระยะจากเงื่อนไขด้านบน) ============ --}}
    <table style="margin-top: 10pt;">
        @foreach($texts['closing'] as $line)
            <tr style="height: 21pt;">
                <td class="c b f18">{{ $line }}</td>
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
    <table style="page-break-inside: avoid; margin-top: 4pt; margin-bottom: 8pt;">
        <tr style="height: 84pt;">
            <td class="nopad" style="width: 3.6%;"></td>
            <td class="sig-box f16" style="width: 26.2%;">
                <div>(<span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>)</div>
                {{-- ( ${Quotes.Account Name} ) --}}
                <div class="b">( {{ $q->customerName() ?: '&nbsp;' }} )</div>
                <div class="b">{{ $texts['sign_customer'] }}</div>
            </td>
            <td class="nopad" style="width: 2.9%;"></td>
            <td class="sig-box f16" style="width: 32.5%;">
                <div>(<span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>)</div>
                {{-- ( ${Quotes.ชื่อจริง} ${Quotes.นามสกุล} ) --}}
                <div class="b">( {{ $q->salesFullName() ?: '&nbsp;' }} )</div>
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

</div>

{{-- ============ แถว 25-28 : ท้ายกระดาษ (รูปภาพ 3) + รหัสแบบฟอร์ม ${Quotes.ISO}
     ตรึงไว้ล่างสุดของทุกหน้าด้วย htmlpagefooter (พื้นที่จองโดย margin_bottom ใน QuotePdfService) ============ --}}
{{-- ส่วนนี้ QuotePdfService ดึงไปตั้งเป็น page footer ด้วย SetHTMLFooter ตั้งแต่หน้าแรก (ใช้ inline style ล้วน เหมือนหัวกระดาษ) --}}
<!--FOOTER-->
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 0; text-align: center;">
                @if($hasFooter)
                    <img src="{{ $footerImg }}" style="width: 194mm;">
                @endif
            </td>
        </tr>
    </table>
    {{-- รหัสฟอร์มจัดกึ่งกลางใต้ QR code ของภาพท้ายกระดาษ
         (QR อยู่ที่ 83.7%–92.4% ของความกว้างภาพ 194mm ซึ่งวางกึ่งกลางพื้นที่ 200mm → ศูนย์กลาง QR ≈ 173.8mm) --}}
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            {{-- เลขหน้า เช่น "หน้า 1/2" ที่มุมซ้ายล่าง --}}
            <td style="width: 78.4%; padding: 1pt 0 0 2mm; text-align: left; font-size: 11.5pt; color: #444; white-space: nowrap;">หน้า {PAGENO}/{nbpg}</td>
            <td style="width: 17%; padding: 1pt 0 0 0; text-align: center; font-size: 13pt; white-space: nowrap;">{{ $q->isoCode() }}</td>
            <td style="width: 4.6%; padding: 0;"></td>
        </tr>
    </table>
<!--/FOOTER-->

@if($trackingUrl)
    <div style="position: absolute; right: 6mm; bottom: 4mm;">
        <barcode code="{{ $trackingUrl }}" type="QR" size="0.45" error="M" disableborder="1" />
    </div>
@endif

</body>
</html>
