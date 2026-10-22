@php use App\Support\QuoteData as F; @endphp
{{-- แถว 3-6 : ข้อมูลลูกค้า / เลขที่ / วันที่ / ผู้เสนอ --}}
<table class="info">
    <tr style="height: 26pt; margin-top: 4pt;">
        <td class="nopad" style="width: {{ $wA }}%;"></td>
        <td class="bd b l" style="width: {{ $wB }}%;">เรียน</td>
        <td class="bd b l" style="width: {{ $wCH }}%;">{{ $q->customerName() }}</td>
        <td class="bd b l nowrap" style="width: {{ $wI }}%;">ใบเสนอราคาเลขที่&nbsp;:</td>
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
        <td class="bd b l">วันที่&nbsp;:</td>
        {{-- วันที่ dd/mm/yyyy แสดงเฉพาะเมื่อ Quote Stage = อนุมัติ --}}
        <td class="bd b c">{{ $q->isApproved() ? F::dateFormat($q->quoteDate()) : '' }}&nbsp;</td>
        <td class="nopad"></td>
    </tr>
    <tr style="height: 24.4pt;">
        <td class="nopad"></td>
        <td class="bd b l">ผู้เสนอราคา&nbsp;:</td>
        <td class="bd b c">{{ $q->salesName() }}</td>
        <td class="nopad"></td>
    </tr>
    <tr style="height: 24.4pt;">
        <td class="nopad"></td>
        <td class="bd b l">ติดต่อ</td>
        <td class="bd b l">{{ $q->contactName() }}</td>
        <td class="bd b l">โทรศัพท์&nbsp;:</td>
        <td class="bd b c">{{ $q->contactMobile() }}</td>
        <td class="nopad"></td>
    </tr>
</table>
