<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    public function view(User $user, Order $order): Response
    {
        return $this->ownsOrder($user, $order);
    }

    public function update(User $user, Order $order): Response
    {
        return $this->ownsOrder($user, $order);
    }

    public function delete(User $user, Order $order): Response
    {
        return $this->ownsOrder($user, $order);
    }

    public function processPayment(User $user, Order $order): Response
    {
        return $this->ownsOrder($user, $order);
    }

    public function viewPayments(User $user, Order $order): Response
    {
        return $this->ownsOrder($user, $order);
    }

    private function ownsOrder(User $user, Order $order): Response
    {
        return $user->id === $order->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
