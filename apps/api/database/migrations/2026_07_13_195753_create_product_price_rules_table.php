<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_rules', function (Blueprint $table) {

            $table->id();


            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();


            // Industry-specific target (vehicle part, room, body area...)
            $table->nullableMorphs('target');


            $table->decimal(
                'price',
                10,
                2
            );


            $table->integer('duration_minutes')
                ->nullable();


            $table->boolean('is_active')
                ->default(true);


            $table->timestamps();


            $table->unique([
                'product_id',
                'target_type',
                'target_id'
            ]);

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('product_price_rules');
    }
};