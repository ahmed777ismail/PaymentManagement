<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function authHeadersFor(User $user): array
{
    return [
        'Authorization' => 'Bearer '.auth('api')->login($user),
    ];
}

function validOrderPayload(array $overrides = []): array
{
    $payload = array_replace_recursive([
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
                'price' => 50.25,
            ],
            [
                'product_name' => 'Mouse',
                'quantity' => 1,
                'price' => 20,
            ],
        ],
    ], $overrides);

    if (array_key_exists('items', $overrides)) {
        $payload['items'] = $overrides['items'];
    }

    return $payload;
}

it('requires authentication to access orders', function () {
    $this->getJson('/api/v1/orders')
        ->assertUnauthorized();
});

it('creates an order with calculated totals and pending status', function () {
    $user = User::factory()->create();

    $response = $this
        ->withHeaders(authHeadersFor($user))
        ->postJson('/api/v1/orders', validOrderPayload());

    $response
        ->assertCreated()
        ->assertJsonPath('data.customer_name', 'Jane Customer')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total_amount', '120.50')
        ->assertJsonCount(2, 'data.items');

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'customer_email' => 'customer@example.com',
        'status' => 'pending',
        'total_amount' => 120.50,
    ]);

    $this->assertDatabaseHas('order_items', [
        'product_name' => 'Keyboard',
        'quantity' => 2,
        'unit_price' => 50.25,
        'line_total' => 100.50,
    ]);
});

it('lists authenticated user orders and filters by status', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $pendingOrderId = $this
        ->withHeaders(authHeadersFor($user))
        ->postJson('/api/v1/orders', validOrderPayload([
            'customer' => ['email' => 'pending@example.com'],
        ]))
        ->json('data.id');

    $confirmedOrderId = $this
        ->withHeaders(authHeadersFor($user))
        ->postJson('/api/v1/orders', validOrderPayload([
            'customer' => ['email' => 'confirmed@example.com'],
        ]))
        ->json('data.id');

    $this
        ->withHeaders(authHeadersFor($user))
        ->patchJson("/api/v1/orders/{$confirmedOrderId}/status", [
            'status' => 'confirmed',
        ])
        ->assertOk();

    $this
        ->withHeaders(authHeadersFor($otherUser))
        ->postJson('/api/v1/orders', validOrderPayload([
            'customer' => ['email' => 'other@example.com'],
        ]))
        ->assertCreated();

    $response = $this
        ->withHeaders(authHeadersFor($user))
        ->getJson('/api/v1/orders?status=pending');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pendingOrderId)
        ->assertJsonPath('meta.total', 1);
});

it('shows only orders owned by the authenticated user', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $orderId = $this
        ->withHeaders(authHeadersFor($owner))
        ->postJson('/api/v1/orders', validOrderPayload())
        ->json('data.id');

    $this
        ->withHeaders(authHeadersFor($owner))
        ->getJson("/api/v1/orders/{$orderId}")
        ->assertOk()
        ->assertJsonPath('data.id', $orderId);

    $this
        ->withHeaders(authHeadersFor($otherUser))
        ->getJson("/api/v1/orders/{$orderId}")
        ->assertNotFound();
});

it('updates a pending order and recalculates totals', function () {
    $user = User::factory()->create();

    $orderId = $this
        ->withHeaders(authHeadersFor($user))
        ->postJson('/api/v1/orders', validOrderPayload())
        ->json('data.id');

    $response = $this
        ->withHeaders(authHeadersFor($user))
        ->putJson("/api/v1/orders/{$orderId}", validOrderPayload([
            'customer' => [
                'name' => 'Updated Customer',
                'email' => 'updated@example.com',
                'phone' => null,
            ],
            'items' => [
                [
                    'product_name' => 'Monitor',
                    'quantity' => 3,
                    'price' => 99.99,
                ],
            ],
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('data.customer_name', 'Updated Customer')
        ->assertJsonPath('data.total_amount', '299.97')
        ->assertJsonCount(1, 'data.items');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'customer_email' => 'updated@example.com',
        'total_amount' => 299.97,
    ]);
});

it('does not update order details after the order is confirmed', function () {
    $user = User::factory()->create();

    $orderId = $this
        ->withHeaders(authHeadersFor($user))
        ->postJson('/api/v1/orders', validOrderPayload())
        ->json('data.id');

    $this
        ->withHeaders(authHeadersFor($user))
        ->patchJson("/api/v1/orders/{$orderId}/status", [
            'status' => 'confirmed',
        ])
        ->assertOk();

    $this
        ->withHeaders(authHeadersFor($user))
        ->putJson("/api/v1/orders/{$orderId}", validOrderPayload([
            'customer' => ['name' => 'Should Not Change'],
        ]))
        ->assertConflict()
        ->assertJsonPath('message', 'Only pending orders can be modified.');
});

it('deletes a pending order with no payments', function () {
    $user = User::factory()->create();

    $orderId = $this
        ->withHeaders(authHeadersFor($user))
        ->postJson('/api/v1/orders', validOrderPayload())
        ->json('data.id');

    $this
        ->withHeaders(authHeadersFor($user))
        ->deleteJson("/api/v1/orders/{$orderId}")
        ->assertNoContent();

    $this->assertDatabaseMissing('orders', [
        'id' => $orderId,
    ]);
});

it('validates order items before creating an order', function () {
    $user = User::factory()->create();

    $this
        ->withHeaders(authHeadersFor($user))
        ->postJson('/api/v1/orders', validOrderPayload([
            'items' => [
                [
                    'product_name' => '',
                    'quantity' => 0,
                    'price' => 0,
                ],
            ],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'items.0.product_name',
            'items.0.quantity',
            'items.0.price',
        ]);
});
