<?php

namespace App\Console\Commands;

use App\Models\PartnerApplication;
use App\Services\ApplicationDocuments;
use Illuminate\Console\Command;

/** Removes the verification documents of rejected applications once they are old enough that nobody needs them. */
class PruneApplicationDocuments extends Command
{
    protected $signature = 'applications:prune-documents {--days=90 : Keep rejected applications\' documents this long}';

    protected $description = 'Delete verification documents of old rejected applications';

    public function handle(ApplicationDocuments $documents): int
    {
        $apps = PartnerApplication::where('status', PartnerApplication::REJECTED)->where('reviewed_at', '<', now()->subDays((int) $this->option('days')))
            ->has('documents')->get();

        foreach ($apps as $app) {
            $documents->purge($app);
        }

        $this->info("Removed documents of {$apps->count()} rejected application(s).");

        return self::SUCCESS;
    }
}
