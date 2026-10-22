# Zoho CRM Quote PDF Generator (Laravel 12 + mPDF)

สร้าง PDF ใบเสนอราคา (แบบฟอร์ม `FA-SA-02-103`) จากข้อมูล Zoho CRM โดยตรง
แทนการใช้ Mail Merge ของ Zoho Writer ซึ่งเสียเครดิตทุกครั้ง

```
https://zoho.trackingmouthefirst.com/quote/{RecordID}.{signature}
https://zoho.trackingmouthefirst.com/quote/{RecordID}.{signature}?download=1
POST https://zoho.trackingmouthefirst.com/quote/{RecordID}.{signature}/attach
```

## โครงสร้างไฟล์ที่เพิ่มจาก Laravel มาตรฐาน

```
app/
  Console/Commands/PurgeQuotePdfs.php      quotes:purge  ลบ PDF เก่ากว่า N วัน (schedule รายวัน)
  Console/Commands/MakeQuoteLink.php       quotes:link   แสดง signature/URL ของ Quote (ใช้เทียบกับ Deluge)
  Exceptions/QuoteNotFoundException.php
  Exceptions/ZohoApiException.php
  Http/Controllers/QuoteController.php     show() / attach()
  Http/Middleware/VerifyQuoteSignature.php ตรวจ HMAC → ผิด 404
  Pdf/ThaiLanguageToFont.php               บังคับ mPDF ใช้ TH Sarabun New กับภาษาไทย
  Providers/AppServiceProvider.php         bind ZohoCrmService + rate limiter "quote-pdf"
  Services/ZohoCrmService.php              OAuth refresh, getQuote, related records, uploadAttachment
  Services/QuotePdfService.php             mPDF render + cache ไฟล์ตาม Modified_Time
  Support/BahtText.php                     ตัวเลข → คำอ่านภาษาไทย
  Support/QuoteData.php                    map field จาก Zoho ให้ view ใช้
  Support/QuoteLink.php                    sign / verify / url
config/zoho.php                            endpoint, OAuth, API name ของ field
config/quote.php                           secret, บริษัท, กระดาษ, ฟอนต์, retention
config/logging.php                         channel "zoho" → storage/logs/zoho-YYYY-MM-DD.log
resources/views/pdf/quote.blade.php        template เอกสาร
resources/fonts/THSarabunNew*.ttf          ฟอนต์ไทย 4 น้ำหนัก
routes/web.php, routes/console.php
docs/deluge/*.dg                           โค้ด Deluge สำหรับ workflow / custom button
tests/Unit/BahtTextTest.php, QuoteLinkTest.php
tests/Feature/QuotePdfTest.php
```

## การติดตั้ง

### 1. Composer และสิทธิ์โฟลเดอร์

```bash
composer install
cp .env.example .env
php artisan key:generate
chmod -R ug+rw storage bootstrap/cache
```

PHP ต้องมี extension `mbstring`, `gd`, `curl`, `fileinfo`, `zip`

### 2. ฟอนต์

ฟอร์มต้นแบบใช้ **Angsana New** จึงมีไฟล์ `AngsanaNew*.ttf` (แตกจาก `C:\Windows\Fonts\angsana.ttc`,
fsType อนุญาตให้ embed ได้) และ **TH Sarabun New** เป็นฟอนต์สำรอง อยู่ใน `resources/fonts/` แล้ว
ถ้าต้องการเปลี่ยนฟอนต์ ให้วางไฟล์ `.ttf` ในโฟลเดอร์เดียวกัน
แล้วแก้ `fontdata` ใน `QuotePdfService::makeMpdf()` และตั้ง `QUOTE_FONT` ใน `.env`

mPDF จะสร้าง cache ของฟอนต์ไว้ที่ `storage/app/mpdf/` ครั้งแรกที่ render (ช้าเล็กน้อย 1 ครั้ง)

