<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('films', function (Blueprint $table) {

            $table->id();


            $table->string('brand');

            $table->string('name');


            $table->string('slug')
                ->unique();


            $table->text('description')
                ->nullable();


            /*
             * سعر المتر أو الوحدة الأساسية
             */
            $table->decimal(
                'base_price',
                10,
                2
            );


            $table->integer('warranty_years')
                ->nullable();


            $table->boolean('is_active')
                ->default(true);


            $table->timestamps();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('films');
    }
};