{{-- หัวกระดาษ (Picture 4 ที่ A1:K2) — QuotePdfService ดึงส่วนใน marker ไปตั้งเป็น page header ซ้ำทุกหน้า
     (พื้นที่จองโดย margin_top = margin_top_mm + header_height_mm) ใช้ inline style ล้วน เพราะ render แยกจาก <style> --}}
<!--HEADER-->
    <table style="margin-top: 6pt; width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 2%; height: 72pt; padding: 0;"></td>
            <td style="width: 98%; height: 72pt; padding: 0; text-align: left; vertical-align: middle;">
                @if($hasHeader)
                    <img src="{{ $headerImg }}" style="width: 175mm;">
                @else
                    <div style="font-weight: bold; font-size: 17.5pt;">{{ $company['name_th'] }}</div>
                    <div style="font-size: 11.5pt;">{{ $company['name_en'] }} &nbsp; เลขประจำตัวผู้เสียภาษี {{ $company['tax_id'] }}</div>
                @endif
            </td>
        </tr>
    </table>
<!--/HEADER-->
