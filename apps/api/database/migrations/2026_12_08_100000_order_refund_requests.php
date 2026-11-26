<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A customer asking for their money back (or to cancel a paid order). Unshipped orders are refunded at once; the rest wait for a decision.
        Schema::create('order_refund_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('reason', 300);
            $table->string('kind', 12);                         // cancel (not shipped yet) | return (shipped or delivered)
            $table->string('status', 12)->default('requested'); // requested | approved | rejected
            $table->string('decision_note', 300)->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refund_requests');
    }
};
