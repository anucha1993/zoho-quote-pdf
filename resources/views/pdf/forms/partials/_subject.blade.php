@php use App\Support\RichText; @endphp
{{-- แถว 7 : หัวข้อการให้บริการ (พื้นชมพู) / แถว 8 : ข้อความนำ --}}
<table>
    <tr style="height: 53pt;">
        <td class="nopad" style="width: {{ $wA }}%;"></td>
        {{-- หัวข้อการให้บริการเป็น rich text จาก CRM → กรองเหลือ tag พื้นฐานแล้ว render
             &nbsp; ใส่เฉพาะตอนว่าง: ถ้าต่อท้ายข้อความ บรรทัดสุดท้ายที่เป็นภาษาจีนจะสูงเท่าตัวไทย 16pt → ห่างจากบรรทัดก่อนหน้า --}}
        @php $subjectHtml = RichText::inline($q->serviceSubject()); @endphp
        <td class="bd b c f24 fill-subject" style="width: {{ 100 - $wA - $wL }}%; height: 53pt;">{!! $subjectHtml !== '' ? $subjectHtml : '&nbsp;' !!}</td>
        <td class="nopad" style="width: {{ $wL }}%;"></td>
    </tr>
    <tr style="height: 19pt;">
        <td class="nopad"></td>
        {{-- ข้อความนำจาก config (อาจมี <br> สำหรับบรรทัดภาษาจีน) --}}
        <td class="bd c f16">{!! $texts['intro'] !!}</td>
        <td class="nopad"></td>
    </tr>
</table>
