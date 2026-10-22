@php
    /**
     * ฟอร์ม FA-SA-CNDE — ไทย-จีน (FA-SA-CN) + หน้า "QT Destination" แบบเดียวกับ FA-SA-THDE
     *
     * หน้า 1: หัวกระดาษ ข้อมูลลูกค้า หัวข้อบริการ ตารางรายการ QT Destination + กล่องสรุปยอด (QT_Destination / *_D)
     * หน้า 2: ตารางรายการ QT Thailand (ชื่อสินค้าไทย + Product Name (CN)) + กล่องสรุปยอด (Quoted_Items / QT_*)
     * หน้า 3: เงื่อนไข/สิทธิประโยชน์ + ข้อความปิดท้าย + กล่องลงนาม
     *
     * ป้าย/ข้อความ 2 ภาษามาจาก config forms['FA-SA-CNDE'] (ต่อยอดจาก FA-SA-CN)
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

    {{-- ===== หน้า 1 : QT Destination ===== --}}
    @include('pdf.forms.partials._items', [
        'rows' => $q->destinationItems(),
        'totals' => $q->destinationTotals(),
        'fillMarkers' => true,
        'sectionTitle' => $texts['destination_title'] ?? 'QT Destination',
    ])

    {{-- ===== หน้า 2 : QT Thailand ===== --}}
    <pagebreak />
    @include('pdf.forms.partials._info')
    @include('pdf.forms.partials._items', [
        'rows' => $q->items(),
        'totals' => $q->totals(),
        'fillMarkers' => false,
        'sectionTitle' => $texts['thailand_title'] ?? 'QT Thailand',
        'showNameCn' => true,
    ])

    @include('pdf.forms.partials._terms')
</div>

@include('pdf.forms.partials._closing')
@include('pdf.forms.partials._footer')
