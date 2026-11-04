@php
    /**
     * ฟอร์ม FA-SA-SUB — ใบเสนอราคาค่าบริการดูแลพนักงาน (โครงเดียวกับ FA-SA-TH)
     *
     * ต่างจาก FA-SA-TH เฉพาะตารางรายการ: ลำดับที่ | จำนวนพนักงานต่างด้าว (คน) | อัตราค่าบริการ (ราคา + หน่วย)
     * ตามด้วยแถวเงื่อนไขการคิดค่าบริการ ไม่มีแถวยอดรวมและกล่องสรุปยอด
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
    @include('pdf.forms.partials._items_sub', ['rows' => $q->items()])
    @include('pdf.forms.partials._terms')
</div>

@include('pdf.forms.partials._closing')
@include('pdf.forms.partials._footer')
