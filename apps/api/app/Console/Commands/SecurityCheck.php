<?php

namespace App\Console\Commands;

use App\Support\SecurityChecks;
use Illuminate\Console\Command;

class SecurityCheck extends Command
{
    protected $signature = 'platform:security-check';

    protected $description = 'Check the deployment configuration for common security mistakes';

    public function handle(SecurityChecks $checks): int
    {
        $failed = false;

        foreach ($checks->run() as $c) {
            $label = ['ok' => ' OK ', 'warn' => 'WARN', 'fail' => 'FAIL'][$c['status']];
            $line = "[{$label}] {$c['message']}";
            $c['status'] === 'fail' ? $this->error($line) : ($c['status'] === 'warn' ? $this->warn($line) : $this->info($line));
            $failed = $failed || $c['status'] === 'fail';
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
