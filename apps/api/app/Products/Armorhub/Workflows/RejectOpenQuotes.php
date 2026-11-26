<?php

namespace App\Products\Armorhub\Workflows;

use App\Core\Workflow\Action;
use App\Core\Workflow\WorkflowContext;
use App\Enums\QuoteStatus;
use App\Models\PartnerQuote;

/** A cancelled order no longer wants quotes: partners stop seeing the request. */
class RejectOpenQuotes implements Action
{
    public function name(): string
    {
        return 'armorhub.reject_open_quotes';
    }

    public function run(WorkflowContext $context): void
    {
        PartnerQuote::withoutGlobalScopes()->where('order_id', $context->subject->id)
            ->whereIn('status', [QuoteStatus::Pending, QuoteStatus::Submitted])
            ->update(['status' => QuoteStatus::Rejected]);
    }
}
