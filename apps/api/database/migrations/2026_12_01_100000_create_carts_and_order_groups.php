<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a customer has put aside to buy together.
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 40)->nullable();     // the chosen option, as the product's price rule knows it
            $table->unsignedBigInteger('target_id')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['cart_id', 'product_id', 'target_type', 'target_id']);
        });

        // One purchase of several things, maybe from several sellers: paid once, split into one order per line so each seller,
        // shipment and settlement keeps its own record.
        Schema::create('order_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();     // the store (market) the customer belongs to
            $table->foreignId('customer_id')->constrained('users');
            $table->string('currency', 3);
            $table->decimal('items_amount', 12, 2);                // the invoices of all its orders
            $table->decimal('service_fee_amount', 12, 2)->default(0);
            $table->decimal('payable_amount', 12, 2);              // what the customer pays once
            $table->string('status', 20)->default('awaiting_payment')->index();   // awaiting_payment | paid | cancelled
            $table->json('shipping_address')->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('transaction_id', 120)->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->index()->after('company_id');
            $table->unsignedSmallInteger('quantity')->default(1)->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['group_id', 'quantity']);
        });
        Schema::dropIfExists('order_groups');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
