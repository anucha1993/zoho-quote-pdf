{{-- แถว 15-16 : เงื่อนไข และรายละเอียดการชำระเงิน (rich text จาก CRM) --}}
@if($termsHtml !== '')
    {{-- หน้าแรกจบที่ตารางรายการ/ยอดรวม → เงื่อนไข/สิทธิประโยชน์ขึ้นหน้าใหม่ (ปิดได้ด้วย document.terms_on_new_page = false)
         marker TERMS_BREAK: QuotePdfService จะขึ้นหน้าใหม่ให้ ยกเว้นตารางรายการล้นไปหน้าถัดไปแล้ว (ต่อท้ายได้เลย ไม่ทิ้งหน้าที่มีแต่ยอดรวม) --}}
    @if($doc['terms_on_new_page'] ?? true)
        <!--TERMS_BREAK-->
    @else
        <div style="height: 9pt;"></div>
    @endif

    {{-- เว้นระยะซ้าย/ขวาให้ตรงแนวกับตารางด้านบน (ไม่ชิดขอบกระดาษ) --}}
    <table>
        <tr>
            <td class="nopad" style="width: {{ $wA }}%;"></td>
            <td class="l top f18 rich" style="padding: 6pt 18pt 0 18pt; min-height: 13pt;">{!! $termsHtml !!}</td>
            <td class="nopad" style="width: {{ $wL }}%;"></td>
        </tr>
    </table>
@endif
