<?php

use App\Services\Payments\Gateways\CreditCardGateway;
use App\Services\Payments\PaymentGatewayManager;
use Tests\TestCase;

uses(TestCase::class);

it('resolves enabled payment gateway drivers', function () {
    config()->set('payment-gateways.gateways.credit_card.enabled', true);

    $gateway = app(PaymentGatewayManager::class)->resolve('credit_card');

    expect($gateway)->toBeInstanceOf(CreditCardGateway::class);
});

it('returns only enabled payment methods', function () {
    config()->set('payment-gateways.gateways.credit_card.enabled', true);
    config()->set('payment-gateways.gateways.paypal.enabled', false);

    $methods = app(PaymentGatewayManager::class)->enabledMethods();

    expect($methods)
        ->toContain('credit_card')
        ->not->toContain('paypal');
});

it('rejects disabled payment methods', function () {
    config()->set('payment-gateways.gateways.credit_card.enabled', false);

    app(PaymentGatewayManager::class)->resolve('credit_card');
})->throws(InvalidArgumentException::class);
