<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function paymentAuthHeadersFor(User $user): array
{
    return [
        'Authorization' => 'Bearer '.auth('api')->login($user),
    ];
}

function createConfirmedOrderFor(User $user): int
{
    $orderId = test()
        ->withHeaders(paymentAuthHeadersFor($user))
        ->postJson('/api/v1/orders', [
            'customer' => [
                'name' => 'Jane Customer',
                'email' => 'customer@example.com',
                'phone' => '+201234567890',
            ],
            'currency' => 'USD',
            'items' => [
                [
                    'product_name' => 'Keyboard',
                    'quantity' => 2,
                    'price' => 50,
                ],
            ],
        ])
        ->json('data.id');

    test()
        ->withHeaders(paymentAuthHeadersFor($user))
        ->patchJson("/api/v1/orders/{$orderId}/status", [
            'status' => 'confirmed',
        ])
        ->assertOk();

    return $orderId;
}

it('requires authentication to access payments', function () {
    $this->getJson('/api/v1/payments')
        ->assertUnauthorized();
});

it('processes a successful payment for a confirmed order', function () {
    $user = User::factory()->create();
    $orderId = createConfirmedOrderFor($user);

    $response = $this
        ->withHeaders(paymentAuthHeadersFor($user))
        ->postJson("/api/v1/orders/{$orderId}/payments", [
            'method' => 'credit_card',
            'metadata' => [
                'card_last_four' => '4242',
            ],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.order_id', $orderId)
        ->assertJsonPath('data.method', 'credit_card')
        ->assertJsonPath('data.status', 'successful')
        ->assertJsonPath('data.amount', '100.00')
        ->assertJsonStructure([
            'data' => [
                'id',
                'payment_id',
                'order_id',
                'status',
                'method',
                'amount',
                'currency',
                'gateway_reference',
                'failure_reason',
                'metadata',
                'processed_at',
            ],
        ]);

    $this->assertDatabaseHas('payments', [
        'order_id' => $orderId,
        'method' => 'credit_card',
        'status' => 'successful',
        'amount' => 100.00,
    ]);
});

it('stores failed payment attempts', function () {
    $user = User::factory()->create();
    $orderId = createConfirmedOrderFor($user);

    $response = $this
        ->withHeaders(paymentAuthHeadersFor($user))
        ->postJson("/api/v1/orders/{$orderId}/payments", [
            'method' => 'failing_gateway',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', 'Simulated gateway failure.');

    $this->assertDatabaseHas('payments', [
        'order_id' => $orderId,
        'method' => 'failing_gateway',
        'status' => 'failed',
        'failure_reason' => 'Simulated gateway failure.',
    ]);
});

it('rejects payments for orders that are not confirmed', function () {
    $user = User::factory()->create();

    $orderId = $this
        ->withHeaders(paymentAuthHeadersFor($user))
        ->postJson('/api/v1/orders', [
            'customer' => [
                'name' => 'Jane Customer',
                'email' => 'customer@example.com',
            ],
            'items' => [
                [
                    'product_name' => 'Keyboard',
                    'quantity' => 1,
                    'price' => 50,
                ],
            ],
        ])
        ->json('data.id');

    $this
        ->withHeaders(paymentAuthHeadersFor($user))
        ->postJson("/api/v1/orders/{$orderId}/payments", [
            'method' => 'credit_card',
        ])
        ->assertConflict()
        ->assertJsonPath('message', 'Payments can only be processed for confirmed orders.');
});

it('rejects unsupported payment methods', function () {
    $user = User::factory()->create();
    $orderId = createConfirmedOrderFor($user);

    $this
        ->withHeaders(paymentAuthHeadersFor($user))
        ->postJson("/api/v1/orders/{$orderId}/payments", [
            'method' => 'crypto',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method']);
});

it('lists all payments for the authenticated user and filters by order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $orderId = createConfirmedOrderFor($user);
    $otherOrderId = createConfirmedOrderFor($otherUser);

    $this
        ->withHeaders(paymentAuthHeadersFor($user))
        ->postJson("/api/v1/orders/{$orderId}/payments", [
            'method' => 'credit_card',
        ])
        ->assertCreated();

    $this
        ->withHeaders(paymentAuthHeadersFor($otherUser))
        ->postJson("/api/v1/orders/{$otherOrderId}/payments", [
            'method' => 'credit_card',
        ])
        ->assertCreated();

    $response = $this
        ->withHeaders(paymentAuthHeadersFor($user))
        ->getJson("/api/v1/payments?order_id={$orderId}");

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_id', $orderId)
        ->assertJsonPath('meta.total', 1);
});

it('shows payments for a specific owned order', function () {
    $user = User::factory()->create();
    $orderId = createConfirmedOrderFor($user);

    $this
        ->withHeaders(paymentAuthHeadersFor($user))
        ->postJson("/api/v1/orders/{$orderId}/payments", [
            'method' => 'paypal',
        ])
        ->assertCreated();

    $this
        ->withHeaders(paymentAuthHeadersFor($user))
        ->getJson("/api/v1/orders/{$orderId}/payments")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.method', 'paypal');
});

it('prevents deleting an order with associated payments', function () {
    $user = User::factory()->create();
    $orderId = createConfirmedOrderFor($user);

    $this
        ->withHeaders(paymentAuthHeadersFor($user))
        ->postJson("/api/v1/orders/{$orderId}/payments", [
            'method' => 'credit_card',
        ])
        ->assertCreated();

    $this
        ->withHeaders(paymentAuthHeadersFor($user))
        ->deleteJson("/api/v1/orders/{$orderId}")
        ->assertConflict()
        ->assertJsonPath('message', 'Orders with associated payments cannot be deleted.');

    expect(Order::query()->whereKey($orderId)->exists())->toBeTrue();
});
