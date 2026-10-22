<?php

/*
|--------------------------------------------------------------------------
| การตั้งค่าระบบออก PDF ใบเสนอราคา (แบบฟอร์ม FA-SA-02-103)
|--------------------------------------------------------------------------
*/

return [

    // Secret สำหรับสร้าง/ตรวจ signature ของลิงก์ (ต้องตรงกับฝั่ง Deluge)
    'link_secret' => env('QUOTE_LINK_SECRET'),

    // ความยาว signature (hex) ที่ใช้ใน URL — 64 = เต็ม SHA-256
    // ถ้าระบบ Tracking เดิมตัดให้สั้น ให้ตั้งค่าให้ตรงกัน (เช่น 32)
    'signature_length' => (int) env('QUOTE_SIGNATURE_LENGTH', 64),

    // base URL ของระบบ (ใช้สร้างลิงก์ / QR code)
    'base_url' => env('QUOTE_BASE_URL', env('APP_URL')),

    // เปิด route แบบสั้น /{record_id} (ไม่มี signature) เพื่อให้ CRM สร้าง URL ได้ตรง ๆ
    // เช่น https://pdf.trackingmouthefirst.com/6274719000184443042
    // ข้อแลกเปลี่ยน: ใครรู้ record ID ก็เปิด PDF ได้ (ID 19 หลัก เดายาก แต่ไม่ใช่ความลับ)
    'allow_unsigned' => (bool) env('QUOTE_ALLOW_UNSIGNED', false),

    // Rate limit ต่อ IP (จำนวน request ต่อนาที)
    'rate_limit_per_minute' => (int) env('QUOTE_RATE_LIMIT', 30),

    // โฟลเดอร์เก็บไฟล์ PDF (relative กับ storage/app)
    'storage_dir' => 'quotes',

    // ลบไฟล์ PDF ที่เก่ากว่า N วัน
    'retention_days' => (int) env('QUOTE_RETENTION_DAYS', 7),

    // รหัสแบบฟอร์ม (ใช้เมื่อ Quote ไม่มีค่าใน field ISO)
    'form_code' => env('QUOTE_FORM_CODE', 'FA-SA-02-103'),

    // แสดง QR Code ของลิงก์เอกสารหรือไม่ (ฟอร์มต้นแบบไม่มี จึงปิดเป็นค่าเริ่มต้น)
    'show_qr' => (bool) env('QUOTE_SHOW_QR', false),

    // ข้อมูลบริษัท
    'company' => [
        'name_th' => env('COMPANY_NAME_TH', 'บริษัทนำคนต่างด้าวมาทำงานในประเทศ เดอะ เฟิร์ส กูด แมน กรุ๊ป จำกัด'),
        'name_en' => env('COMPANY_NAME_EN', 'The First Good Man Group Co., Ltd.'),
        'tax_id' => env('COMPANY_TAX_ID', '0105559140065'),
        // ภาพหัวกระดาษ / ท้ายกระดาษ (ตามแบบฟอร์ม TFG หัวกระดาษ-ISO)
        'header_image' => env('COMPANY_HEADER_IMAGE', resource_path('images/header.jpg')),
        'footer_image' => env('COMPANY_FOOTER_IMAGE', resource_path('images/footer.jpg')),
    ],

    // ผู้อนุมัติใบเสนอราคา (กล่องลงนามด้านขวา)
    'approver' => [
        'name' => env('QUOTE_APPROVER_NAME', 'นายเฟื่องฟู ชีวินมหรัตน์'),
        'title' => env('QUOTE_APPROVER_TITLE', 'กรรมการผู้จัดการ/ผู้อนุมัติเสนอราคา'),
    ],

    // ข้อความคงที่ในแบบฟอร์ม (ตรงกับ sheet "คนเมียวดี (เขียว-แดง)")
    'texts' => [
        'intro' => 'บริษัทนำคนต่างด้าวมาทำงานในประเทศ เดอะ เฟิร์ส กูด แมน กรุ๊ป จำกัด  ใคร่ขอเสนอราคา และ เงื่อนไข ตามรายละเอียด นี้.-',
        'closing' => [
            'บริษัทนำคนต่างด้าวมาทำงานในประเทศ เดอะ เฟิร์ส กูด แมน กรุ๊ป จำกัด',
            'บริษัทฯ ขอขอบพระคุณท่านที่ให้ความไว้วางใจและเลือกใช้บริการด้วยดีเสมอมา',
            'หากท่านพิจารณาและยอมรับใบเสนอราคาฉบับนี้ กรุณาลงนามโดยผู้มีอำนาจพร้อมประทับตราบริษัท (ถ้ามี)',
            'และส่งกลับทาง LINE หรืออีเมล เพื่อให้บริษัทฯ ดำเนินการในขั้นตอนต่อไป',
        ],
        'confirm_title' => 'การยืนยันการใช้บริการ',
        'confirm_body' => 'ข้าพเจ้าตกลงใช้บริการตามรายการ อัตราค่าบริการ และรายละเอียดที่ระบุในใบเสนอราคาฉบับนี้ พร้อมยอมรับข้อกำหนดและเงื่อนไขการให้บริการทุกประการ',
        'sign_customer' => 'ลูกค้าคนสำคัญ',
        'sign_sales' => 'ผู้เสนอขาย',
    ],

    // ค่าเริ่มต้นสำหรับเอกสาร
    'document' => [
        'currency_symbol' => '฿',
        'paper' => 'A4',
        // ระยะขอบตาม Page Setup ของ Excel ต้นแบบ (นิ้ว → มม.)
        'margin_left_mm' => 6,
        'margin_right_mm' => 4,
        'margin_top_mm' => 4.5,
        'margin_bottom_mm' => 6,
        // ฟอนต์ตามต้นแบบ: Angsana New (มี fallback เป็น TH Sarabun New ถ้าไม่มีไฟล์)
        'font' => env('QUOTE_FONT', 'angsananew'),
        'font_size' => 12,
        // จำนวนแถวรายการขั้นต่ำที่แสดงในตาราง (ต้นแบบมี 3 แถว)
        'min_item_rows' => 3,
    ],
];
