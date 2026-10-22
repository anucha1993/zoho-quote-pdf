{{-- ท้ายกระดาษ (รูปภาพ 3) + เลขหน้า + รหัสแบบฟอร์ม ${Quotes.ISO}
     QuotePdfService ดึงส่วนใน marker ไปตั้งเป็น page footer ทุกหน้าตั้งแต่หน้าแรก (inline style ล้วน) --}}
<!--FOOTER-->
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 0; text-align: center;">
                @if($hasFooter)
                    <img src="{{ $footerImg }}" style="width: 194mm;">
                @endif
            </td>
        </tr>
    </table>
    {{-- รหัสฟอร์มจัดกึ่งกลางใต้ QR code ของภาพท้ายกระดาษ
         (QR อยู่ที่ 83.7%–92.4% ของความกว้างภาพ 194mm ซึ่งวางกึ่งกลางพื้นที่ 200mm → ศูนย์กลาง QR ≈ 173.8mm) --}}
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            {{-- เลขหน้าอยู่กึ่งกลางหน้ากระดาษพอดี: ช่องว่างซ้าย 21.6% + ช่อง 56.8% (จุดกึ่งกลาง = 50%) --}}
            <td style="width: 21.6%; padding: 0;"></td>
            <td style="width: 56.8%; padding: 1pt 0 0 0; text-align: center; font-size: 11.5pt; color: #444; white-space: nowrap;">หน้า {PAGENO}/{nbpg}</td>
            <td style="width: 17%; padding: 1pt 0 0 0; text-align: center; font-size: 13pt; white-space: nowrap;">{{ $q->isoCode() }}</td>
            <td style="width: 4.6%; padding: 0;"></td>
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
