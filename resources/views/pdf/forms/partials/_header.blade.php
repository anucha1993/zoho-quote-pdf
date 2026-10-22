{{-- หัวกระดาษ (Picture 4 ที่ A1:K2) — QuotePdfService ดึงส่วนใน marker ไปตั้งเป็น page header ซ้ำทุกหน้า
     (พื้นที่จองโดย margin_top = margin_top_mm + header_height_mm) ใช้ inline style ล้วน เพราะ render แยกจาก <style> --}}
<!--HEADER-->
    {{-- ภาพหัวกระดาษเต็มความกว้างพื้นที่พิมพ์ (210 − ขอบซ้าย − ขอบขวา = 200mm) --}}
    <table style="margin-top: 4pt; width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 100%; padding: 0; text-align: left; vertical-align: middle;">
                @if($hasHeader)
                    <img src="{{ $headerImg }}" style="width: {{ 210 - ($doc['margin_left_mm'] ?? 6) - ($doc['margin_right_mm'] ?? 4) }}mm;">
                @else
                    <div style="font-weight: bold; font-size: 17.5pt;">{{ $company['name_th'] }}</div>
                    <div style="font-size: 11.5pt;">{{ $company['name_en'] }} &nbsp; เลขประจำตัวผู้เสียภาษี {{ $company['tax_id'] }}</div>
                @endif
            </td>
        </tr>
    </table>
<!--/HEADER-->
