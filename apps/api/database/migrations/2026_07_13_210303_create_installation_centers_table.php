<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_centers', function (Blueprint $table) {

            $table->id();

            $table->foreignId('company_id')
    ->nullable()
    ->constrained()
    ->nullOnDelete();


            $table->string('name');

            $table->string('country')
                ->nullable();

            $table->string('city')
                ->nullable();

            $table->text('address')
                ->nullable();


            $table->string('phone')
                ->nullable();


            $table->boolean('is_active')
                ->default(true);


            $table->timestamps();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('installation_centers');
    }
};