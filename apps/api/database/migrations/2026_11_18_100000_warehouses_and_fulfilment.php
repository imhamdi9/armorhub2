<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('city')->nullable();
            $t->string('address')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // seller: the seller stores and ships it; armorhub: it is stored in a market warehouse and ArmorHub ships it.
        Schema::table('products', fn (Blueprint $t) => $t->string('fulfilment', 12)->default('seller'));
        Schema::table('orders', fn (Blueprint $t) => $t->string('fulfilment', 12)->default('seller'));
        Schema::table('orders', fn (Blueprint $t) => $t->unsignedBigInteger('warehouse_id')->nullable());

        Schema::create('inbound_shipments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $t->unsignedBigInteger('seller_id')->index();
            $t->string('status', 12)->default('announced'); // announced, received, cancelled
            $t->string('carrier')->nullable();
            $t->string('tracking_number')->nullable();
            $t->string('note')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('received_at')->nullable();
            $t->timestamps();
        });

        Schema::create('inbound_shipment_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('inbound_shipment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('cartons_expected');
            $t->unsignedInteger('cartons_received')->default(0);
        });

        Schema::create('warehouse_stock', function (Blueprint $t) {
            $t->id();
            $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->integer('units')->default(0);
            $t->unique(['warehouse_id', 'product_id']);
        });

        // Every change of stock is a row: nothing is edited in place without a trace.
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->integer('units');
            $t->string('type', 20); // received, shipped, adjustment
            $t->string('reference_type', 30)->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('warehouse_stock');
        Schema::dropIfExists('inbound_shipment_items');
        Schema::dropIfExists('inbound_shipments');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('warehouse_id'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('fulfilment'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('fulfilment'));
        Schema::dropIfExists('warehouses');
    }
};
