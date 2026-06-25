<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows users to access only their own orders', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($owner)->create();
    $policy = new OrderPolicy;

    expect($policy->view($owner, $order)->allowed())->toBeTrue()
        ->and($policy->view($otherUser, $order)->denied())->toBeTrue()
        ->and($policy->update($owner, $order)->allowed())->toBeTrue()
        ->and($policy->delete($otherUser, $order)->denied())->toBeTrue()
        ->and($policy->processPayment($owner, $order)->allowed())->toBeTrue()
        ->and($policy->viewPayments($otherUser, $order)->denied())->toBeTrue();
});

it('allows users to access only payments for their own orders', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($owner)->create();
    $payment = Payment::factory()->for($order)->create();
    $policy = new PaymentPolicy;

    expect($policy->view($owner, $payment)->allowed())->toBeTrue()
        ->and($policy->view($otherUser, $payment)->denied())->toBeTrue();
});
