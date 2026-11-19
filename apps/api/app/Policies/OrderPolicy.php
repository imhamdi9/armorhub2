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

        // Staff of a market see its orders only when their role deals with orders (not, say, a catalogue reviewer or a technician).
        if ($user->company_id) {
            return $this->staffWorksOrders($user) && $order->company_id === $user->company_id;
        }

        return false;
    }

    /** Whether this person's role is one that handles orders: managers, operations, support, warehouse. */
    public function staffWorksOrders(User $user): bool
    {
        return $user->can('orders.view') || $user->can('operations.manage') || $user->can('support.manage') || $user->can('warehouse.manage');
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