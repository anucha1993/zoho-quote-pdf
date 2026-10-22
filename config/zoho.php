<?php

/*
|--------------------------------------------------------------------------
| การตั้งค่าเชื่อมต่อ Zoho CRM (Data center: US)
|--------------------------------------------------------------------------
| ค่าลับทั้งหมด (client_id / client_secret / refresh_token) อ่านจาก .env เท่านั้น
| ห้ามนำไปแสดงฝั่ง client หรือ commit ลง git
*/

return [

    // URL สำหรับขอ access token (US DC)
    'accounts_url' => env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.com'),

    // URL ของ CRM API (US DC)
    'api_url' => env('ZOHO_API_URL', 'https://www.zohoapis.com'),

    // เวอร์ชัน API
    'api_version' => env('ZOHO_API_VERSION', 'v8'),

    // Org ID ของ CRM (ใช้ประกอบ log / ตรวจสอบเท่านั้น)
    'org_id' => env('ZOHO_ORG_ID', '853559971'),

    // OAuth Self Client
    'client_id' => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'refresh_token' => env('ZOHO_REFRESH_TOKEN'),

    // เวลาที่ cache access token (วินาที) — Zoho ให้อายุ 60 นาที จึง cache ไว้ ~55 นาที
    'token_cache_ttl' => (int) env('ZOHO_TOKEN_CACHE_TTL', 55 * 60),
    'token_cache_key' => 'zoho:crm:access_token',

    // timeout ของ HTTP request (วินาที)
    'timeout' => (int) env('ZOHO_HTTP_TIMEOUT', 20),

    // ดึงข้อมูลที่เกี่ยวข้องเพิ่มเติมหรือไม่ (เพิ่ม API call ต่อ 1 Quote)
    'fetch_related' => [
        'account' => (bool) env('ZOHO_FETCH_ACCOUNT', true),
        'contact' => (bool) env('ZOHO_FETCH_CONTACT', true),
        'owner' => (bool) env('ZOHO_FETCH_OWNER', true),
        // พนักงานจาก lookup "รหัสพนักงาน" (ใช้ดึงชื่อเล่น)
        'employee' => (bool) env('ZOHO_FETCH_EMPLOYEE', true),
    ],

    /*
    | API name ของ field ใน module Quotes
    | ปรับให้ตรงกับ CRM จริง (ดูได้จาก Setup > Developer Space > APIs > API Names)
    */
    'fields' => [
        'quote_number' => 'Quote_Number',
        'subject' => 'Subject',
        'valid_till' => 'Valid_Till',
        'quote_stage' => 'Quote_Stage',
        'owner' => 'Owner',
        'account' => 'Account_Name',
        'contact' => 'Contact_Name',
        'deal' => 'Deal_Name',
        'billing' => [
            'street' => 'Billing_Street',
            'city' => 'Billing_City',
            'state' => 'Billing_State',
            'code' => 'Billing_Code',
            'country' => 'Billing_Country',
        ],
        'sub_total' => 'Sub_Total',
        'discount' => 'Discount',
        'tax' => 'Tax',
        'adjustment' => 'Adjustment',
        'grand_total' => 'Grand_Total',
        'terms' => 'Terms_and_Conditions',
        'description' => 'Description',
        'modified_time' => 'Modified_Time',
        'created_time' => 'Created_Time',

        // เลขที่เอกสาร ${Quotes.Quote Number} = field "Quotation Number" (formula) เช่น "IO2026-021613"
        // ใช้แสดงในฟอร์มและเป็นชื่อไฟล์ PDF — ถ้าว่างจะลองตามลำดับใน number_fallback
        'document_number' => env('ZOHO_FIELD_QUOTE_NUMBER', 'Quotation_Number'),
        'number_fallback' => ['Subject', 'Quote_Number'],

        // Subform รายการสินค้า — ป้ายชื่อ "QT Thailand" ใน org นี้คือ subform มาตรฐาน Quoted_Items
        'items' => env('ZOHO_ITEMS_SUBFORM', 'Quoted_Items'),
        'items_fallback' => ['QT_Thailand', 'Product_Details'],
        'item' => [
            'product' => 'Product_Name',       // lookup => ['id','name','Product_Code']
            'description' => 'Description',    // บางระบบใช้ Product_Description
            'quantity' => 'Quantity',
            'list_price' => 'List_Price',
            'discount' => 'Discount',
            'tax' => 'Tax',
            'total' => 'Total',
            'net_total' => 'Net_Total',
            // ---- custom fields (ยืนยัน API name จริงก่อนใช้งาน) ----
            'recurring_amount' => env('ZOHO_ITEM_RECURRING_FIELD', 'Recurring_Amount'),
            'unit' => env('ZOHO_ITEM_UNIT_FIELD', 'Unit'),
        ],

        /*
        | custom fields ระดับ Quote ที่ฟอร์มต้นแบบใช้ (merge field ใน Excel → API name)
        | ป้ายชื่อใน CRM เป็นภาษาไทย Zoho จะตั้ง API name ให้อัตโนมัติ
        | ต้องเปิดดูที่ Setup > Developer Space > APIs > API Names > Quotes แล้วแก้ที่ .env
        */
        /*
        | merge token ที่พนักงานพิมพ์ไว้ในข้อความ rich text เช่น «Quotes.เงื่อนไขการชำระเงิน» หรือ ${Quotes.Account Name}
        | (เดิม Zoho Writer แทนค่าให้) → ระบบจะแทนด้วยค่าจาก field ตามป้ายชื่อนี้
        | key = ป้ายชื่อ field ใน CRM, value = API name
        */
        'merge_labels' => [
            'เงื่อนไขการชำระเงิน' => 'field1',
            'Account Name' => 'Account_Name',
            'Contact Name' => 'Contact_Name',
            'Quote Number' => 'Quotation_Number',
            'Quotation Number' => 'Quotation_Number',
            'Subject' => 'Subject',
            'ชื่อจริง' => 'First_Name',
            'นามสกุล' => 'Last_Name',
            'เบอร์โทรศัพท์' => 'Sales_Mobile',
            'วันที่เสนอราคา' => 'field2',
            'หัวข้อการให้บริการ' => 'field6',
            'ISO' => 'ISO',
            'งวดที่ 1' => 'Installment_1',
            'งวดที่ 2' => 'Installment_2',
            'งวดที่ 3' => 'Installment_3',
            'รวมทุกงวด' => 'field4',
        ],

        // ค่าเริ่มต้นคือ API name จริงของ org 853559971 (ตรวจจาก settings/fields เมื่อ 2026-10-22)
        'custom' => [
            // ${Quotes.วันที่เสนอราคา}  (ถ้าว่างจะใช้ Created_Time)
            'quote_date' => env('ZOHO_FIELD_QUOTE_DATE', 'field2'),
            // ${Quotes.หัวข้อการให้บริการ}  (ถ้าว่างจะเว้นว่างตามต้นแบบ)
            'service_subject' => env('ZOHO_FIELD_SERVICE_SUBJECT', 'field6'),
            // ${Quotes.ชื่อจริง} ${Quotes.นามสกุล}  (ถ้าว่างจะใช้ชื่อ Owner)
            'sales_first_name' => env('ZOHO_FIELD_SALES_FIRST_NAME', 'First_Name'),
            'sales_last_name' => env('ZOHO_FIELD_SALES_LAST_NAME', 'Last_Name'),
            // ${Lookup:รหัสพนักงาน.ชื่อเล่น} — lookup Sales_Code → module Staffs → field Nickname
            'employee_lookup' => env('ZOHO_FIELD_EMPLOYEE_LOOKUP', 'Sales_Code'),
            'employee_module' => env('ZOHO_EMPLOYEE_MODULE', 'Staffs'),
            'employee_nickname' => env('ZOHO_FIELD_EMPLOYEE_NICKNAME', 'Nickname'),
            // ${Quotes.เงื่อนไข และรายละเอียการชำระเงิน}  (ถ้าว่างจะใช้ Terms_and_Conditions)
            'payment_terms' => env('ZOHO_FIELD_PAYMENT_TERMS', 'field7'),
            // ${Quotes.ISO}  รหัสแบบฟอร์ม (picklist, ถ้าว่างจะใช้ config quote.form_code)
            'iso' => env('ZOHO_FIELD_ISO', 'ISO'),
        ],
    ],
];
