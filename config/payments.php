<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment gateway driver
    |--------------------------------------------------------------------------
    |
    | "pseudo" simulates hosted checkout (confirm button) so corporate
    | prepayment flows work end-to-end. Replace with a real driver class
    | bound in AppServiceProvider when SSLCommerz / bKash is finalized.
    |
    */
    'driver' => env('PAYMENT_GATEWAY_DRIVER', 'pseudo'),
];