**สำคัญ:** mPDF ตัดคำภาษาไทยโดยแทรก U+200B (zero-width space) ระหว่างคำ
ถ้าฟอนต์ไม่มี glyph ของ U+200B จะเห็นกล่องสี่เหลี่ยมแทรกในข้อความไทย
ไฟล์ TH Sarabun New ใน repo ถูก patch ไว้แล้ว ถ้าเปลี่ยนฟอนต์ใหม่ให้รัน:

```bash
pip install fonttools
python scripts/add_zwsp_glyph.py resources/fonts/*.ttf
rm -f storage/app/mpdf/*   # ล้าง cache ฟอนต์ของ mPDF
```

### 3. สร้าง Self Client ใน Zoho API Console

1. เปิด https://api-console.zoho.com (login บัญชีที่เป็น admin ของ org `853559971`)
2. **Add Client → Self Client → Create**
3. แท็บ **Generate Code** ใส่ scope:
   ```
   ZohoCRM.modules.quotes.READ,ZohoCRM.modules.accounts.READ,ZohoCRM.modules.contacts.READ,ZohoCRM.users.READ,ZohoCRM.modules.attachments.CREATE
   ```
   Time duration 10 นาที → **Create** → คัดลอก `code`
4. แลก code เป็น refresh token (ทำภายใน 10 นาที):
   ```bash
   curl -X POST https://accounts.zoho.com/oauth/v2/token \
     -d grant_type=authorization_code \
     -d client_id=XXXX \
     -d client_secret=XXXX \
     -d code=1000.xxxx
   ```
   นำ `refresh_token` จาก response มาใส่ `.env`

### 4. ตั้งค่า `.env`

```dotenv
ZOHO_CLIENT_ID=1000.xxxx
ZOHO_CLIENT_SECRET=xxxx
ZOHO_REFRESH_TOKEN=1000.xxxx

QUOTE_LINK_SECRET=   # php -r "echo bin2hex(random_bytes(32));"
QUOTE_SIGNATURE_LENGTH=64
QUOTE_BASE_URL=https://zoho.trackingmouthefirst.com

COMPANY_ADDRESS="..."
COMPANY_TAX_ID="0105..."
COMPANY_PHONE="02-..."
```

วางโลโก้ที่ `public/images/logo.png` (ถ้าไม่มีจะแสดงชื่อบริษัทแทน)

### 5. ตรวจสอบ API name ของ field

แบบฟอร์มอิง sheet **"คนเมียวดี (เขียว-แดง)"** ใน `ex/FA-SA.xlsx` ซึ่งใช้ merge field ของ Zoho
ค่าเริ่มต้นด้านล่างคือ API name จริงของ org 853559971 (ตรวจจาก API `settings/fields` เมื่อ 22 ต.ค. 2026)
ถ้ามีการเปลี่ยน field ใน CRM ให้แก้ที่ `.env`:

| merge field ในฟอร์ม | ตัวแปร `.env` | API name จริง | ถ้าว่างจะใช้ |
|---|---|---|---|
| `${Quotes.Quote Number}` (เลขที่เอกสาร/ชื่อไฟล์) | `ZOHO_FIELD_QUOTE_NUMBER` | `Quotation_Number` เช่น `IO2026-021613` | Subject → Quote_Number |
| `${Quotes.วันที่เสนอราคา}` | `ZOHO_FIELD_QUOTE_DATE` | `field2` | Created_Time |
| `${Quotes.หัวข้อการให้บริการ}` | `ZOHO_FIELD_SERVICE_SUBJECT` | `field6` | เว้นว่าง (แถบชมพูคงไว้) |
| `${Quotes.ชื่อจริง}` / `${Quotes.นามสกุล}` | `ZOHO_FIELD_SALES_FIRST_NAME` / `..._LAST_NAME` | `First_Name` / `Last_Name` | ชื่อ Owner |
| `${Lookup:รหัสพนักงาน.ชื่อเล่น}` | `ZOHO_FIELD_EMPLOYEE_LOOKUP` / `ZOHO_EMPLOYEE_MODULE` / `ZOHO_FIELD_EMPLOYEE_NICKNAME` | `Sales_Code` → module `Staffs` → `Nickname` | ไม่แสดง |
| `${Quotes.เงื่อนไข และรายละเอียการชำระเงิน}` | `ZOHO_FIELD_PAYMENT_TERMS` | `field7` | Terms_and_Conditions |
| `${Quotes.ISO}` | `ZOHO_FIELD_ISO` | `ISO` (picklist) | `QUOTE_FORM_CODE` |
| `${QT Thailand.*}` (subform) | `ZOHO_ITEMS_SUBFORM` | `Quoted_Items` (S.NO = `Sequence_Number`, Amount = `Total`) | QT_Thailand |
| `${Contacts.Mobile}` | (ดึงจาก Contact อัตโนมัติ) | `Mobile` | Phone |

