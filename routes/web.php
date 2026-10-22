<?php

use App\Http\Controllers\QuoteController;
use App\Http\Middleware\VerifyQuoteKey;
use App\Http\Middleware\VerifyQuoteSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Quote PDF routes  (โดเมน pdf.trackingmouthefirst.com)
|--------------------------------------------------------------------------
| {form} = slug ของแบบฟอร์ม เช่น FA-SA-TH (ดู config/quote.php → forms)
| {id}   = Zoho record ID (ตัวเลข)
|
| แบบสั้น (เปิดด้วย QUOTE_ALLOW_UNSIGNED=true) — ใช้กับปุ่ม "Open a URL" ใน CRM:
|   GET  /{form}/{id}             แสดง PDF inline   เช่น /FA-SA-TH/6274719000184443042
|   GET  /{form}/{id}?download=1  ดาวน์โหลด PDF
|   POST /{form}/{id}/attach      แนบ PDF เข้า Quote (ต้องส่ง header X-Quote-Key = QUOTE_LINK_SECRET)
|   GET  /{id}                    ใช้ฟอร์มเริ่มต้น (quote.default_form)
|
| แบบมี signature (เปิดเสมอ) — สำหรับลิงก์ที่ส่งให้บุคคลภายนอก:
|   GET  /quote/{form}/{id}.{sig}            แสดง PDF inline
|   GET  /quote/{form}/{id}.{sig}?download=1 ดาวน์โหลด PDF
|   POST /quote/{form}/{id}.{sig}/attach     แนบ PDF เข้า Quote
|   GET  /quote/{id}.{sig}                   ใช้ฟอร์มเริ่มต้น
|
| sig = HMAC-SHA256(id) hex (ตรวจใน middleware, ผิด → 404)
| rate limit ต่อ IP กำหนดใน AppServiceProvider (limiter "quote-pdf")
*/

$idPattern = '[0-9]{10,25}';
$formPattern = '[A-Za-z0-9][A-Za-z0-9_-]{0,40}';
$sigPattern = '[A-Fa-f0-9]{16,64}';

// ---------- แบบมี signature ----------
Route::middleware(['throttle:quote-pdf', VerifyQuoteSignature::class])
    ->where(['id' => $idPattern, 'sig' => $sigPattern, 'form' => $formPattern])
    ->group(function () {
        Route::get('/quote/{form}/{id}.{sig}', [QuoteController::class, 'show'])->name('quote.form.show');
        Route::post('/quote/{form}/{id}.{sig}/attach', [QuoteController::class, 'attach'])->name('quote.form.attach');

        Route::get('/quote/{id}.{sig}', [QuoteController::class, 'show'])->name('quote.show');
        Route::post('/quote/{id}.{sig}/attach', [QuoteController::class, 'attach'])->name('quote.attach');
    });

// ---------- แบบสั้น ----------
if (config('quote.allow_unsigned')) {
    Route::middleware('throttle:quote-pdf')
        ->where(['id' => $idPattern, 'form' => $formPattern])
        ->group(function () {
            Route::get('/{form}/{id}', [QuoteController::class, 'show'])->name('quote.form.short');
            Route::post('/{form}/{id}/attach', [QuoteController::class, 'attach'])
                ->middleware(VerifyQuoteKey::class)
                ->name('quote.form.short.attach');

            // หน้าเลือกแบบฟอร์ม (รายการทุกฟอร์ม) — ต้องประกาศก่อน /{form}/{id} จึงจะไม่ชนกัน
            Route::get('/{id}/forms', [QuoteController::class, 'pick'])->name('quote.pick');

            Route::get('/{id}', [QuoteController::class, 'show'])->name('quote.short');
            Route::post('/{id}/attach', [QuoteController::class, 'attach'])
                ->middleware(VerifyQuoteKey::class)
                ->name('quote.short.attach');
        });
}

Route::get('/', fn () => response()->noContent());
