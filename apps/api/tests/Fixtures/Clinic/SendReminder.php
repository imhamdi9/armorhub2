<?php

namespace Tests\Fixtures\Clinic;

use App\Core\Workflow\Action;
use App\Core\Workflow\WorkflowContext;

class SendReminder implements Action
{
    /** @var list<string> */
    public static array $ran = [];

    public function name(): string
    {
        return 'clinic.send_reminder';
    }

    public function run(WorkflowContext $context): void
    {
        self::$ran[] = "{$context->transition}:{$context->from}->{$context->to}";
    }
}
