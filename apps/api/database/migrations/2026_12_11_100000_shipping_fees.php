<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the customer pays for delivery. It travels with the order of the seller who ships and goes to that seller (no commission on it).
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shipping_amount', 12, 2)->default(0);
        });
        Schema::table('order_groups', function (Blueprint $table) {
            $table->decimal('shipping_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('shipping_amount'));
        Schema::table('order_groups', fn (Blueprint $table) => $table->dropColumn('shipping_amount'));
    }
};
