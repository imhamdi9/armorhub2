<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Physical products need a delivery address and a shipment; services do not.
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('requires_shipping')->default(false);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->json('shipping_address')->nullable();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('carrier')->nullable();          // human name, e.g. "SMSA"
            $table->string('service')->nullable();          // e.g. "express"
            $table->string('reference')->nullable();        // the carrier's own shipment id
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->string('label_url', 500)->nullable();
            $table->string('status', 30)->default('created');
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'reference']);
            $table->index(['provider', 'tracking_number']);
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('occurred_at');
            // Carriers resend events; the same one is only stored once.
            $table->string('dedupe_key', 64);
            $table->timestamps();

            $table->unique(['shipment_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('shipping_address'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('requires_shipping'));
    }
};
