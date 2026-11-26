<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class RenewSubscriptions extends Command
{
    protected $signature = 'subscriptions:renew';

    protected $description = 'Renew paid plans whose month has ended, or move cancelled ones to the free plan.';

    public function handle(SubscriptionService $subs): int
    {
        [$renewed, $ended] = $subs->renewDue();
        $this->info("Renewed {$renewed}, ended {$ended}.");

        return self::SUCCESS;
    }
}
