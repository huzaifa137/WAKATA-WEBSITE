<?php

// WAKATA_PLACEHOLDER_PDFS: true/false. Left empty it is on everywhere except production.
$placeholderPdfs = env('WAKATA_PLACEHOLDER_PDFS');

return [
    'name'        => 'WAKATA',
    'description' => "WAKATA, the Wakiso Kampala Teachers' Association: mock examinations, past papers, practical workbooks and holiday packages. Affordable quality assessment.",
    'phone'       => '+256 700 000 000',
    'phone_tel'   => '+256700000000',
    'whatsapp'    => '256700000000',
    'email'       => 'info@wakatastationers.com',
    'hours'       => 'Monday to Saturday, 8am to 6pm',
    'address'     => 'Wakiso and Kampala, Uganda',

    // Checkout (module 5). Fees are in UGX. The server decides the fee from the chosen method, never from the browser.
    'delivery' => [
        'pickup'    => ['label' => 'Pick up from our shop', 'fee' => 0,     'eta' => 'Ready within one working day'],
        'kampala'   => ['label' => 'Kampala and Wakiso',    'fee' => 8000,  'eta' => '1 to 2 days'],
        'upcountry' => ['label' => 'Upcountry courier',     'fee' => 15000, 'eta' => '2 to 4 days'],
        'email'     => ['label' => 'Email delivery (PDF)',  'fee' => 0,     'eta' => 'On payment confirmation'],
    ],
    'payments' => [
        'mtn'    => 'MTN Mobile Money',
        'airtel' => 'Airtel Money',
        'card'   => 'Visa or Mastercard',
        'cash'   => 'Cash on pickup',
    ],
    // Optional: staff address that gets a copy of every new order (WAKATA_ORDER_NOTIFY in .env).
    'order_notify' => env('WAKATA_ORDER_NOTIFY'),

    // Customer portal (module 6)
    // How many times a customer can download each PDF of an order. Support can raise it for one file (file_downloads.max_downloads).
    'download_limit' => 5,
    // Optional: staff address told when a customer writes in from Help and support. Falls back to WAKATA_ORDER_NOTIFY.
    'support_notify' => env('WAKATA_SUPPORT_NOTIFY') ?: env('WAKATA_ORDER_NOTIFY'),
    // While a paper has no PDF uploaded yet, hand out a small licence sheet instead of the real file, so the download flow can be tried.
    // On by default everywhere except production; in production a customer is told the file is still being prepared (and no download is used).
    'placeholder_pdfs' => ($placeholderPdfs === null || $placeholderPdfs === '') ? env('APP_ENV') !== 'production' : filter_var($placeholderPdfs, FILTER_VALIDATE_BOOLEAN),

    // Password for the seeded admin (and the local demo customer). Set WAKATA_SEED_PASSWORD in .env.
    'seed_password' => env('WAKATA_SEED_PASSWORD'),
];
