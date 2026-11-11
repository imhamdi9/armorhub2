<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            IndustrySeeder::class,
            RolesAndPermissionsSeeder::class,
            VehicleCatalogSeeder::class,
            IndustryCatalogSeeder::class,
        ]);
    }
}
