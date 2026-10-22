{{-- ส่วนปิดท้าย: QuotePdfService วัดพื้นที่ที่เหลือจริงหลัง marker นี้ ถ้าไม่พอจะขึ้นหน้าใหม่ แล้ววางบล็อกนี้ตรึงล่างสุดของหน้า --}}
<!--CLOSING-->
<div class="closing-pinned">

    {{-- แถว 17-20 : ข้อความปิดท้าย (กึ่งกลาง ตัวหนา) --}}
    <table style="margin-top: 10pt;">
        @foreach($texts['closing'] as $line)
            <tr style="height: 21pt;">
                <td class="c b f18">{{ $line }}</td>
            </tr>
        @endforeach
    </table>

    {{-- แถว 21-22 : การยืนยันการใช้บริการ --}}
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

    {{-- แถว 23 : กล่องลงนาม 3 กล่อง (Rectangle 8 / 4 / 3) --}}
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
                @if(!empty($salesSignature) && is_file($salesSignature))
                    {{-- ลายเซ็นพนักงานขายจาก Staffs.Image Sign วางเหนือเส้นลงนาม --}}
                    <div style="margin-bottom: -6pt;"><img src="{{ $salesSignature }}" style="height: {{ $approver['signature_height_pt'] ?? 34 }}pt;"></div>
                @endif
                <div>(<span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>)</div>
                {{-- ( ${Quotes.ชื่อจริง} ${Quotes.นามสกุล} ) --}}
                <div class="b">( {{ $q->salesFullName() ?: '&nbsp;' }} )</div>
                <div class="b">{{ $texts['sign_sales'] }}</div>
            </td>
            <td class="nopad" style="width: 3.8%;"></td>
            <td class="sig-box f16" style="width: 22.9%;">
                @php $sigImg = $approver['signature'] ?? null; @endphp
                @if($sigImg && is_file($sigImg) && $q->isApproved())
                    {{-- ลายเซ็นผู้อนุมัติ แสดงเฉพาะ Quote สถานะ "อนุมัติ" (quote.approved_stages) เช่นเดียวกับวันที่ --}}
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
