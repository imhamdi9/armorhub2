<?php

namespace App\Core\Workflow;

/** What a guard or action gets to look at: the thing moving, who moves it, and the move itself. */
final class WorkflowContext
{
    public function __construct(
        public readonly mixed $subject,
        public readonly mixed $actor = null,
        public readonly ?int $tenantId = null,
        public readonly array $data = [],
        public ?string $transition = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}
