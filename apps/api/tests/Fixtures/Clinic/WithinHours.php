<?php

namespace Tests\Fixtures\Clinic;

use App\Core\Workflow\Guard;
use App\Core\Workflow\WorkflowContext;

/** The clinic only checks patients in during opening hours. */
class WithinHours implements Guard
{
    public static bool $open = true;

    public function name(): string
    {
        return 'clinic.within_hours';
    }

    public function check(WorkflowContext $context): bool|string
    {
        return self::$open ? true : 'The clinic is closed.';
    }
}
