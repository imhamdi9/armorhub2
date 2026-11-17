<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->string('refund_status', 12)->nullable();        // pending (owed to the customer) | refunded (sent back)
            $table->string('refund_reason', 300)->nullable();
            $table->string('refund_reference', 120)->nullable();
            $table->timestamp('refunded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['refund_amount', 'refund_status', 'refund_reason', 'refund_reference', 'refunded_at']));
    }
};
