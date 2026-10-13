<?php

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

    // Password for the seeded admin (and the local demo customer). Set WAKATA_SEED_PASSWORD in .env.
    'seed_password' => env('WAKATA_SEED_PASSWORD'),
];
