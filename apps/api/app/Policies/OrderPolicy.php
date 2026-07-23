<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        // Platform admin يرى كل شيء
        if ($user->hasRole('platform-admin')) {
            return true;
        }

        // العميل يرى طلباته فقط
        if ($user->hasRole('customer')) {
            return $order->customer_id === $user->id;
        }

        // المستخدم داخل شركة يرى طلبات شركته
        if ($user->company_id) {
            return $order->company_id === $user->company_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->hasRole('platform-admin');
    }
}