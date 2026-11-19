<?php

namespace Database\Seeders;

use App\Models\Industry;
use App\Modules\IndustryRegistry;
use Illuminate\Database\Seeder;

/** Creates one industries row for every registered industry module. */
class IndustrySeeder extends Seeder
{
    public function run(): void
    {
        foreach (app(IndustryRegistry::class)->all() as $module) {
            Industry::updateOrCreate(
                ['key' => $module->key()],
                [
                    'name' => $module->name(),
                    'description' => $module->description(),
                    'is_active' => true,
                ]
            );
        }
    }
}
