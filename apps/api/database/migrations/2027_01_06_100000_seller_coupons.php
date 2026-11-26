<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A coupon a seller makes: it applies only to that seller's goods and the seller bears its cost.
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->index();
        });
        // The part of an order's discount that the seller pays (the rest, if any, is the platform's).
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('seller_discount_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', fn (Blueprint $t) => $t->dropColumn('company_id'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('seller_discount_amount'));
    }
};
