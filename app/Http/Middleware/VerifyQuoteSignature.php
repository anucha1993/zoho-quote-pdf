<?php

namespace App\Http\Middleware;

use App\Support\QuoteLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ตรวจสอบ signature ใน URL /quote/{id}.{sig}
 * ถ้าไม่ตรง → 404 โดยไม่บอกเหตุผล (ไม่ให้เดาได้ว่า id มีอยู่หรือไม่)
 */
class VerifyQuoteSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) $request->route('id');
        $sig = (string) $request->route('sig');

        if ($id === '' || $sig === '' || ! QuoteLink::verify($id, $sig)) {
            abort(404);
        }

        return $next($request);
    }
}
