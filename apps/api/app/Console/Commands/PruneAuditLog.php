<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLog extends Command
{
    protected $signature = 'audit:prune {--days=365 : Keep this many days}';

    protected $description = 'Delete audit log entries older than the retention period';

    public function handle(): int
    {
        $deleted = AuditLog::where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();

        $this->info("Deleted {$deleted} audit log entries.");

        return self::SUCCESS;
    }
}
