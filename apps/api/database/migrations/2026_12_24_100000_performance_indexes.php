<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Bought together", reviews of what was received and a customer's order list all read orders by product and buyer.
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['product_id', 'customer_id'], 'orders_product_customer_index');
            $table->index(['customer_id', 'status'], 'orders_customer_status_index');
        });

        // The admin queue of returns is read by status.
        Schema::table('order_refund_requests', function (Blueprint $table) {
            $table->index('status', 'refund_requests_status_index');
        });

        // The cart reminder scans the carts that have not been reminded.
        Schema::table('carts', function (Blueprint $table) {
            $table->index('reminded_at', 'carts_reminded_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('carts', fn (Blueprint $table) => $table->dropIndex('carts_reminded_at_index'));
        Schema::table('order_refund_requests', fn (Blueprint $table) => $table->dropIndex('refund_requests_status_index'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_customer_status_index');
            $table->dropIndex('orders_product_customer_index');
        });
    }
};
