<?php

namespace App\Console\Commands;

use App\Services\Marketing\AdSpendSync;
use Illuminate\Console\Command;

class SyncAdSpend extends Command
{
    protected $signature = 'marketing:sync-spend {--days=7 : How many days back to read}';

    protected $description = 'Reads what the ad accounts spent per campaign (Meta, TikTok), read only';

    public function handle(AdSpendSync $sync): int
    {
        foreach ($sync->run((int) $this->option('days')) as $r) {
            $this->line("{$r['platform']} {$r['account']}: {$r['status']} ({$r['rows']} rows)".($r['note'] ? " {$r['note']}" : ''));
        }

        return self::SUCCESS;
    }
}
