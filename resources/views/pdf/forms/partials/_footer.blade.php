{{-- ท้ายกระดาษ (รูปภาพ 3) + เลขหน้า + รหัสแบบฟอร์ม ${Quotes.ISO}
     QuotePdfService ดึงส่วนใน marker ไปตั้งเป็น page footer ทุกหน้าตั้งแต่หน้าแรก (inline style ล้วน) --}}
<!--FOOTER-->
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 0; text-align: center;">
                @if($hasFooter)
                    {{-- ภาพท้ายกระดาษเต็มความกว้างพื้นที่พิมพ์ 200mm --}}
                    <img src="{{ $footerImg }}" style="width: {{ 210 - ($doc['margin_left_mm'] ?? 6) - ($doc['margin_right_mm'] ?? 4) }}mm;">
                @endif
            </td>
        </tr>
    </table>
    {{-- รหัสฟอร์มจัดกึ่งกลางใต้ QR code ของภาพท้ายกระดาษ (ภาพถูกตัดขอบขาวโดย ImageTrim แล้ว)
         (QR อยู่ที่ 88.7%–98.7% ของความกว้างภาพที่ตัดแล้ว → ศูนย์กลาง 93.7% ของ 200mm; ช่องกว้าง 12.8% เริ่มที่ 87.2% มีจุดกึ่งกลางที่ 93.6%) --}}
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            {{-- เลขหน้าอยู่กึ่งกลางหน้ากระดาษพอดี: ช่องว่างซ้าย 12.8% + ช่อง 74.4% (จุดกึ่งกลาง = 50%) --}}
            <td style="width: 12.8%; padding: 0;"></td>
            <td style="width: 74.4%; padding: 1pt 0 0 0; text-align: center; font-size: 11.5pt; color: #444; white-space: nowrap;">{!! $L['page'] ?? 'หน้า' !!} {PAGENO}/{nbpg}</td>
            <td style="width: 12.8%; padding: 1pt 0 0 0; text-align: center; font-size: 13pt; white-space: nowrap;">{{ $q->isoCode() }}</td>
        </tr>
    </table>
<!--/FOOTER-->

@if($trackingUrl)
    <div style="position: absolute; right: 6mm; bottom: 4mm;">
        <barcode code="{{ $trackingUrl }}" type="QR" size="0.45" error="M" disableborder="1" />
    </div>
@endif

</body>
</html>
