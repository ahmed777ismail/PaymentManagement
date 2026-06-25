<?php

namespace App\Http\Requests\Payments;

use App\Contracts\Payments\PaymentGatewayResolverInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(PaymentGatewayResolverInterface $paymentGatewayResolver): array
    {
        return [
            'method' => ['required', 'string', Rule::in($paymentGatewayResolver->enabledMethods())],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
