<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The products a seller's coupon applies to (the seller picks them; a coupon never touches anything else).
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->json('product_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', fn (Blueprint $t) => $t->dropColumn('product_ids'));
    }
};
