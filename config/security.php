<?php

/**
 * Where the pages may load things from (Content-Security-Policy, see App\Http\Middleware\SecurityHeaders).
 * When you add a payment gateway (Pesapal, Marz Pay ...) that redirects to its own page or opens a frame or form,
 * add its address under 'form' or 'frame' here.
 */
return [
    // enforce | report | off
    'csp' => env('SECURITY_CSP', 'enforce'),

    'sources' => [
        'script'  => ['https://www.googletagmanager.com'],
        'style'   => ['https://fonts.googleapis.com'],
        'font'    => ['https://fonts.gstatic.com'],
        'img'     => ['https://www.google-analytics.com', 'https://*.googletagmanager.com'],
        'connect' => ['https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://*.googletagmanager.com'],
        'frame'   => ['https://www.google.com'],
        'form'    => [],
    ],
];
