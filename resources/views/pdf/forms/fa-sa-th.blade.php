@php
    /**
     * ฟอร์ม FA-SA-TH — ใบเสนอราคาภาษาไทย (sheet "คนเมียวดี (เขียว-แดง)" ของ FA-SA.xlsx)
     *
     * หน้า 1: หัวกระดาษ ข้อมูลลูกค้า หัวข้อบริการ ตารางรายการ (QT Thailand) + กล่องสรุปยอด
     * หน้า 2: เงื่อนไข/สิทธิประโยชน์ + ข้อความปิดท้าย + กล่องลงนาม (ถ้าไม่มีเงื่อนไข ส่วนลงนามอยู่หน้า 1)
     *
     * ส่วนประกอบทั้งหมดอยู่ใน partials/ ใช้ร่วมกับฟอร์มอื่นในตระกูล FA-SA
     *
     * @var \App\Support\QuoteData $q
     */
    use App\Support\FormLayout;

    extract(FormLayout::vars($q, $doc, $company));
@endphp
@include('pdf.forms.partials._head')
@include('pdf.forms.partials._header')

<div class="content">
    @include('pdf.forms.partials._info')
    @include('pdf.forms.partials._subject')
    @include('pdf.forms.partials._items', ['rows' => $q->items(), 'rowTotal' => $q->tableSubtotal(), 'totals' => $q->totals(), 'fillMarkers' => true])
    @include('pdf.forms.partials._terms')
</div>

@include('pdf.forms.partials._closing')
@include('pdf.forms.partials._footer')
