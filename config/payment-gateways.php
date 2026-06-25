<?php

use App\Services\Payments\Gateways\CreditCardGateway;
use App\Services\Payments\Gateways\FailingGateway;
use App\Services\Payments\Gateways\PaypalGateway;

return [
    'gateways' => [
        'credit_card' => [
            'driver' => CreditCardGateway::class,
            'enabled' => env('CREDIT_CARD_ENABLED', true),
            'api_key' => env('CREDIT_CARD_API_KEY'),
            'secret' => env('CREDIT_CARD_SECRET'),
        ],

        'paypal' => [
            'driver' => PaypalGateway::class,
            'enabled' => env('PAYPAL_ENABLED', true),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
        ],

        'failing_gateway' => [
            'driver' => FailingGateway::class,
            'enabled' => env('FAILING_GATEWAY_ENABLED', true),
        ],
    ],
];
