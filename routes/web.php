<?php

use App\Http\Controllers\QuoteController;
use App\Http\Middleware\VerifyQuoteKey;
use App\Http\Middleware\VerifyQuoteSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Quote PDF routes  (โดเมน pdf.trackingmouthefirst.com)
|--------------------------------------------------------------------------
| แบบสั้น (เปิดด้วย QUOTE_ALLOW_UNSIGNED=true) — ใช้กับปุ่ม "Open a URL" ใน CRM:
|   GET  /{id}                แสดง PDF inline      เช่น /6274719000184443042
|   GET  /{id}?download=1     ดาวน์โหลด PDF
|   POST /{id}/attach         แนบ PDF เข้า Quote   (ต้องส่ง header X-Quote-Key = QUOTE_LINK_SECRET)
|
| แบบมี signature (เปิดเสมอ) — สำหรับลิงก์ที่ส่งให้บุคคลภายนอก:
|   GET  /quote/{id}.{sig}            แสดง PDF inline
|   GET  /quote/{id}.{sig}?download=1 ดาวน์โหลด PDF
|   POST /quote/{id}.{sig}/attach     แนบ PDF เข้า Quote
|
| - id  = Zoho record ID (ตัวเลข)
| - sig = HMAC-SHA256 hex (ตรวจใน middleware, ผิด → 404)
| - rate limit ต่อ IP กำหนดใน AppServiceProvider (limiter "quote-pdf")
*/
Route::middleware(['throttle:quote-pdf', VerifyQuoteSignature::class])
    ->where(['id' => '[0-9]+', 'sig' => '[A-Fa-f0-9]{16,64}'])
    ->group(function () {
        Route::get('/quote/{id}.{sig}', [QuoteController::class, 'show'])->name('quote.show');
        Route::post('/quote/{id}.{sig}/attach', [QuoteController::class, 'attach'])->name('quote.attach');
    });

if (config('quote.allow_unsigned')) {
    Route::middleware('throttle:quote-pdf')
        ->where(['id' => '[0-9]{10,25}'])
        ->group(function () {
            Route::get('/{id}', [QuoteController::class, 'show'])->name('quote.short');
            Route::post('/{id}/attach', [QuoteController::class, 'attach'])
                ->middleware(VerifyQuoteKey::class)
                ->name('quote.short.attach');
        });
}

Route::get('/', fn () => response()->noContent());
