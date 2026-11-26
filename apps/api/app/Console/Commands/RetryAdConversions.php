<?php

namespace App\Console\Commands;

use App\Models\AdConversionLog;
use App\Models\Order;
use App\Models\PartnerApplication;
use App\Services\Marketing\AdConversions;
use Illuminate\Console\Command;

class RetryAdConversions extends Command
{
    protected $signature = 'marketing:retry-conversions';

    protected $description = 'Sends again the purchases and leads an ad platform did not accept (up to 4 tries, within a day)';

    public function handle(AdConversions $conversions): int
    {
        $failed = AdConversionLog::where('status', 'failed')->where('created_at', '>=', now()->subDay())->where('created_at', '<=', now()->subMinutes(5))
            ->whereIn('subject_type', ['order', 'application'])->get()->groupBy(fn ($l) => $l->subject_type.'|'.$l->subject_id);
        $tried = 0;

        foreach ($failed as $logs) {
            $first = $logs->first();
            $pending = $logs->groupBy('platform')->filter(fn ($g) => $g->count() < AdConversions::MAX_TRIES
                && ! AdConversionLog::where(['platform' => $g->first()->platform, 'event_id' => $g->first()->event_id, 'status' => 'sent'])->exists());
            if ($pending->isEmpty()) {
                continue;
            }
            // Only the newest failure of a platform counts for waiting: one retry per run.
            if ($first->subject_type === 'order' && ($order = Order::withoutGlobalScopes()->with('customer:id,email,contact_email')->find($first->subject_id))) {
                $conversions->purchase($order);
                $tried++;
            } elseif ($first->subject_type === 'application' && ($application = PartnerApplication::find($first->subject_id))) {
                $conversions->lead($application);
                $tried++;
            }
        }
        $this->info("Retried {$tried}.");

        return self::SUCCESS;
    }
}
