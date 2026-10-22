# การตั้งค่าฝั่ง Zoho CRM

โดเมนระบบ: `https://pdf.trackingmouthefirst.com`

## วิธีที่ 1 (แนะนำ, ไม่ต้องเขียนโค้ด): Custom Button "Open a URL"

ใช้ได้เมื่อ `.env` ตั้ง `QUOTE_ALLOW_UNSIGNED=true`

1. **Setup → Customization → Modules and Fields → Quotes → Buttons → + New Button**
2. ตั้งค่า
   - Button name: `Print PDF`
   - Where: **Detail Page** (หรือ View Page / List view ตามต้องการ)
   - Action: **Open a URL**
   - URL (รูปแบบ `/{ชื่อฟอร์ม}/{record id}`):
     ```
     https://pdf.trackingmouthefirst.com/FA-SA-TH/${Quotes.Quote Id}
     ```
   - Open in: **New window**
3. กด Save แล้วเลือก profile ที่มองเห็นปุ่ม

ผลลัพธ์: กดปุ่มแล้วเปิด PDF ทันที ระบบจะดึงข้อมูลล่าสุดจาก CRM สร้าง PDF และ cache ไว้
จนกว่า Quote จะถูกแก้ไข (Modified Time เปลี่ยน) หรือ template ถูกอัปเดต

**หลายฟอร์ม — เลือกได้ 3 แบบ (ใช้ร่วมกันได้):**

| แบบ | URL ในปุ่ม | เหมาะกับ |
|---|---|---|
| **A. อัตโนมัติ** (แนะนำ) | `https://pdf.trackingmouthefirst.com/auto/${Quotes.Quote Id}` | ปุ่มเดียวทุกฟอร์ม ระบบเลือกจากฟิลด์ **ISO** ของ Quote ตามตาราง `auto_form.map` ใน `config/quote.php` (รองรับ `*` เช่น `FA-SA-02-*-CN => FA-SA-CN`) ไม่ตรงใช้ฟอร์มเริ่มต้น |
| **B. หน้าเลือกฟอร์ม** | `https://pdf.trackingmouthefirst.com/${Quotes.Quote Id}/forms` | กดปุ่มแล้วเห็นรายการฟอร์มทั้งหมดพร้อมปุ่ม "เปิด PDF / ดาวน์โหลด" และไฮไลต์ฟอร์มที่ระบบแนะนำ (dropdown ฝั่งเว็บ ไม่ต้องแก้ CRM เมื่อเพิ่มฟอร์ม) |
| **C. Picklist ใน CRM** | `https://pdf.trackingmouthefirst.com/${Quotes.PDF Form}/${Quotes.Quote Id}` | สร้างฟิลด์ picklist ชื่อ `PDF Form` ค่าเป็น slug (`FA-SA-TH`, `FA-SA-CN`, …) ให้พนักงานเลือกในหน้า Quote แล้วปุ่มเดียวใช้ค่านั้น |

จะสร้างปุ่มแยกต่อฟอร์ม (`/FA-SA-CN/${Quotes.Quote Id}`) ก็ยังได้เหมือนเดิม
รายชื่อฟอร์มที่มี = ไฟล์ใน `resources/views/pdf/forms/` (ดู README หัวข้อ "เพิ่มแบบฟอร์มใหม่")

ถ้าต้องการให้ **ดาวน์โหลด** แทนเปิดในแท็บ ใส่ `?download=1` ต่อท้าย:
```
https://pdf.trackingmouthefirst.com/FA-SA-TH/${Quotes.Quote Id}?download=1
```

ถ้าไม่ระบุชื่อฟอร์ม (`https://pdf.trackingmouthefirst.com/${Quotes.Quote Id}`) จะใช้ฟอร์มเริ่มต้น
ตาม `QUOTE_DEFAULT_FORM` (ค่าเริ่มต้น FA-SA-TH)

### (ทางเลือก) เก็บ URL ไว้ใน field เพื่อส่งอีเมล/แสดงในหน้า record

สร้าง field ชนิด **URL** ชื่อ `Quote PDF URL` (API name `Quote_PDF_URL`) แล้วตั้ง
**Workflow Rule** (Quotes, On Create) → Action: **Field Update**
ตั้งค่า `Quote PDF URL` = `https://pdf.trackingmouthefirst.com/${Quotes.Quote Id}`
(ไม่ต้องใช้ Deluge เลย เพราะไม่ต้องคำนวณ signature)

## ฟอร์มใบงาน (module Deals): Deal Information & Tracking

สร้าง Custom Button ใน **Deals** (ไม่ใช่ Quotes) แบบ Invoke a URL:
```
https://pdf.trackingmouthefirst.com/TRACKING/${Deals.Deal Id}
```
ฟอร์มนี้ดึงข้อมูลจาก Deal (ชื่อบริษัท ผู้ติดต่อ Job Code จำนวนแรงงาน ค่าใช้จ่าย) + subform Tracking System
และไม่ปรากฏในหน้าเลือกฟอร์มของใบเสนอราคา

## วิธีที่ 2 (ปลอดภัยกว่า): ลิงก์แบบมี signature

เหมาะกับลิงก์ที่ส่งให้ลูกค้าภายนอก เพราะเดา URL ไม่ได้แม้รู้ record ID
ใช้ Deluge ใน `workflow_set_quote_pdf_url.dg` และ `button_print_quote.dg`
รูปแบบ URL: `https://pdf.trackingmouthefirst.com/quote/{id}.{signature}`

## ปุ่ม "แนบ PDF เข้า Quote" (ทางเลือก)

ให้ Laravel สร้าง PDF แล้วแนบเข้า Attachments ของ Quote โดยไม่เสียเครดิต Zoho Writer
สร้าง Custom Button → **Writing Function** → argument `quoteId` = `${Quotes.Quote Id}`:

```deluge
string attachQuotePdf(Int quoteId)
{
    // ค่าเดียวกับ QUOTE_LINK_SECRET ใน .env — เก็บใน Organization Variable "quote_link_secret"
    secret = zoho.crm.getOrgVariable("quote_link_secret");

    headers = Map();
    headers.put("X-Quote-Key", secret);

    resp = invokeurl
    [
        url : "https://pdf.trackingmouthefirst.com/FA-SA-TH/" + quoteId.toString() + "/attach"
        type : POST
        headers : headers
    ];
    info resp;

    if (resp.get("ok") == true)
    {
        return "แนบไฟล์ " + resp.get("filename") + " เรียบร้อย";
    }
    return "แนบไฟล์ไม่สำเร็จ: " + ifnull(resp.get("message"), resp.toString());
}
```

## สิ่งที่ Zoho ไม่ต้องตั้งค่า

- ไม่ต้อง whitelist โดเมน: ปุ่ม Open a URL เปิดลิงก์ใดก็ได้
- ไม่ต้องสร้าง Connection/OAuth ใน Deluge: ฝั่ง Laravel เป็นคนเรียก Zoho API เองด้วย Self Client
- ไม่ต้องตั้ง CORS: เป็นการเปิดหน้า PDF ตรง ๆ ไม่ใช่ AJAX

## สิ่งที่ต้องมีฝั่งเซิร์ฟเวอร์

- DNS: A record `pdf.trackingmouthefirst.com` → IP เซิร์ฟเวอร์
- HTTPS (Let's Encrypt) เพราะ Zoho CRM เป็น https หากเปิดลิงก์ http browser จะเตือน
- `.env`: `APP_URL` และ `QUOTE_BASE_URL` = `https://pdf.trackingmouthefirst.com`, `QUOTE_ALLOW_UNSIGNED=true`