**ยอดรวมทั้งหมด** คำนวณจากผลรวมช่อง Amount ของทุกรายการ (= `Sub_Total` ก่อน VAT) ตามสูตร `=SUM()` ในต้นแบบ
ไม่ใช้ `Grand_Total` ของ Zoho ซึ่งรวม VAT แล้ว

Scope ที่ Self Client ต้องมี:
```
ZohoCRM.modules.quotes.READ,ZohoCRM.modules.accounts.READ,ZohoCRM.modules.contacts.READ,ZohoCRM.users.READ,ZohoCRM.modules.attachments.CREATE,ZohoCRM.modules.custom.READ,ZohoCRM.settings.fields.READ
```
(`modules.custom.READ` ใช้อ่าน module Staffs เพื่อดึงชื่อเล่น, `settings.fields.READ` ใช้ตรวจ API name)

ค่าอื่นในฟอร์ม:

| ตัวแปร `.env` | ค่าเริ่มต้น | ใช้ทำอะไร |
|---|---|---|
| `QUOTE_APPROVER_NAME` / `QUOTE_APPROVER_TITLE` | นายเฟื่องฟู ชีวินมหรัตน์ / กรรมการผู้จัดการ… | กล่องลงนามขวา |
| `QUOTE_FONT` | `angsananew` | ฟอนต์ตามต้นแบบ (ไม่มีไฟล์จะถอยไป `thsarabunnew`) |
| `QUOTE_SHOW_QR` | `false` | ต้นแบบไม่มี QR ถ้าต้องการให้เปิดเป็น `true` |
| `ZOHO_ITEM_RECURRING_FIELD` / `ZOHO_ITEM_UNIT_FIELD` | `Recurring_Amount` / `Unit` | บรรทัดเสริมใต้ชื่อรายการ (ถ้ามีข้อมูล) |

ภาพหัว/ท้ายกระดาษอยู่ที่ `resources/images/header.jpg`, `footer.jpg` (จาก `ex/`)
ตัวอย่างผลลัพธ์เทียบกับต้นแบบ: `docs/sample-quote.pdf` กับ `docs/template-excel-export.pdf`

### 6. Cron สำหรับลบไฟล์เก่า

```
* * * * * cd /path/to/zoho-quote-pdf && php artisan schedule:run >> /dev/null 2>&1
```

หรือรันเองด้วย `php artisan quotes:purge --days=7`

### 7. ทดสอบ

```bash
php artisan test
php artisan quotes:link 6123456000000123456   # ดู signature/URL
php artisan serve                               # แล้วเปิด URL ที่ได้
```

## แบบฟอร์มหลายแบบ

URL รูปแบบ `https://pdf.trackingmouthefirst.com/{ฟอร์ม}/{record_id}` เช่น `/FA-SA-TH/6274719000184443042`

| ฟอร์ม | ไฟล์ template | หมายเหตุ |
|---|---|---|
| `FA-SA-TH` | `resources/views/pdf/forms/fa-sa-th.blade.php` | ใบเสนอราคาภาษาไทย (ค่าเริ่มต้น) |

