<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // endpoint attach ถูกเรียกจาก Zoho (Deluge invokeurl) จึงไม่มี CSRF token
        // ความปลอดภัยอาศัย signature ใน URL แทน
        $middleware->validateCsrfTokens(except: [
            'quote/*',
        ]);

        // รองรับการอยู่หลัง reverse proxy / load balancer (ให้ rate limit เห็น IP จริง)
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
