@php
    /**
     * ตารางรายการแบบอัตราค่าบริการ (ฟอร์ม FA-SA-SUB): ลำดับที่ | จำนวนพนักงานต่างด้าว (คน) | อัตราค่าบริการ (ราคา | หน่วย)
     * + แถวเงื่อนไขการคิดค่าบริการ — ไม่มีจำนวน/จำนวนเงิน/สรุปยอด
     *
     * @var array $rows รายการจาก QuoteData::items()
     */
    use App\Support\BahtText;

    // อัตราค่าบริการกว้างเท่า ราคา+จำนวน+จำนวนเงิน ของฟอร์ม TH (เส้นซ้ายตรงกับป้ายขวาของตารางข้อมูลลูกค้า)
    // แบ่งเป็น ราคา (เช่น "30 บาท (สามสิบบาท)") กับ หน่วย ("ต่อคน / ต่อวัน")
    // ช่องหน่วยกว้างพอให้ "ต่อคน / ต่อวัน" อยู่บรรทัดเดียว ที่เหลือให้ราคา
    $rUnit = 15.0;
    $rPrice = round($tSum - $rUnit, 2);
    // "30 บาท (สามสิบบาท)" — ราคาเต็มบาทไม่แสดง .00 และไม่ต้องมี "ถ้วน"
    $rateText = fn (float $price) => number_format($price, fmod($price, 1.0) == 0.0 ? 0 : 2)
        .' บาท ('.preg_replace('/ถ้วน$/u', '', BahtText::convert($price)).')';
    // ค่าสำรองกรณี config ยังไม่มี forms.FA-SA-SUB (เช่น config cache บนเซิร์ฟเวอร์ยังเป็นชุดเก่า) — ไม่ให้ 500
    $colRate = $L['col_rate'] ?? 'อัตราค่าบริการ<br><span style="font-weight: normal;">Service Rate</span>';
    $rateUnit = $texts['rate_unit'] ?? 'ต่อคน / ต่อวัน';
    $condition = trim((string) ($texts['rate_condition'] ?? ''));
@endphp

<table>
    <thead>
    <tr style="height: 38pt;">
        <td class="nopad" style="width: {{ $wA }}%;"></td>
        <td class="bd b c f15 fill-subject" style="width: {{ $wB }}%;">{!! $L['col_no'] !!}</td>
        <td class="bd b c f15 fill-subject" style="width: {{ $tCH }}%;">{!! $L['col_desc'] !!}</td>
        <td class="bd b c f15 fill-subject" colspan="2" style="width: {{ $tSum }}%;">{!! $colRate !!}</td>
        <td class="nopad" style="width: {{ $wL }}%;"></td>
    </tr>
    </thead>
    <tbody>
    @forelse($rows as $item)
        <tr style="height: 38pt;">
            <td class="nopad"></td>
            <td class="bd c f22">{{ $item['no'] }}</td>
            <td class="bd l f22">{{ $item['name'] }}</td>
            <td class="bd l f22" style="width: {{ $rPrice }}%;">{{ $rateText($item['list_price']) }}</td>
            <td class="bd c f22" style="width: {{ $rUnit }}%;">{{ $rateUnit }}</td>
            <td class="nopad"></td>
        </tr>
    @empty
        <tr style="height: 38pt;">
            <td class="nopad"></td>
            <td class="bd" colspan="4">&nbsp;</td>
            <td class="nopad"></td>
        </tr>
    @endforelse
    @if($condition !== '')
        <tr style="height: 30pt;">
            <td class="nopad"></td>
            <td class="bd l b f18" colspan="4" style="padding: 3pt 6pt;">{!! $condition !!}</td>
            <td class="nopad"></td>
        </tr>
    @endif
    </tbody>
</table>
