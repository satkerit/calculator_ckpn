<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    /**
     * Set standard OWASP security headers on all responses.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // shortcut: CSP dilewati di lokal agar Vite dev server (HMR/eval Alpine) tidak diblokir;
        // tambahkan origin dev ke script-src bila perlu menguji CSP di lokal.
        if (! app()->isLocal()) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; "
                ."script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; "
                ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net; "
                ."font-src 'self' data: https://fonts.bunny.net; "
                ."img-src 'self' data:; "
                ."connect-src 'self'; "
                ."object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'"
            );
        }

        return $response;
    }
}
