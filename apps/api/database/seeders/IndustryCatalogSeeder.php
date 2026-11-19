<?php

namespace Database\Seeders;

use App\Modules\Beauty\Models\TreatmentArea;
use App\Modules\Devices\Models\DeviceBrand;
use App\Modules\Devices\Models\DeviceComponent;
use App\Modules\Devices\Models\DeviceModel;
use App\Modules\HomeServices\Models\RoomType;
use Illuminate\Database\Seeder;

/** Starter catalogs for the non-automotive industries. */
class IndustryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Living Room', 'Bedroom', 'Kitchen', 'Bathroom', 'Office'] as $name) {
            RoomType::firstOrCreate(['slug' => str($name)->slug()], ['name' => $name]);
        }

        foreach (['Face', 'Hands', 'Hair', 'Nails', 'Full Body'] as $name) {
            TreatmentArea::firstOrCreate(['slug' => str($name)->slug()], ['name' => $name]);
        }

        foreach (['Screen', 'Battery', 'Back Cover', 'Camera Lens'] as $name) {
            DeviceComponent::firstOrCreate(['slug' => str($name)->slug()], ['name' => $name]);
        }

        $apple = DeviceBrand::firstOrCreate(['slug' => 'apple'], ['name' => 'Apple']);
        DeviceModel::firstOrCreate(
            ['slug' => 'iphone-16'],
            ['device_brand_id' => $apple->id, 'name' => 'iPhone 16']
        );
    }
}
