<?php

namespace App\Contracts\Payments;

interface PaymentGatewayResolverInterface
{
    public function resolve(string $method): PaymentGatewayInterface;

    /**
     * @return list<string>
     */
    public function enabledMethods(): array;
}
