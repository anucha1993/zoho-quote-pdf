@php use App\Support\QuoteData as F; @endphp
{{-- ตารางข้อมูลหัวฟอร์ม 4 แถว (ตาม mail merge template)
     ซ้าย : บริษัท / ที่อยู่ (Accounts.Billing) / ผู้ติดต่อ / เบอร์ (Contacts.Mobile)
     ขวา  : ใบเสนอราคาเลขที่ / วันที่เสนอราคา / ฝ่ายขายผู้เสนอราคา / เบอร์โทรศัพท์ (Quotes.เบอร์โทรศัพท์)
     ป้ายจาก $L (อาจมี 2 บรรทัด เช่น ไทย+จีน) ป้ายชิดขวา ค่าชิดซ้าย --}}
<table class="info">
    <tr style="height: 26pt;">
        <td class="nopad" style="width: {{ $wA }}%;"></td>
        <td class="bd b r nowrap" style="width: {{ $iLabelL }}%;">{!! $L['to'] !!}</td>
        <td class="bd b l" style="width: {{ $iValL }}%;">{{ $q->customerName() }}</td>
        <td class="bd b r lr nowrap" style="width: {{ $iLabelR }}%;">{!! $L['quote_no'] !!}</td>
        <td class="bd b l" style="width: {{ $iValR }}%;">{{ $q->number() }}</td>
        <td class="nopad" style="width: {{ $wL }}%;"></td>
    </tr>
    <tr style="height: 26pt;">
        <td class="nopad"></td>
        <td class="bd b r nowrap">{!! $L['address'] !!}</td>
        <td class="bd b l">{{ $q->billingAddress() }}</td>
        <td class="bd b r lr nowrap">{!! $L['date'] !!}</td>
        {{-- วันที่ dd/mm/yyyy แสดงเฉพาะเมื่อ Quote Stage = อนุมัติ --}}
        <td class="bd b l">{{ $q->isApproved() ? F::dateFormat($q->quoteDate()) : '' }}&nbsp;</td>
        <td class="nopad"></td>
    </tr>
    <tr style="height: 26pt;">
        <td class="nopad"></td>
        <td class="bd b r nowrap">{!! $L['contact'] !!}</td>
        <td class="bd b l">{{ $q->contactName() }}</td>
        <td class="bd b r lr nowrap">{!! $L['sales'] !!}</td>
        <td class="bd b l">{{ $q->salesName() }}</td>
        <td class="nopad"></td>
    </tr>
    <tr style="height: 26pt;">
        <td class="nopad"></td>
        <td class="bd b r nowrap">{!! $L['contact_phone'] ?? 'เบอร์&nbsp;:' !!}</td>
        <td class="bd b l">{{ $q->contactMobile() }}</td>
        <td class="bd b r lr nowrap">{!! $L['phone'] !!}</td>
        <td class="bd b l">{{ $q->salesPhone() }}</td>
        <td class="nopad"></td>
    </tr>
</table>
