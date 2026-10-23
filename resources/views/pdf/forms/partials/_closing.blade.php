{{-- ส่วนปิดท้าย: QuotePdfService วัดพื้นที่ที่เหลือจริงหลัง marker นี้ ถ้าไม่พอจะขึ้นหน้าใหม่ แล้ววางบล็อกนี้ตรึงล่างสุดของหน้า
     ข้อความทั้งหมดมาจาก config (อาจมี <br> สำหรับบรรทัดภาษาจีน) จึง render แบบไม่ escape --}}
<!--CLOSING-->
<div class="closing-pinned">

    {{-- แถว 17-20 : ข้อความปิดท้าย (กึ่งกลาง ตัวหนา) --}}
    <table style="margin-top: 10pt;">
        @foreach($texts['closing'] as $line)
            <tr style="height: 21pt;">
                <td class="c b f18">{!! $line !!}</td>
            </tr>
        @endforeach
    </table>

    {{-- แถว 21-22 : การยืนยันการใช้บริการ — ขอบซ้ายตรงกับกรอบลงนาม/ท้ายกระดาษ, เว้นบน 1 บรรทัด, หัวข้อ 18pt --}}
    <table style="margin-top: 20pt;">
        <tr style="height: 22pt;">
            <td class="l b fh" style="padding: 0;">{!! $texts['confirm_title'] !!}</td>
        </tr>
        <tr style="height: 22pt;">
            <td class="l top f18" style="padding: 0;">{!! $texts['confirm_body'] !!}</td>
        </tr>
    </table>

    {{-- แถว 23 : กล่องลงนาม 3 กล่อง เต็มความกว้างเท่าภาพท้ายกระดาษ (ซ้ายสุด-ขวาสุด) --}}
    <table style="page-break-inside: avoid; margin-top: 6pt; margin-bottom: 8pt;">
        <tr style="height: 96pt;">
            <td class="sig-box f16" style="width: 31%;">
                <div>(<span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>)</div>
                {{-- ( ${Quotes.Account Name} ) --}}
                <div class="b">( {{ $q->customerName() ?: '&nbsp;' }} )</div>
                <div class="b">{!! $texts['sign_customer'] !!}</div>
            </td>
            <td class="nopad" style="width: 3.5%;"></td>
            @php
                /* รูปลายเซ็นวาดเป็น background ของกล่อง (ไม่อยู่ใน flow) → เลื่อนรูปได้อิสระโดยข้อความไม่ขยับ
                   ตำแหน่ง = ระยะจากขอบบนกล่อง (approver.signature_top_mm) ความสูงรูป = signature_height_pt */
                $sigStyle = function (string $file) use ($approver, $doc): string {
                    // mPDF ไม่ใช้ background-size กับช่องตาราง → ย่อรูปให้พิกเซลตรงกับความสูงที่ต้องการที่ img_dpi ก่อน
                    $scaled = \App\Support\ImageTrim::scaledToHeight(
                        $file,
                        (float) ($approver['signature_height_pt'] ?? 34),
                        (int) ($doc['img_dpi'] ?? 300)
                    );

                    return sprintf(
                        'background-image: url(%s); background-repeat: no-repeat; background-position: center %smm;',
                        str_replace(DIRECTORY_SEPARATOR, '/', $scaled),
                        $approver['signature_top_mm'] ?? 3
                    );
                };
            @endphp
            <td class="sig-box f16" style="width: 31%; @if(!empty($salesSignature) && is_file($salesSignature)){{ $sigStyle($salesSignature) }}@endif">
                {{-- ลายเซ็นพนักงานขายจาก Staffs.Image Sign (background ของกล่อง) เหนือเส้นลงนาม --}}
                <div>(<span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>)</div>
                {{-- ( ${Quotes.ชื่อจริง} ${Quotes.นามสกุล} ) --}}
                <div class="b">( {{ $q->salesFullName() ?: '&nbsp;' }} )</div>
                <div class="b">{!! $texts['sign_sales'] !!}</div>
            </td>
            <td class="nopad" style="width: 3.5%;"></td>
            @php $sigImg = $approver['signature'] ?? null; @endphp
            {{-- ลายเซ็นผู้อนุมัติ แสดงเฉพาะ Quote สถานะ "อนุมัติ" (quote.approved_stages) เช่นเดียวกับวันที่ --}}
            <td class="sig-box f16" style="width: 31%; @if($sigImg && is_file($sigImg) && $q->isApproved()){{ $sigStyle($sigImg) }}@endif">
                <div>(<span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>)</div>
                <div class="b">( {{ $approver['name'] }} )</div>
                <div class="b">{!! $approver['title'] !!}</div>
            </td>
        </tr>
    </table>

</div>
