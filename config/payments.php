<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active payment processor
    |--------------------------------------------------------------------------
    |
    | Selects which PaymentProcessor adapter is bound behind the
    | SafeModePaymentProcessor guard. The processor is a plugin: the rest of
    | the application only depends on the PaymentProcessor interface
    | (charge / refund), so swapping gateways is a configuration change, not a
    | rewrite. Supported: "fake" (default - canned, no gateway), "stripe".
    | A "bluepay" adapter drops in the same way for a production merchant.
    |
    */

    'processor' => env('PAYMENTS_PROCESSOR', 'fake'),

    'stripe' => [
        'secret' => env('STRIPE_SECRET', ''),
        'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
        'currency' => env('STRIPE_CURRENCY', 'usd'),
    ],

];
