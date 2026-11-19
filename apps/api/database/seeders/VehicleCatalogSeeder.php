<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Automotive\Models\VehicleMake;
use App\Modules\Automotive\Models\VehicleModel;
use App\Modules\Automotive\Models\VehicleYear;
use App\Modules\Automotive\Models\VehiclePart;
use App\Models\Industry;
use App\Models\Product;
use App\Models\ProductPriceRule;


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


        $product = Product::create([
            'industry_id'=>Industry::where('key','automotive')->value('id'),
            'category'=>'paint-protection-film',
            'brand'=>'XPEL',
            'name'=>'Ultimate Plus',
            'slug'=>'xpel-ultimate-plus',
            'base_price'=>500,
            'warranty_years'=>10,
        ]);


        ProductPriceRule::insert([

            [
                'product_id'=>$product->id,
                'target_type'=>'vehicle_part',
                'target_id'=>$hood->id,
                'price'=>1200,
                'duration_minutes'=>180,
                'is_active'=>true,
            ],

            [
                'product_id'=>$product->id,
                'target_type'=>'vehicle_part',
                'target_id'=>$bumper->id,
                'price'=>900,
                'duration_minutes'=>120,
                'is_active'=>true,
            ],

            [
                'product_id'=>$product->id,
                'target_type'=>'vehicle_part',
                'target_id'=>$mirror->id,
                'price'=>250,
                'duration_minutes'=>30,
                'is_active'=>true,
            ],

        ]);

    }
}