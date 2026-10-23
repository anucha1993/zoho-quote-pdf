@php
    /**
     * ตารางรายการ + กล่องสรุปยอด (ใช้ซ้ำได้ทั้ง QT Thailand และ QT Destination)
     *
     * @var array $rows      รายการจาก QuoteData::items() หรือ destinationItems()
     * @var array $totals    ['amount' => , 'discount' => , 'tax' => , 'grand' => ]
     * @var bool  $fillMarkers ใส่ marker ITEMS_END/BLANKROWS ให้ QuotePdfService เติมแถวว่าง (เฉพาะตารางแรกของเอกสาร)
     * @var string|null $sectionTitle แถบชื่อส่วนเหนือตาราง (เช่น "QT Destination") ไม่ใส่ = ไม่แสดง
     * @var bool  $showNameCn  แสดง Product Name (CN) ใต้ชื่อสินค้า (ฟอร์ม FA-SA-CN)
     * @var float|null $rowTotal ยอดแถว "ยอดรวม" ท้ายตาราง = ยอดเฉพาะตารางนี้ (ไม่ใส่ = ใช้ $totals['amount'])
     * @var bool  $showTotals  แสดงกล่องสรุปยอด (รวมเป็นเงิน/ภาษี/รวมทั้งสิ้น) ใต้ตาราง — หน้า QT Destination ไม่แสดง
     */
    use App\Support\QuoteData as F;
    $fillMarkers = $fillMarkers ?? false;
    $sectionTitle = $sectionTitle ?? null;
    $showNameCn = $showNameCn ?? false;
    $rowTotal = $rowTotal ?? $totals['amount'];
    $showTotals = $showTotals ?? true;
@endphp

@if($sectionTitle)
    <table style="margin-top: 6pt;">
        <tr>
            <td class="nopad" style="width: {{ $wA }}%;"></td>
            <td class="bd section-title f18" style="width: {{ 100 - $wA - $wL }}%;">{!! $sectionTitle !!}</td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>
    </table>
@endif

{{-- แถว 9-14 : ตารางรายการ (thead ซ้ำทุกหน้าเมื่อรายการยาวข้ามหน้า) --}}
<table>
    <thead>
    <tr style="height: 38pt;">
        <td class="nopad" style="width: {{ $wA }}%;"></td>
        <td class="bd b c f15 fill-grey" style="width: {{ $wB }}%;">{!! $L['col_no'] !!}</td>
        <td class="bd b c f15 fill-grey" style="width: {{ $tCH }}%;">{!! $L['col_desc'] !!}</td>
        <td class="bd b c f15 fill-grey" style="width: {{ $tI }}%;">{!! $L['col_price'] !!}</td>
        <td class="bd b c f15 fill-grey" style="width: {{ $tJ }}%;">{!! $L['col_qty'] !!}</td>
        <td class="bd b c f15 fill-grey" style="width: {{ $tK }}%;">{!! $L['col_amount'] !!}</td>
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
                @if(($showNameCn ?? false) && !empty($item['name_cn']))
                    {{-- ฟอร์มจีน: Product Name (CN) ใต้ชื่อไทย --}}
                    <br>{{ $item['name_cn'] }}
                @endif
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
        {{-- แถว "ยอดรวม" ท้ายตาราง = ผลรวมช่องจำนวนเงิน (ก่อนส่วนลด/ภาษี) ตาม «Quotes.Total ในต้นแบบ --}}
        <tr style="height: 25.4pt;">
            <td class="nopad"></td>
            <td class="bd" colspan="2"></td>
            <td class="bd r b f22" colspan="2">{!! $L['row_total'] ?? 'ยอดรวม' !!}</td>
            {{-- ยอดเฉพาะตารางนี้ (ต่างจากกล่องสรุปยอดด้านล่างซึ่งเป็นยอดรวมทั้งใบ) --}}
            <td class="bd r b f22">{{ F::money($rowTotal, false) }}</td>
            <td class="nopad"></td>
        </tr>
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

@if($showTotals)
{{-- กล่องสรุปยอด (ขวา): 2 คอลัมน์ขวาตรงกับคอลัมน์ จำนวน/จำนวนเงิน ของตาราง, คอลัมน์ป้ายไทยกว้างกว่าคอลัมน์ ราคา เล็กน้อย --}}
<table style="margin-top: 6pt;">
    <tr>
        <td class="nopad" style="width: {{ $wA }}%;"></td>
        <td class="nopad" style="width: {{ round(100 - $wA - $wL - $tTotW, 2) }}%;"></td>
        <td class="nopad" style="width: {{ $tTotW }}%;">
            <table class="totals">
                <tr>
                    <td class="bd r b t1">{!! $L['total_amount'][0] !!}</td>
                    <td class="bd c b t2 nowrap">{!! $L['total_amount'][1] !!}</td>
                    <td class="bd r t3">{{ F::money($totals['amount'], false) }}</td>
                </tr>
                {{-- แถว "ส่วนลด / Total Discount" ไม่แสดงตามคำขอ (เปิดได้ด้วย document.show_discount = true) --}}
                @if(!empty($doc['show_discount']))
                <tr>
                    <td class="bd r b t1">{!! $L['total_discount'][0] !!}</td>
                    <td class="bd c b t2 nowrap">{!! $L['total_discount'][1] !!}</td>
                    <td class="bd r t3">{{ F::money($totals['discount'], false) }}</td>
                </tr>
                @endif
                <tr>
                    <td class="bd r b t1">{!! $L['total_tax'][0] !!}</td>
                    <td class="bd c b t2 nowrap">{!! $L['total_tax'][1] !!}</td>
                    <td class="bd r t3">{{ F::money($totals['tax'], false) }}</td>
                </tr>
                <tr>
                    <td class="bd r b t1">{!! $L['grand_total'][0] !!}</td>
                    <td class="bd c b t2 nowrap">{!! $L['grand_total'][1] !!}</td>
                    <td class="bd r b t3">{{ F::money($totals['grand'], false) }}</td>
                </tr>
            </table>
        </td>
        <td class="nopad" style="width: {{ $wL }}%;"></td>
    </tr>
</table>
@endif
