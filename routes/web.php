<?php

use App\Http\Controllers\QuoteController;
use App\Http\Middleware\VerifyQuoteSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Quote PDF routes
|--------------------------------------------------------------------------
| GET  /quote/{id}.{sig}            แสดง PDF inline
| GET  /quote/{id}.{sig}?download=1 ดาวน์โหลด PDF
| POST /quote/{id}.{sig}/attach     สร้าง PDF แล้วแนบกลับเข้า Quote ใน CRM
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

Route::get('/', fn () => response()->noContent());
