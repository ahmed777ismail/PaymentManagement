<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentGatewayResolverInterface;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayResolverInterface::class, PaymentGatewayManager::class);
    }
}
