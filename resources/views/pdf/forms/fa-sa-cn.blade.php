@php
    /**
     * ฟอร์ม FA-SA-CN — ใบเสนอราคา ไทย-จีน (อ้างอิง FA-SA-TH, คำแปลจาก ex/FA-SA-CN.xlsx)
     *
     * โครงสร้างเหมือน FA-SA-TH ทุกประการ ต่างกันที่ป้าย/ข้อความ (config forms['FA-SA-CN'] → labels, texts, approver)
     * แสดง 2 บรรทัด ไทย + จีน และแถบหัวข้อบริการเป็นสีเขียว
     *
     * หน้า 1: หัวกระดาษ ข้อมูลลูกค้า หัวข้อบริการ ตารางรายการ (QT Thailand) + กล่องสรุปยอด
     * หน้า 2: เงื่อนไข/สิทธิประโยชน์ (rich text จาก CRM พิมพ์ไทย/จีนได้) + ข้อความปิดท้าย + กล่องลงนาม
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
    {{-- ตารางรายการ: แสดง Product Name (CN) ใต้ชื่อไทย --}}
    @include('pdf.forms.partials._items', ['rows' => $q->items(), 'rowTotal' => $q->tableSubtotal(), 'totals' => $q->totals(), 'fillMarkers' => true, 'showNameCn' => true])
    @include('pdf.forms.partials._terms')
</div>

@include('pdf.forms.partials._closing')
@include('pdf.forms.partials._footer')
