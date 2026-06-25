<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Contracts\Payments\PaymentGatewayResolverInterface;
use InvalidArgumentException;

class PaymentGatewayManager implements PaymentGatewayResolverInterface
{
    public function resolve(string $method): PaymentGatewayInterface
    {
        $gateway = config("payment-gateways.gateways.{$method}");

        if (! is_array($gateway) || ($gateway['enabled'] ?? false) !== true) {
            throw new InvalidArgumentException("Unsupported payment method [{$method}].");
        }

        $driver = $gateway['driver'] ?? null;

        if (! is_string($driver) || ! is_subclass_of($driver, PaymentGatewayInterface::class)) {
            throw new InvalidArgumentException("Invalid payment gateway driver for [{$method}].");
        }

        return app($driver);
    }

    public function enabledMethods(): array
    {
        return collect(config('payment-gateways.gateways', []))
            ->filter(fn (array $gateway): bool => ($gateway['enabled'] ?? false) === true)
            ->keys()
            ->values()
            ->all();
    }
}
