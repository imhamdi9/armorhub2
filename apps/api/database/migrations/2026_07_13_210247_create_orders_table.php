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

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            // The thing being serviced (vehicle, property, device...), if any
            $table->nullableMorphs('subject');


            $table->string('status')
                ->default('pending_quotes');


            $table->decimal('product_amount',10,2)
                ->default(0);

            $table->decimal('service_amount',10,2)
                ->default(0);

            $table->decimal('commission_amount',10,2)
                ->default(0);

            $table->decimal('total_amount',10,2)
                ->default(0);


            $table->string('currency')
                ->default('USD');


            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};