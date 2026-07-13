<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_parts', function (Blueprint $table) {

            $table->id();


            $table->string('name');

            $table->string('slug')
                ->unique();


            $table->text('description')
                ->nullable();


            /*
             * القيمة التي تستخدم لاحقًا في التسعير
             * مثال:
             * Hood = 1.5
             * Full Front = 4
             */
            $table->decimal(
                'coverage_factor',
                5,
                2
            )
            ->default(1);


            $table->boolean('is_active')
                ->default(true);


            $table->timestamps();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('vehicle_parts');
    }
};