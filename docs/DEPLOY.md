# Deploy บน pdf.trackingmouthefirst.com (Plesk + nginx)

## 1. ตั้งค่าโดเมนใน Plesk

- **Hosting Settings → Document root** = `/httpdocs/public` (ต้องชี้ไปที่โฟลเดอร์ `public` ของ Laravel ไม่ใช่ root โปรเจกต์)
- **PHP Settings**: PHP 8.2+ (เครื่อง dev ใช้ 8.3), เปิด extension `mbstring`, `gd`, `curl`, `fileinfo`, `zip`, `openssl`
- `memory_limit` อย่างน้อย 256M (mPDF ใช้ RAM ตอนสร้างฟอนต์ cache ครั้งแรก)
- **SSL/TLS**: ออก Let's Encrypt ให้โดเมน และเปิด redirect http → https
- **Apache & nginx Settings** (ถ้า nginx เป็น proxy ให้ Apache ไม่ต้องแก้; ถ้าใช้ nginx อย่างเดียว ใส่ใน "Additional nginx directives"):
  ```nginx
  location / {
      try_files $uri $uri/ /index.php?$query_string;
  }
  ```

## 2. อัปโหลดโค้ด

อัปโหลดทั้งโปรเจกต์ (ยกเว้น `vendor/`, `node_modules/`) แล้วรันใน SSH / Plesk terminal:

```bash
cd /var/www/vhosts/trackingmouthefirst.com/pdf.trackingmouthefirst.com   # path จริงของโดเมน
composer install --no-dev --optimize-autoloader
cp .env.example .env          # ครั้งแรกเท่านั้น
php artisan key:generate
```

โฟลเดอร์ที่ต้องเขียนได้ (owner = user ของ Plesk, เช่น `psacln`):
```bash
chmod -R ug+rwX storage bootstrap/cache
mkdir -p storage/app/quotes storage/app/mpdf storage/framework/{cache,sessions,views} storage/logs
```

## 3. `.env` ที่ต้องถูกต้อง (จุดที่ทำให้ 500 / 404 บ่อยที่สุด)

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pdf.trackingmouthefirst.com

# ระบบนี้ไม่ใช้ database — ห้ามปล่อยเป็น database (ค่าเริ่มต้นของ Laravel 12) ไม่งั้นทุก route จะ 500
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

QUOTE_BASE_URL=https://pdf.trackingmouthefirst.com
QUOTE_ALLOW_UNSIGNED=true       # ถ้าไม่มีบรรทัดนี้ URL แบบ /{id} จะ 404
QUOTE_LINK_SECRET=...           # ค่าเดียวกับเครื่อง dev

ZOHO_CLIENT_ID=...
ZOHO_CLIENT_SECRET=...
ZOHO_REFRESH_TOKEN=...
```
คัดลอกบรรทัด `ZOHO_FIELD_*`, `ZOHO_ITEMS_SUBFORM`, `QUOTE_FONT`, `QUOTE_APPROVER_*` จาก `.env.example` มาด้วย

## 4. หลังแก้ `.env` ทุกครั้ง

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize        # สร้าง cache ใหม่ (config + route)
```
ถ้าเคยรัน `config:cache` / `route:cache` ไว้ก่อนเพิ่ม `QUOTE_ALLOW_UNSIGNED` route แบบสั้นจะไม่ปรากฏจนกว่าจะ clear

## 5. ตรวจสอบ

```bash
php artisan about                      # ดู env/driver ที่โหลดจริง
php artisan route:list --path=          # ต้องเห็น quote.short และ quote.show
php artisan quotes:link 6274719000184443042
curl -I https://pdf.trackingmouthefirst.com/6274719000184443042   # ต้องได้ 200 application/pdf
tail -50 storage/logs/laravel.log       # ถ้ายัง 500 ดู error ที่นี่
tail -50 storage/logs/zoho-*.log        # ปัญหาฝั่ง Zoho (token, field)
```

อาการที่พบบ่อย:

| อาการ | สาเหตุ | แก้ |
|---|---|---|
| `/up` 200 แต่ `/` 500 | SESSION/CACHE เป็น database แต่ไม่มี DB | ตั้ง `SESSION_DRIVER=file`, `CACHE_STORE=file` แล้ว `config:clear` |
| `/{id}` 404 แต่ `/quote/{id}.{sig}` ใช้ได้ | ไม่มี `QUOTE_ALLOW_UNSIGNED=true` หรือ route cache เก่า | เพิ่มค่าแล้ว `route:clear` |
| 502 | Zoho token/credential ผิด หรือ scope ไม่พอ | ดู `storage/logs/zoho-*.log` |
| 500 ตอนสร้าง PDF ครั้งแรก | `storage/app/mpdf` เขียนไม่ได้ หรือไม่มี GD | chmod / เปิด extension gd |
| กล่องสี่เหลี่ยมในข้อความไทย | ใช้ฟอนต์ที่ไม่มี glyph U+200B | ดู README หัวข้อฟอนต์ |

## 6. Cron ลบไฟล์เก่า (Plesk → Scheduled Tasks)

```
* * * * *  cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```
