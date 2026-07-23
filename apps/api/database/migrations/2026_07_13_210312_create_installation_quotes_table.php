<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_quotes', function (Blueprint $table) {

            $table->id();


            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->foreignId('installation_center_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->decimal('price',10,2);


            $table->string('status')
                ->default('pending');


            $table->timestamps();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('installation_quotes');
    }
};