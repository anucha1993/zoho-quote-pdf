@php
    /**
     * ฟอร์ม TRACKING — "Deal Information & Tracking" (module Deals / ใบงาน) แยกจากใบเสนอราคา
     *
     * หน้าเดียว: หัวกระดาษบริษัท → ข้อมูลใบงาน 2 คอลัมน์ → ตาราง Tracking System
     * ใช้ header/footer ร่วมกับฟอร์มใบเสนอราคา (partials) แต่เนื้อหาเป็นของตัวเอง
     *
     * @var \App\Support\DealData $q
     */
    use App\Support\FormLayout;
    use App\Support\QuoteData as F;

    extract(FormLayout::vars($q, $doc, $company));
    $rows = $q->trackingRows();
@endphp
@include('pdf.forms.partials._head')
{{-- ฟอร์มนี้ไม่ใช้หัวกระดาษ/ท้ายกระดาษของบริษัท (ไม่ include _header/_footer) ขอบกระดาษกำหนดใน config forms['TRACKING']['document'] --}}

<style>
    table.kv td { padding: 2pt 4pt; vertical-align: top; }
    table.kv td.k { width: 24%; text-align: right; color: #333; white-space: nowrap; }
    table.kv td.v { width: 26%; font-weight: bold; }
    table.track th, table.track td { border: 0.3pt solid #000; padding: 2pt 4pt; }
    table.track th { background: #D9D9D9; font-weight: bold; text-align: center; font-size: 12pt; }
    table.track td { font-size: 12pt; vertical-align: middle; }
    .title { font-size: 16pt; font-weight: bold; margin: 2pt 0 6pt 4pt; }
    .sig2 { border: 0.3pt solid #000; height: 80pt; text-align: center; vertical-align: middle; font-size: 12pt; }
</style>

<div class="content">

    <div class="title">{{ $texts['tracking_title'] ?? 'Deal Information & Tracking' }}</div>

    {{-- ข้อมูลใบงาน 2 คอลัมน์ (ตามตำแหน่งใน mail merge template) --}}
    <table class="kv">
        <tr><td class="k">ชื่อบริษัท :</td><td class="v" colspan="3">{{ $q->customerName() }}</td></tr>
        <tr><td class="k">ผู้ติดต่อ :</td><td class="v">{{ $q->contactName() }}</td><td class="k">Job Code Auto :</td><td class="v">{{ $q->jobCode() }}</td></tr>
        <tr><td class="k">เบอร์ติดต่อ :</td><td class="v">{{ $q->contactPhone() }}</td><td class="k">ISO :</td><td class="v">{{ $q->isoCode() }}</td></tr>
        <tr><td class="k">Deal Name :</td><td class="v">{{ $q->dealName() }}</td><td class="k">M5L Job Code :</td><td class="v">{{ $q->m5lJobCode() }}</td></tr>
        <tr><td class="k">Document Group :</td><td class="v">{{ $q->documentGroup() }}</td><td class="k">ประเภทใบงาน :</td><td class="v">{{ $q->mouService() }}</td></tr>
        <tr><td class="k">แรงงานชาย (คน) :</td><td class="v">{{ $q->maleWorkers() }}</td><td class="k">ผู้เปิดใบงาน :</td><td class="v">{{ $q->openerName() }}</td></tr>
        <tr><td class="k">แรงงานหญิง (คน) :</td><td class="v">{{ $q->femaleWorkers() }}</td><td class="k">ค่าใช้จ่ายนายจ้าง :</td><td class="v">{{ $q->employerCostPerPerson() }}</td></tr>
        <tr><td class="k">รวมจำนวนแรงงาน :</td><td class="v">{{ $q->totalWorkers() }}</td><td class="k">ค่าใช้จ่ายนายจ้างรวม :</td><td class="v">{{ $q->employerCostTotal() }}</td></tr>
        <tr><td class="k">แรงงานสัญชาติ :</td><td class="v">{{ $q->nationality() }}</td><td class="k">ค่าใช้จ่ายแรงงานรวม :</td><td class="v">{{ $q->workerCostTotal() }}</td></tr>
        <tr><td class="k">ค่าใช้จ่ายแรงงาน :</td><td class="v">{{ $q->workerCostPerPerson() }}</td><td class="k">การตรวจสุขภาพแรงงานต่างด้าว :</td><td class="v">{{ $q->healthCheck() }}</td></tr>
        <tr><td class="k">หมายเหตุ :</td><td class="v">{{ $q->remark() }}</td><td class="k">ซื้อประกันสุขภาพ (เพิ่มเติม) :</td><td class="v">{{ $q->healthInsurance() }}</td></tr>
    </table>

    {{-- ตาราง Tracking System --}}
    <table class="track" style="margin-top: 10pt;">
        <thead>
        <tr>
            <th style="width: 6%;">No.</th>
            <th style="width: 30%;">รายการ</th>
            <th style="width: 16%;">สถานะ</th>
            <th style="width: 13%;">วันที่เริ่มดำเนินการ</th>
            <th style="width: 13%;">วันที่ดำเนินเสร็จ</th>
            <th style="width: 11%;">จำนวนประมาณ</th>
            <th style="width: 11%;">จำนวนจริง</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            <tr style="height: 22pt;">
                <td class="c">{{ $row['no'] }}</td>
                <td class="l">{{ $row['job_status'] }}@if($row['delay_reason'])<br><span class="f16" style="color:#555;">{{ $row['delay_reason'] }}</span>@endif</td>
                <td class="c">{{ $row['tracking_status'] }}</td>
                <td class="c">{{ $row['expected_date'] }}</td>
                <td class="c">{{ $row['actual_date'] }}</td>
                <td class="c">{{ $row['estimated_days'] }}</td>
                <td class="c">{{ $row['actual_days'] }}</td>
            </tr>
        @empty
            <tr style="height: 22pt;"><td colspan="7" class="c" style="color:#777;">— ไม่มีรายการติดตาม —</td></tr>
        @endforelse
        </tbody>
    </table>

</div>
<!--CLOSING-->
<div class="closing-pinned">
    {{-- กล่องลงนาม 2 กล่อง: ผู้เปิดใบงาน / ผู้อนุมัติ (ไม่พิมพ์รูปลายเซ็น เซ็นสดบนกระดาษ) --}}
    <table style="page-break-inside: avoid; margin-bottom: 8pt;">
        <tr style="height: 90pt;">
            <td class="nopad" style="width: 22%;"></td>
            <td class="sig2" style="width: 24%;">
                <div>(<span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>)</div>
                <div>____/____/____</div>
                <div class="b">({{ $texts['sign_opener'] ?? 'ผู้เปิดใบงาน' }})</div>
                <div class="f16">{{ $q->openerName() }}</div>
            </td>
            <td class="nopad" style="width: 8%;"></td>
            <td class="sig2" style="width: 24%;">
                <div>(<span class="sig-line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>)</div>
                <div>____/____/____</div>
                <div class="b">({{ $texts['sign_approver'] ?? 'ผู้อนุมัติ' }})</div>
                <div class="f16">&nbsp;</div>
            </td>
            <td class="nopad" style="width: 22%;"></td>
        </tr>
    </table>
</div>

</body>
</html>
