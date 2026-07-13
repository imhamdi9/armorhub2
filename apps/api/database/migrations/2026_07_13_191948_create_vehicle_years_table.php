<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_years', function (Blueprint $table) {

            $table->id();

            $table->foreignId('vehicle_model_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('year');

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();


            $table->unique([
                'vehicle_model_id',
                'year'
            ]);

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('vehicle_years');
    }
};