**เพิ่มแบบฟอร์มใหม่** (เช่น `FA-SA-CN`):
1. คัดลอก `fa-sa-th.blade.php` เป็น `resources/views/pdf/forms/fa-sa-cn.blade.php` แล้วแก้ layout
   (ชื่อไฟล์ = slug ตัวพิมพ์เล็ก, ใน URL ใช้ตัวพิมพ์ใหญ่/เล็กก็ได้)
2. (ไม่บังคับ) ใส่ override ใน `config/quote.php` → `forms['FA-SA-CN']` เช่น `label`, `form_code`,
   `document` (กระดาษ/ฟอนต์/ขอบ), `texts`, `approver`, `company`
3. ใช้งานได้ทันทีที่ `/FA-SA-CN/{id}` ไม่ต้องแก้ route/controller
4. ทดสอบด้วย `php artisan quotes:link <id> --form=FA-SA-CN`

ตัวแปรทั้งหมดที่ template ใช้ได้: `$q` (ข้อมูล Quote — ดู `app/Support/QuoteData.php`), `$form`,
`$company`, `$approver`, `$texts`, `$doc`, `$formCode`, `$trackingUrl`

## ฝั่ง Zoho CRM

**แบบง่าย (ค่าเริ่มต้น):** เปิด `QUOTE_ALLOW_UNSIGNED=true` แล้วสร้าง Custom Button ใน Quotes
ชนิด **Open a URL** ชี้ไปที่ `https://pdf.trackingmouthefirst.com/FA-SA-TH/${Quotes.Quote Id}`
ไม่ต้องเขียน Deluge รายละเอียดใน `docs/deluge/README.md` (หลายฟอร์ม = หลายปุ่ม เปลี่ยนแค่ชื่อฟอร์มใน URL)

**แบบมี signature (ปลอดภัยกว่า สำหรับส่งลูกค้าภายนอก):**

1. สร้าง field ใน **Quotes**: ชื่อ `Quote PDF URL` ชนิด **URL** (API name `Quote_PDF_URL`)
2. สร้าง **Organization Variable** ชื่อ `quote_link_secret` ค่าเดียวกับ `QUOTE_LINK_SECRET`
3. **Workflow Rule** (Quotes, On Create) → Action: Function → วางโค้ดจาก
   `docs/deluge/workflow_set_quote_pdf_url.dg` (argument `quoteId` = `${Quotes.Quote Id}`)
4. **Custom Button** "Print Quote" (Detail page) → Writing Function → วางโค้ดจาก
   `docs/deluge/button_print_quote.dg`
5. ก่อนเปิดใช้จริง ให้เทียบ signature ของ Quote เดียวกันระหว่าง
   `php artisan quotes:link <id>` กับค่าที่ Deluge สร้าง ถ้าไม่ตรง ให้ตรวจ:
   - ลำดับ parameter ของ `zoho.encryption.hmacsha256(text, key)`
   - output เป็น hex ตัวพิมพ์เล็ก (ไม่ใช่ base64)
   - secret ตรงกันทุกตัวอักษร ไม่มีช่องว่างท้าย

## การทำงานของ cache

- key: `quote:{id}:{Modified_Time}` → ไฟล์ `storage/app/quotes/{id}_{hash}.pdf`
- ทุก request ยังเรียก Zoho 1 ครั้งเพื่ออ่าน `Modified_Time` (ไม่ render ใหม่ถ้าไม่เปลี่ยน)
- ไฟล์เก่าถูกลบโดย `quotes:purge` ตาม `QUOTE_RETENTION_DAYS`

## ความปลอดภัย

- signature = `HMAC-SHA256(record_id, QUOTE_LINK_SECRET)` ตรวจด้วย `hash_equals()`
- ลิงก์ผิดรูปแบบหรือ signature ผิด → 404 เสมอ
- rate limit 30 req/นาที/IP (`QUOTE_RATE_LIMIT`)
- OAuth credential อยู่ใน `.env` เท่านั้น ไม่ส่งออก response ใด ๆ
- endpoint `/attach` ยกเว้น CSRF เพราะถูกเรียกจาก Deluge `invokeurl`
