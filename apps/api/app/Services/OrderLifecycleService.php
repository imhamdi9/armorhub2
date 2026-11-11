<?php

namespace App\Services;

use App\Core\Workflow\TransitionNotAllowed;
use App\Core\Workflow\WorkflowContext;
use App\Core\Workflow\WorkflowEngine;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * paid -> appointment_scheduled -> service_in_progress -> completed,
 * and cancellation before payment. Every move is validated, locked and logged.
 */
class OrderLifecycleService
{
    public function __construct(private WorkflowEngine $workflows)
    {
    }

    public function schedule(Order $order, Carbon $when, User $by): Order
    {
        return $this->move($order, 'schedule', $by, ['scheduled_at' => $when], ['scheduled_at' => $when->toIso8601String()]);
    }

    public function start(Order $order, User $by): Order
    {
        return $this->move($order, 'start', $by, ['started_at' => now()]);
    }

    public function complete(Order $order, User $by): Order
    {
        return $this->move($order, 'complete', $by, ['completed_at' => now()]);
    }

    public function cancel(Order $order, User $by, ?string $reason = null): Order
    {
        return $this->move($order, 'cancel', $by, ['cancelled_at' => now()], array_filter(['reason' => $reason]));
    }

    /**
     * One step of the order's workflow: validated against the (tenant's) definition, locked, applied, announced.
     * What each step allows, checks and does lives in the workflow definition, not here.
     */
    private function move(Order $order, string $transition, User $by, array $attributes = [], array $data = []): Order
    {
        return DB::transaction(function () use ($order, $transition, $by, $attributes, $data) {
            $locked = Order::withoutGlobalScopes()->with('product')->lockForUpdate()->findOrFail($order->id);
            $tenantId = Company::withoutGlobalScopes()->whereKey($locked->company_id)->value('tenant_id');

            try {
                $planned = $this->workflows->plan(Order::WORKFLOW, $locked->status->value, $transition, new WorkflowContext($locked, $by, $tenantId, $data));
            } catch (TransitionNotAllowed $e) {
                throw ValidationException::withMessages(['order' => [$e->getMessage()]]);
            }

            $locked->update(['status' => $planned->to] + $attributes);
            OrderEvent::record($locked, $planned->event() ?? $transition, $by, $data);
            $planned->runActions();

            return $locked->fresh('product');
        });
    }
}
