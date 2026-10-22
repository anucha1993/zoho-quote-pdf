<?php

/*
|--------------------------------------------------------------------------
| การตั้งค่าระบบออก PDF ใบเสนอราคา (แบบฟอร์ม FA-SA-02-103)
|--------------------------------------------------------------------------
*/

/*
| ฟอร์มไทย-จีน (FA-SA-CN) — ใช้ร่วมกับ FA-SA-CNDE ที่เพิ่มหน้า QT Destination
*/
$cnForm = [
    'label' => 'ใบเสนอราคา ไทย-จีน (FA-SA-CN)',
    'view' => 'pdf.forms.fa-sa-cn',
    'document' => [
        'subject_fill' => '#C5E0B4',   // แถบหัวข้อบริการสีเขียวตามต้นแบบ FA-SA-CN.xlsx
    ],
    // ป้ายในฟอร์ม: บรรทัดไทย + บรรทัดจีน (คำแปลจาก ex/FA-SA-CN.xlsx)
    'labels' => [
        'to' => 'เรียน<br>致',
        'quote_no' => 'ใบเสนอราคาเลขที่&nbsp;:<br>报价单编号:',
        'address' => 'ที่อยู่<br>地址',
        'date' => 'วันที่เสนอราคา&nbsp;:<br>报价日期:',
        // ป้ายยาวกว่าช่อง 31mm ที่ 13pt จึงย่อเป็น 11.5pt ให้อยู่บรรทัดเดียว
        'sales' => '<span style="font-size: 11.5pt;">ฝ่ายขายผู้เสนอราคา&nbsp;:</span><br>报价人:',
        'contact' => 'ติดต่อ<br>联系人',
        'phone' => 'เบอร์โทรศัพท์&nbsp;:<br>联系方式:',
        'col_no' => 'ลำดับที่<br>序号',
        'col_desc' => 'รายการ<br>详细资料',
        'col_price' => 'ราคา<br>价格',
        'col_qty' => 'จำนวน<br>人数',
        'col_amount' => 'จำนวนเงิน<br>金额',
        'row_total' => 'ยอดรวม 小计',
        'total_amount' => ['รวมเป็นเงิน', '合计'],
        'total_discount' => ['ส่วนลด', '折扣'],
        'total_tax' => ['ภาษีมูลค่าเพิ่ม 7%', '增值税 7%'],
        'grand_total' => ['จำนวนเงินรวมทั้งสิ้น', '总合'],
    ],
    'texts' => [
        'intro' => 'บริษัทนำคนต่างด้าวมาทำงานในประเทศ เดอะ เฟิร์ส กูด แมน กรุ๊ป จำกัด  ใคร่ขอเสนอราคา และ เงื่อนไข ตามรายละเอียด นี้<br>泰国第一人力资源集团有限公司报价以及服务条件如下:',
        'closing' => [
            'บริษัทนำคนต่างด้าวมาทำงานในประเทศ เดอะ เฟิร์ส กูด แมน กรุ๊ป จำกัด ใคร่ขอขอบพระคุณที่ไว้วางใจเรียกใช้บริการของเราด้วยดีเสมอมา',
            '泰国第一人力资源集团有限公司感谢您一如既往对我们服务的信任',
            'หลังจาก ลูกค้า/ผู้มีอำนาจลงนามได้ลงนามอนุมัติในเอกสารฉบับนี้แล้ว รบกวน ส่งแฟกซ์ หรือ อีเมล์ กลับมายัง',
            '客户/授权签字人签署并批准本文件后，请将传真或电子邮件发回至',
            'บริษัทนำคนต่างด้าวมาทำงานในประเทศ เดอะ เฟิร์ส กูด แมน กรุ๊ป จำกัด ด้วย เพื่อดำเนินการเอกสารในขั้นตอนต่อไป',
            '泰国第一人力资源集团有限公司再处理下一步文件',
        ],
        'confirm_title' => 'การยืนยันการใช้บริการ / 服务确认',
        'confirm_body' => 'ข้าพเจ้าตกลงสั่งซื้อตามรายการและยอมรับในเงื่อนไขที่เสนอมาทั้งหมด<br>本人接受所有提议的条款和条件',
        'sign_customer' => 'ลูกค้าคนสำคัญ (ลงนาม/ประทับตรา)<br>客户(签名/盖章)',
        'sign_sales' => 'ฝ่ายการตลาด/ผู้เสนอขาย<br>营销/报价人',
    ],
    'approver' => [
        'title' => 'กรรมการผู้จัดการ/ผู้อนุมัติเสนอราคา<br>执行董事/价格核准人',
    ],
];

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

    /*
    | แบบฟอร์ม PDF — URL: https://pdf.trackingmouthefirst.com/{form}/{record_id}
    | slug ของฟอร์ม = ชื่อไฟล์ใน resources/views/pdf/forms/ (ตัวพิมพ์เล็ก)
    | เพิ่มฟอร์มใหม่ได้โดยวางไฟล์ Blade เพิ่ม ไม่ต้องแก้ที่นี่ (ใส่เฉพาะเมื่อต้องการ override ค่า)
    */
    'default_form' => env('QUOTE_DEFAULT_FORM', 'FA-SA-TH'),

    /*
    | เลือกฟอร์มอัตโนมัติ: URL /auto/{id} จะอ่านค่า field ของ Quote (ค่าเริ่มต้น ISO เช่น "FA-SA-02-062")
    | แล้วเทียบกับ map ด้านล่าง → ได้ slug ของฟอร์ม ถ้าไม่ตรงใช้ default_form
    | key ใน map รองรับ * ท้ายเป็น prefix เช่น 'FA-SA-02-06*' => 'FA-SA-TH'
    | ใน CRM จึงใช้ปุ่มเดียว https://pdf.trackingmouthefirst.com/auto/${Quotes.Quote Id} สำหรับทุกฟอร์ม
    */
    'auto_form' => [
        'field' => env('QUOTE_FORM_FIELD', 'ISO'),
        'map' => [
            // 'FA-SA-02-062' => 'FA-SA-TH',
            // 'FA-SA-02-*-CN' => 'FA-SA-CN',
        ],
    ],
    'forms' => [
        'FA-SA-TH' => [
            'label' => 'ใบเสนอราคา ภาษาไทย (FA-SA)',
            'view' => 'pdf.forms.fa-sa-th',
        ],
        'FA-SA-THDE' => [
            'label' => 'ใบเสนอราคา ภาษาไทย + หน้า QT Destination',
            'view' => 'pdf.forms.fa-sa-thde',
            'texts' => [
                // แถบชื่อส่วนเหนือตาราง: หน้า 1 = QT Destination, หน้า 2 = QT Thailand
                'destination_title' => env('QUOTE_DESTINATION_TITLE', 'รายการค่าบริการปลายทาง (QT Destination)'),
                'thailand_title' => env('QUOTE_THAILAND_TITLE', 'รายการค่าบริการในประเทศ (QT Thailand)'),
            ],
        ],
        // ---- ฟอร์มของ module Deals (ใบงาน) — URL: /TRACKING/{deal id} ----
        'TRACKING' => [
            'label' => 'Deal Information & Tracking (ใบงาน)',
            'view' => 'pdf.forms.tracking',
            'module' => 'Deals',
            // ไม่มีหัว/ท้ายกระดาษ → ไม่ต้องจองพื้นที่ ใช้ขอบกระดาษปกติ
            'document' => [
                'header_height_mm' => 6,
                'margin_top_mm' => 10,
                'margin_bottom_mm' => 12,
                'closing_height_mm' => 40,
            ],
            'texts' => [
                'tracking_title' => 'Deal Information & Tracking',
                'sign_opener' => 'ผู้เปิดใบงาน',
                'sign_approver' => 'ผู้อนุมัติ',
            ],
        ],
        'FA-SA-CN' => $cnForm,
        'FA-SA-CNDE' => array_replace_recursive($cnForm, [
            'label' => 'ใบเสนอราคา ไทย-จีน + หน้า QT Destination',
            'view' => 'pdf.forms.fa-sa-cnde',
            'texts' => [
                'destination_title' => 'รายการค่าบริการปลายทาง (QT Destination) 目的地服务费用',
                'thailand_title' => 'รายการค่าบริการในประเทศ (QT Thailand) 泰国境内服务费用',
            ],
        ]),
    ],

    // Rate limit ต่อ IP (จำนวน request ต่อนาที)
    'rate_limit_per_minute' => (int) env('QUOTE_RATE_LIMIT', 30),

    // โฟลเดอร์เก็บไฟล์ PDF (relative กับ storage/app)
    'storage_dir' => 'quotes',

    // ลบไฟล์ PDF ที่เก่ากว่า N วัน
    'retention_days' => (int) env('QUOTE_RETENTION_DAYS', 7),

    // รหัสแบบฟอร์ม (ใช้เมื่อ Quote ไม่มีค่าใน field ISO)
    'form_code' => env('QUOTE_FORM_CODE', 'FA-SA-02-103'),

    // Quote Stage ที่ถือว่า "อนุมัติ" — แสดงวันที่ในเอกสารเฉพาะสถานะเหล่านี้
    // (Zoho ส่งค่า display "อนุมัติ"; actual value ใน CRM คือ "Confirmed" ใส่ไว้ทั้งคู่กันพลาด)
    'approved_stages' => array_values(array_filter(array_map('trim', explode(',', env('QUOTE_APPROVED_STAGES', 'อนุมัติ,Confirmed'))))),

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
        // รูปลายเซ็นผู้อนุมัติ (PNG พื้นโปร่ง) — ตั้งเป็นค่าว่างถ้าไม่ต้องการพิมพ์ลายเซ็น
        'signature' => env('QUOTE_APPROVER_SIGNATURE', resource_path('images/signature-approver.png')),
        'signature_height_pt' => (int) env('QUOTE_APPROVER_SIGNATURE_HEIGHT', 34),
    ],

    /*
    | ป้ายช่องต่าง ๆ ในฟอร์ม (ค่าเริ่มต้น = ไทย/อังกฤษ ตาม FA-SA-TH) — ฟอร์มอื่น override ได้ใน forms[...]['labels']
    | ใส่ <br> ได้เพื่อแสดง 2 บรรทัด (เช่น ไทย + จีน) ค่าในนี้ถูก render แบบไม่ escape จึงต้องมาจาก config เท่านั้น
    */
    'labels' => [
        'to' => 'เรียน',
        'quote_no' => 'ใบเสนอราคาเลขที่&nbsp;:',
        'address' => 'ที่อยู่',
        'date' => 'วันที่&nbsp;:',
        'sales' => 'ผู้เสนอราคา&nbsp;:',
        'contact' => 'ติดต่อ',
        'phone' => 'โทรศัพท์&nbsp;:',
        'col_no' => 'ลำดับที่<br>ITEM',
        'col_desc' => 'รายการ<br>DESCRIPTION',
        'col_price' => 'ราคา<br>Price',
        'col_qty' => 'จำนวน<br>Quantity',
        'col_amount' => 'จำนวนเงิน<br>Amount',
        // แถวสุดท้ายของตารางรายการ
        'row_total' => 'ยอดรวม',
        // กล่องสรุปยอด: [ป้ายซ้าย, ป้ายกลาง]
        'total_amount' => ['รวมเป็นเงิน', 'Total Amount'],
        'total_discount' => ['ส่วนลด', 'Total Discount'],
        'total_tax' => ['ภาษีมูลค่าเพิ่ม 7%', 'Total Tax'],
        'grand_total' => ['จำนวนเงินรวมทั้งสิ้น', 'Grand Total'],
        'page' => 'หน้า',
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
        'sign_customer' => 'ลูกค้าคนสำคัญ (ลงนาม/ประทับตรา)',
        'sign_sales' => 'ฝ่ายการตลาด/ผู้เสนอขาย',
    ],

    // ค่าเริ่มต้นสำหรับเอกสาร
    'document' => [
        'currency_symbol' => '฿',
        'paper' => 'A4',
        // ระยะขอบตาม Page Setup ของ Excel ต้นแบบ (นิ้ว → มม.)
        'margin_left_mm' => 6,
        'margin_right_mm' => 4,
        'margin_top_mm' => 4.5,
        // ความสูงพื้นที่หัวกระดาษ (ภาพโลโก้/ที่อยู่) ที่ซ้ำทุกหน้า — margin บนของเนื้อหา = margin_top_mm + ค่านี้
        'header_height_mm' => 32,
        // ขอบล่างเผื่อพื้นที่ท้ายกระดาษ (รูป ISO/LINE/QR + รหัสฟอร์ม) ซึ่งตรึงไว้ล่างสุดของทุกหน้า
        'margin_bottom_mm' => 31,
        'margin_footer_mm' => 3,
        // ฟอนต์ตามต้นแบบ: Angsana New (มี fallback เป็น TH Sarabun New ถ้าไม่มีไฟล์)
        'font' => env('QUOTE_FONT', 'angsananew'),
        // ฟอนต์อักษรจีน (ฟอร์ม FA-SA-CN): SimSun จาก Windows (resources/fonts/simsun.ttc) — ไม่มีไฟล์ = ใช้ค่าเริ่มต้นของ mPDF
        'cjk_font' => env('QUOTE_CJK_FONT', 'simsun'),
        // สัดส่วนขนาดอักษรจีนเทียบกับข้อความรอบข้าง (SimSun ดูใหญ่กว่า Angsana New) 0.75 = 75%
        'cjk_scale' => (float) env('QUOTE_CJK_SCALE', 0.8),
        // สีพื้นแถบหัวข้อบริการ (FA-SA-TH ชมพู, FA-SA-CN เขียว override ใน forms)
        'subject_fill' => '#E6B8B7',
        'font_size' => 12,
        // จำนวนแถวรายการขั้นต่ำที่แสดงในตาราง (ต้นแบบมี 3 แถว) — ใช้เมื่อเอกสารจบในหน้าเดียว
        'min_item_rows' => (int) env('QUOTE_MIN_ITEM_ROWS', 0),
        // เติมแถวว่างในตารางรายการให้ยาวพอดีกรอบโดยวัดพื้นที่จริง (false = ใช้ min_item_rows แทน)
        'fill_item_rows' => (bool) env('QUOTE_FILL_ITEM_ROWS', false),
        // ความสูงแถวว่าง 1 แถว (mm) = 25.4pt ใช้คำนวณจำนวนแถวที่เติม
        'item_row_height_mm' => 9.0,
        // หน้า 1 แสดงเฉพาะรายการสินค้า/ยอดรวม แล้วให้เงื่อนไข (สิทธิประโยชน์ฯ) + ส่วนลงนามขึ้นหน้า 2
        // (ถ้า Quote ไม่มีเงื่อนไข ส่วนลงนามจะอยู่หน้า 1)
        'terms_on_new_page' => (bool) env('QUOTE_TERMS_ON_NEW_PAGE', true),
        // ความสูงของส่วนปิดท้าย (ข้อความ + การยืนยัน + กล่องลงนาม) หน่วย mm — ใช้ตัดสินว่าจะขึ้นหน้าใหม่ก่อนวางหรือไม่
        'closing_height_mm' => 67,
        // วาดกรอบรอบเนื้อหาทุกหน้า
        'frame' => (bool) env('QUOTE_FRAME', false),
    ],
];
