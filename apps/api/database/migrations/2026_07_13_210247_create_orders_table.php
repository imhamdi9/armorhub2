<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {

            $table->id();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('film_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('vehicle_model_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->string('status')
                ->default('pending');


            $table->decimal('film_amount',10,2)
                ->default(0);

            $table->decimal('installation_amount',10,2)
                ->default(0);

            $table->decimal('commission_amount',10,2)
                ->default(0);

            $table->decimal('total_amount',10,2)
                ->default(0);


            $table->string('currency')
                ->default('USD');


            $table->timestamps();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};