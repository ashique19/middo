<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment gateway driver
    |--------------------------------------------------------------------------
    |
    | "eps" — Easy Payment System (eps.com.bd) hosted checkout.
    | "pseudo" — local confirm button for automated tests / offline demos.
    |
    */
    'driver' => env('PAYMENT_GATEWAY_DRIVER', 'eps'),

    /*
    |--------------------------------------------------------------------------
    | EPS (Easy Payment System)
    |--------------------------------------------------------------------------
    |
    | Sandbox API: https://sandboxpgapi.eps.com.bd
    | Production API: https://pgapi.eps.com.bd
    |
    */
    'eps' => [
        'sandbox' => filter_var(env('EPS_SANDBOX', true), FILTER_VALIDATE_BOOL),
        'merchant_id' => env('EPS_MERCHANT_ID'),
        'store_id' => env('EPS_STORE_ID'),
        'username' => env('EPS_USERNAME'),
        'password' => env('EPS_PASSWORD'),
        'hash_key' => env('EPS_HASH_KEY'),
        'timeout' => (int) env('EPS_TIMEOUT', 30),
    ],
];
