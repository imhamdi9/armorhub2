<?php

namespace App\Core\Console;

use App\Core\Surfaces\SurfacePublications;
use Illuminate\Console\Command;

class PublishDueSurfaces extends Command
{
    protected $signature = 'surfaces:publish-due';

    protected $description = 'Publish scheduled content changes whose time has come.';

    public function handle(SurfacePublications $publications): int
    {
        $this->info('Published '.$publications->publishDue().' scheduled change(s).');

        return self::SUCCESS;
    }
}
