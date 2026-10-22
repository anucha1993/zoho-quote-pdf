{{-- แถว 15-16 : เงื่อนไข และรายละเอียดการชำระเงิน (rich text จาก CRM) --}}
@if($termsHtml !== '')
    {{-- หน้าแรกจบที่ตารางรายการ/ยอดรวม → เงื่อนไข/สิทธิประโยชน์ขึ้นหน้าใหม่ (ปิดได้ด้วย document.terms_on_new_page = false) --}}
    @if($doc['terms_on_new_page'] ?? true)
        <pagebreak />
    @else
        <div style="height: 9pt;"></div>
    @endif

    <table>
        <tr>
            <td class="l top f18 rich" style="padding: 2pt 4pt 0 4pt; min-height: 13pt;">{!! $termsHtml !!}</td>
        </tr>
    </table>
@endif
