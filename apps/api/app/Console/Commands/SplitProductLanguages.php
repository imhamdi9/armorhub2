<?php

namespace App\Console\Commands;

use App\Services\ProductLanguages;
use Illuminate\Console\Command;

class SplitProductLanguages extends Command
{
    protected $signature = 'products:split-languages';
    protected $description = 'Give every product its English and Arabic copy in separate fields (safe to run again)';

    public function handle(ProductLanguages $languages): int
    {
        $this->info('Products changed: '.$languages->backfill());

        return self::SUCCESS;
    }
}
