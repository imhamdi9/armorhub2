<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;

/** Quiet shopper records that never bought anything are removed after a month, so abandoned carts do not pile up as accounts. */
class PruneGuestShoppers extends Command
{
    protected $signature = 'guests:prune {--days=30}';
    protected $description = 'Delete guest shoppers who never placed an order';

    public function handle(): int
    {
        $n = 0;
        User::where('is_guest', true)->where('created_at', '<', now()->subDays((int) $this->option('days')))
            ->whereNotIn('id', Order::withoutGlobalScopes()->whereNotNull('customer_id')->select('customer_id'))
            ->chunkById(200, function ($users) use (&$n) {
                foreach ($users as $u) {
                    $u->tokens()->delete();
                    $u->delete();
                    $n++;
                }
            });
        $this->info("Removed {$n} guest shoppers.");

        return self::SUCCESS;
    }
}
