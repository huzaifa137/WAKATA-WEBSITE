<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Module 11: the standard browser protections, on every response.
 *
 *  - frame-ancestors / X-Frame-Options: nobody can put the shop or the admin console inside another site (click-jacking)
 *  - nosniff, Referrer-Policy, Permissions-Policy, and HSTS on https
 *  - a Content-Security-Policy that only lets pages load from this site, Google Fonts, Google Analytics and the Google map
 *  - admin and account pages are never stored by the browser or a shared computer's cache (they hold customer details)
 *
 * The pages use inline scripts and styles, so script-src and style-src keep 'unsafe-inline'; the policy still blocks
 * plug-ins, outside forms, a changed <base>, and any script host that is not listed in config/security.php.
 * SECURITY_CSP=report only reports (nothing is blocked), SECURITY_CSP=off removes the policy.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $h = $response->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), usb=()');

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $mode = (string) config('security.csp', 'enforce');
        if ($mode !== 'off') {
            $h->set($mode === 'report' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy', $this->policy());
        }

        // Pages with personal or business data: never kept in the browser's cache (a file download keeps its own headers)
        if ($request->is('admin', 'admin/*', 'account', 'account/*') && ! $h->has('Content-Disposition')) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function policy(): string
    {
        $c = config('security.sources');

        $directives = [
            'default-src'     => ["'self'"],
            'script-src'      => array_merge(["'self'", "'unsafe-inline'"], $c['script']),
            'style-src'       => array_merge(["'self'", "'unsafe-inline'"], $c['style']),
            'font-src'        => array_merge(["'self'", 'data:'], $c['font']),
            'img-src'         => array_merge(["'self'", 'data:', 'blob:'], $c['img']),
            'connect-src'     => array_merge(["'self'"], $c['connect']),
            'frame-src'       => array_merge(["'self'"], $c['frame']),
            'object-src'      => ["'none'"],
            'base-uri'        => ["'self'"],
            'form-action'     => array_merge(["'self'"], $c['form']),
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)->map(fn ($v, $k) => $k . ' ' . implode(' ', array_unique($v)))->implode('; ');
    }
}
