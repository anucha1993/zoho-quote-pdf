@php use App\Support\RichText; @endphp
{{-- แถว 7 : หัวข้อการให้บริการ (พื้นชมพู) / แถว 8 : ข้อความนำ --}}
<table>
    <tr style="height: 53pt;">
        <td class="nopad" style="width: {{ $wA }}%;"></td>
        {{-- หัวข้อการให้บริการเป็น rich text จาก CRM → กรองเหลือ tag พื้นฐานแล้ว render --}}
        <td class="bd b c f24 fill-subject" style="width: {{ 100 - $wA - $wL }}%; height: 53pt;">{!! RichText::inline($q->serviceSubject()) !!}&nbsp;</td>
        <td class="nopad" style="width: {{ $wL }}%;"></td>
    </tr>
    <tr style="height: 19pt;">
        <td class="nopad"></td>
        {{-- ข้อความนำจาก config (อาจมี <br> สำหรับบรรทัดภาษาจีน) --}}
        <td class="bd c f16">{!! $texts['intro'] !!}</td>
        <td class="nopad"></td>
    </tr>
</table>
