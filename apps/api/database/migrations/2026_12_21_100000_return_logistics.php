<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_refund_requests', function (Blueprint $table) {
            $table->string('return_code', 8)->nullable();        // the customer gives it to whoever collects the parcel: proof it was handed over
            $table->date('pickup_day')->nullable();               // the day the customer chose for the collection
            $table->string('pickup_note', 200)->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('inspection', 12)->nullable();         // passed | failed
            $table->string('inspection_note', 300)->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_refund_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collected_by');
            $table->dropConstrainedForeignId('inspected_by');
            $table->dropColumn(['return_code', 'pickup_day', 'pickup_note', 'collected_at', 'inspection', 'inspection_note', 'inspected_at']);
        });
    }
};
