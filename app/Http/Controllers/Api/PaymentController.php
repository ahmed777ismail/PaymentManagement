<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\ProcessPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\IndexPaymentRequest;
use App\Http\Requests\Payments\ProcessPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(IndexPaymentRequest $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->whereHas('order', fn ($query) => $query->whereBelongsTo($request->user('api')))
            ->when($request->validated('order_id'), fn ($query, int $orderId) => $query->where('order_id', $orderId))
            ->when($request->validated('status'), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->validated('method'), fn ($query, string $method) => $query->where('method', $method))
            ->latest()
            ->paginate((int) ($request->validated('per_page') ?? 15));

        return PaymentResource::collection($payments);
    }

    public function store(
        ProcessPaymentRequest $request,
        Order $order,
        ProcessPaymentAction $processPayment,
    ): JsonResponse {
        $this->authorize('processPayment', $order);

        $payment = $processPayment->execute($order, $request->validated());

        return response()->json([
            'data' => new PaymentResource($payment),
        ], 201);
    }

    public function forOrder(Order $order): AnonymousResourceCollection
    {
        $this->authorize('viewPayments', $order);

        return PaymentResource::collection(
            $order->payments()->latest()->paginate((int) request('per_page', 15))
        );
    }

    public function show(Payment $payment): PaymentResource
    {
        $payment->load('order');
        $this->authorize('view', $payment);

        return new PaymentResource($payment);
    }
}
