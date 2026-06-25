<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\CreateOrderAction;
use App\Actions\Orders\UpdateOrderAction;
use App\Actions\Orders\UpdateOrderStatusAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\IndexOrderRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Http\Requests\Orders\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(IndexOrderRequest $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->with('items')
            ->whereBelongsTo($request->user('api'))
            ->when($request->validated('status'), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate((int) ($request->validated('per_page') ?? 15));

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request, CreateOrderAction $createOrder): JsonResponse
    {
        $order = $createOrder->execute($request->user('api'), $request->validated());

        return response()->json([
            'data' => new OrderResource($order),
        ], 201);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load('items'));
    }

    public function update(
        UpdateOrderRequest $request,
        Order $order,
        UpdateOrderAction $updateOrder,
    ): OrderResource {
        $this->authorize('update', $order);

        return new OrderResource($updateOrder->execute($order, $request->validated()));
    }

    public function updateStatus(
        UpdateOrderStatusRequest $request,
        Order $order,
        UpdateOrderStatusAction $updateOrderStatus,
    ): OrderResource {
        $this->authorize('update', $order);

        $status = OrderStatus::from($request->validated('status'));

        return new OrderResource($updateOrderStatus->execute($order, $status));
    }

    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);
        abort_if($order->payments()->exists(), 409, 'Orders with associated payments cannot be deleted.');

        $order->delete();

        return response()->json(status: 204);
    }
}
