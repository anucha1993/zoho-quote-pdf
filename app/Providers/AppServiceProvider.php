<?php

namespace App\Providers;

use App\Services\ZohoCrmService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ZohoCrmService เป็น singleton และรับ config จาก config/zoho.php
        $this->app->singleton(ZohoCrmService::class, function ($app) {
            return new ZohoCrmService($app['config']->get('zoho'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limit ต่อ IP สำหรับ endpoint PDF (ค่าเริ่มต้น 30 req/นาที)
        RateLimiter::for('quote-pdf', function (Request $request) {
            return Limit::perMinute((int) config('quote.rate_limit_per_minute', 30))
                ->by($request->ip())
                ->response(fn () => response('Too Many Requests', 429));
        });
    }
}
