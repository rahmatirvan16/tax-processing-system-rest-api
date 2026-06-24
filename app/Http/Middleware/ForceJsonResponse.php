<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Paksa setiap request API dianggap meminta JSON.
     *
     * Tanpa ini, saat autentikasi gagal middleware Authenticate Laravel
     * mencoba redirect ke route('login') (yang tidak ada) sehingga muncul
     * 500 RouteNotFoundException, bukan 401 JSON.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
