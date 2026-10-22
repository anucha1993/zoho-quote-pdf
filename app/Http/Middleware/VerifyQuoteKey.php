<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ป้องกัน endpoint ที่เขียนข้อมูลกลับ CRM (POST /{id}/attach) บน route แบบไม่มี signature
 * ผู้เรียก (Deluge invokeurl) ต้องส่ง header X-Quote-Key หรือ ?key= ให้ตรงกับ QUOTE_LINK_SECRET
 */
class VerifyQuoteKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('quote.link_secret');
        $given = (string) ($request->header('X-Quote-Key') ?? $request->query('key', ''));

        if ($secret === '' || $given === '' || ! hash_equals($secret, $given)) {
            abort(404);
        }

        return $next($request);
    }
}
