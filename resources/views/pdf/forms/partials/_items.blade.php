@php
    /**
     * ตารางรายการ + กล่องสรุปยอด (ใช้ซ้ำได้ทั้ง QT Thailand และ QT Destination)
     *
     * @var array $rows      รายการจาก QuoteData::items() หรือ destinationItems()
     * @var array $totals    ['amount' => , 'discount' => , 'tax' => , 'grand' => ]
     * @var bool  $fillMarkers ใส่ marker ITEMS_END/BLANKROWS ให้ QuotePdfService เติมแถวว่าง (เฉพาะตารางแรกของเอกสาร)
     * @var string|null $sectionTitle แถบชื่อส่วนเหนือตาราง (เช่น "QT Destination") ไม่ใส่ = ไม่แสดง
     */
    use App\Support\QuoteData as F;
    $fillMarkers = $fillMarkers ?? false;
    $sectionTitle = $sectionTitle ?? null;
@endphp

@if($sectionTitle)
    <table style="margin-top: 6pt;">
        <tr>
            <td class="nopad" style="width: {{ $wA }}%;"></td>
            <td class="bd section-title f18" style="width: {{ 100 - $wA - $wL }}%;">{{ $sectionTitle }}</td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>
    </table>
@endif

{{-- แถว 9-14 : ตารางรายการ (thead ซ้ำทุกหน้าเมื่อรายการยาวข้ามหน้า) --}}
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
    @forelse($rows as $item)
        <tr style="height: 25.4pt;">
            <td class="nopad"></td>
            <td class="bd c f22">{{ $item['no'] }}</td>
            <td class="bd l f22">
                {{ $item['name'] }}
                @if(($item['recurring_amount'] ?? null) !== null && $item['recurring_amount'] > 0)
                    <br><span class="f16">ราคาค่าใช้งานปีถัดไป (Recurring Amount {{ F::money($item['recurring_amount']) }})</span>
                @endif
            </td>
            <td class="bd r f22">{{ F::money($item['list_price'], false) }}</td>
            <td class="bd c f22">{{ F::qty($item['quantity']) }}@if(!empty($item['unit'])) <span class="f16">{{ $item['unit'] }}</span>@endif</td>
            <td class="bd r f22">{{ F::money($item['total'], false) }}</td>
            <td class="nopad"></td>
        </tr>
    @empty
        <tr style="height: 25.4pt;">
            <td class="nopad"></td>
            <td class="bd" colspan="5">&nbsp;</td>
            <td class="nopad"></td>
        </tr>
    @endforelse
    </tbody>
</table>

@if($fillMarkers)
    {{-- QuotePdfService หยุดตรงนี้เพื่อวัดตำแหน่งจริง แล้วเติมแถวว่าง (ถ้าเปิด fill_item_rows) ก่อนเขียนกล่องสรุปยอด --}}
    <!--ITEMS_END count={{ count($rows) }} terms={{ $hasTermsPage ? 1 : 0 }}-->
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
@endif

{{-- กล่องสรุปยอด (ขวา) กว้างเท่า 3 คอลัมน์ขวาของตารางพอดี เส้นแบ่งจึงตรงกัน --}}
<table style="margin-top: 6pt;">
    <tr>
        <td class="nopad" style="width: {{ $wA }}%;"></td>
        <td class="nopad" style="width: {{ round(100 - $wA - $wL - $tSum, 2) }}%;"></td>
        <td class="nopad" style="width: {{ $tSum }}%;">
            <table class="totals">
                <tr>
                    <td class="bd r b t1">รวมเป็นเงิน</td>
                    <td class="bd c b t2 nowrap">Total Amount</td>
                    <td class="bd r t3">{{ F::money($totals['amount'], false) }}</td>
                </tr>
                <tr>
                    <td class="bd r b t1">ส่วนลด</td>
                    <td class="bd c b t2 nowrap">Total Discount</td>
                    <td class="bd r t3">{{ F::money($totals['discount'], false) }}</td>
                </tr>
                <tr>
                    <td class="bd r b t1">ภาษีมูลค่าเพิ่ม 7%</td>
                    <td class="bd c b t2 nowrap">Total Tax</td>
                    <td class="bd r t3">{{ F::money($totals['tax'], false) }}</td>
                </tr>
                <tr>
                    <td class="bd r b t1">จำนวนเงินรวมทั้งสิ้น</td>
                    <td class="bd c b t2 nowrap">Grand Total</td>
                    <td class="bd r b t3">{{ F::money($totals['grand'], false) }}</td>
                </tr>
            </table>
        </td>
        <td class="nopad" style="width: {{ $wL }}%;"></td>
    </tr>
</table>
