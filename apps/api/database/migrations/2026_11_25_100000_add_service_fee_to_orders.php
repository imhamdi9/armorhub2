<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // The platform fee the customer pays on top of the invoice: a rate and the amount, fixed when the price is known.
            $table->decimal('service_fee_rate', 5, 2)->default(0)->after('total_amount');
            $table->decimal('service_fee_amount', 12, 2)->default(0)->after('service_fee_rate');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['service_fee_rate', 'service_fee_amount']);
        });
    }
};
