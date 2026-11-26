<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Part of an order's money goes back (damage, a missing item, a late delivery) while the order stands. One per order.
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('partial_refund_amount', 12, 2)->nullable();
            $table->string('partial_refund_status', 12)->nullable();       // pending | refunded
            $table->string('partial_refund_reason', 300)->nullable();
            $table->string('partial_refund_reference', 120)->nullable();
            $table->timestamp('partial_refunded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['partial_refund_amount', 'partial_refund_status', 'partial_refund_reason', 'partial_refund_reference', 'partial_refunded_at']));
    }
};
