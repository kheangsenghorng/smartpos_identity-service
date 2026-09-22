<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->remove('X-Powered-By');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->is('docs*') || $request->is('docs/*') || $request->is('horizon*') || $request->is('telescope*') || $request->is('pulse*') || $request->is('livewire*') || $request->is('vendor*')) {
            $response->headers->set('Content-Security-Policy', "default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https: fonts.bunny.net fonts.googleapis.com; font-src 'self' https: fonts.bunny.net fonts.gstatic.com data:; connect-src 'self' https: http: ws: wss:; img-src 'self' data: https:; frame-ancestors 'none';");
        } else {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none';");
        }

        $response->headers->set('Permissions-Policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        if ($request->is('api*') || $request->is('api/*')) {
            $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        } else {
            $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        }

        return $response;
    }
}
