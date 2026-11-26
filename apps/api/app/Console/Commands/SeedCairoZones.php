<?php

namespace App\Console\Commands;

use App\Models\ServiceZone;
use Illuminate\Console\Command;

/** The launch areas of Cairo and Giza: a few trusted centres in each beat a hundred spread across the city. */
class SeedCairoZones extends Command
{
    protected $signature = 'zones:seed-cairo {--activate : open them all now (otherwise they are created closed, to be opened one by one)}';

    protected $description = 'Create the Cairo and Giza launch zones (safe to run again)';

    private const ZONES = [
        ['nasr-city-heliopolis', 'Nasr City and Heliopolis', 'مدينة نصر ومصر الجديدة', 30.0750, 31.3300, 8],
        ['new-cairo', 'New Cairo and Fifth Settlement', 'التجمع الخامس والقاهرة الجديدة', 30.0300, 31.4700, 10],
        ['maadi', 'Maadi', 'المعادي', 29.9600, 31.2600, 6],
        ['sheikh-zayed-6-october', 'Sheikh Zayed and 6th of October', 'الشيخ زايد و6 أكتوبر', 30.0200, 30.9800, 14],
        ['mohandessin-dokki', 'Mohandessin, Dokki and Zamalek', 'المهندسين والدقي والزمالك', 30.0550, 31.2050, 6],
    ];

    public function handle(): int
    {
        foreach (self::ZONES as $i => [$key, $en, $ar, $lat, $lng, $radius]) {
            $zone = ServiceZone::firstOrCreate(['market' => 'EG', 'key' => $key], ['name_en' => $en, 'name_ar' => $ar, 'city' => 'Cairo', 'latitude' => $lat, 'longitude' => $lng, 'radius_km' => $radius, 'position' => $i + 1, 'is_active' => false]);
            if ($this->option('activate') && ! $zone->is_active) {
                $zone->update(['is_active' => true]);
            }
        }
        $this->info(count(self::ZONES).' zones ready'.($this->option('activate') ? ' and open.' : ' (closed: open them in the admin screen).'));

        return self::SUCCESS;
    }
}
