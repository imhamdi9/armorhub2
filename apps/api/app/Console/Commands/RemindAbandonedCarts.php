<?php

namespace App\Console\Commands;

use App\Services\CartReminders;
use Illuminate\Console\Command;

class RemindAbandonedCarts extends Command
{
    protected $signature = 'carts:remind';

    protected $description = 'Remind customers about carts they left behind';

    public function handle(CartReminders $reminders): int
    {
        $this->info('Reminded '.$reminders->run().' customer(s).');

        return self::SUCCESS;
    }
}
