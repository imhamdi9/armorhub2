<?php

namespace App\Products\Armorhub\Workflows;

use App\Core\Workflow\Action;
use App\Core\Workflow\WorkflowContext;
use App\Models\Warranty;

/** When the work is completed, the customer gets the warranty the product promises. Idempotent. */
class GrantWarranty implements Action
{
    public function name(): string
    {
        return 'armorhub.grant_warranty';
    }

    public function run(WorkflowContext $context): void
    {
        $order = $context->subject;
        $years = $order->product?->warranty_years;

        if ($years) {
            Warranty::withoutGlobalScopes()->firstOrCreate(['order_id' => $order->id], [
                'company_id' => $order->company_id, 'customer_id' => $order->customer_id, 'product_id' => $order->product_id,
                'starts_at' => now(), 'ends_at' => now()->addYears($years),
            ]);
        }
    }
}
