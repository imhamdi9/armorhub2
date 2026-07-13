<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleYear;
use App\Models\VehiclePart;
use App\Models\Film;
use App\Models\FilmPriceRule;


class VehicleCatalogSeeder extends Seeder
{
    public function run(): void
    {

        $bmw = VehicleMake::create([
            'name'=>'BMW',
            'slug'=>'bmw',
        ]);


        $m4 = VehicleModel::create([
            'vehicle_make_id'=>$bmw->id,
            'name'=>'M4',
            'slug'=>'m4',
        ]);


        VehicleYear::create([
            'vehicle_model_id'=>$m4->id,
            'year'=>2025,
        ]);


        $hood = VehiclePart::create([
            'name'=>'Hood',
            'slug'=>'hood',
            'coverage_factor'=>1.5,
        ]);


        $bumper = VehiclePart::create([
            'name'=>'Front Bumper',
            'slug'=>'front-bumper',
            'coverage_factor'=>1,
        ]);


        $mirror = VehiclePart::create([
            'name'=>'Mirrors',
            'slug'=>'mirrors',
            'coverage_factor'=>0.3,
        ]);


        $film = Film::create([
            'brand'=>'XPEL',
            'name'=>'Ultimate Plus',
            'slug'=>'xpel-ultimate-plus',
            'base_price'=>500,
            'warranty_years'=>10,
        ]);


        FilmPriceRule::insert([

            [
                'film_id'=>$film->id,
                'vehicle_part_id'=>$hood->id,
                'price'=>1200,
                'installation_minutes'=>180,
                'is_active'=>true,
            ],

            [
                'film_id'=>$film->id,
                'vehicle_part_id'=>$bumper->id,
                'price'=>900,
                'installation_minutes'=>120,
                'is_active'=>true,
            ],

            [
                'film_id'=>$film->id,
                'vehicle_part_id'=>$mirror->id,
                'price'=>250,
                'installation_minutes'=>30,
                'is_active'=>true,
            ],

        ]);

    }
}