<?php

namespace App\Console\Commands;

use App\Models\Partner;
use App\Services\InstallationJobs;
use App\Services\Reputation;
use Illuminate\Console\Command;

class InstallationsMaintain extends Command
{
    protected $signature = 'installations:maintain';

    protected $description = 'Release proven installation jobs after the objection window, close old requests and rebuild every centre\'s record';

    public function handle(InstallationJobs $jobs, Reputation $reputation): int
    {
        $released = $jobs->releaseDue();
        $expired = $jobs->expireOld();
        Partner::withoutGlobalScopes()->each(fn (Partner $p) => $reputation->refresh($p));
        $this->info("Released {$released}, expired {$expired}.");

        return self::SUCCESS;
    }
}
