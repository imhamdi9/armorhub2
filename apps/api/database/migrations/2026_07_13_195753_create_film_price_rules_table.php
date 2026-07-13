<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('film_price_rules', function (Blueprint $table) {

            $table->id();


            $table->foreignId('film_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->foreignId('vehicle_part_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->decimal(
                'price',
                10,
                2
            );


            $table->integer('installation_minutes')
                ->nullable();


            $table->boolean('is_active')
                ->default(true);


            $table->timestamps();


            $table->unique([
                'film_id',
                'vehicle_part_id'
            ]);

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('film_price_rules');
    }
};