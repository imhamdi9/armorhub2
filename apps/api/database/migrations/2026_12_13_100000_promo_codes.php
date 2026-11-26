<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Discount codes, paid for by the platform: the seller still receives the full price, ArmorHub's side of the sale shrinks.
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 8);                                   // percent | fixed
            $table->decimal('value', 10, 2);                             // 10 = 10% or 10 currency units
            $table->decimal('max_discount', 10, 2)->nullable();          // cap for a percentage
            $table->decimal('min_order', 12, 2)->nullable();             // smallest items total that qualifies
            $table->string('market', 2)->nullable();                     // null = every market
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();          // in total
            $table->unsignedSmallInteger('per_customer_limit')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('promo_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promo_code_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('status', 10)->default('reserved');           // reserved (at checkout) | released (purchase cancelled)
            $table->timestamps();
        });

        Schema::table('carts', fn (Blueprint $table) => $table->string('promo_code', 40)->nullable());
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('promo_code', 40)->nullable();
        });
        Schema::table('order_groups', fn (Blueprint $table) => $table->decimal('discount_amount', 12, 2)->default(0));
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_redemptions');
        Schema::dropIfExists('promo_codes');
        Schema::table('carts', fn (Blueprint $table) => $table->dropColumn('promo_code'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['discount_amount', 'promo_code']));
        Schema::table('order_groups', fn (Blueprint $table) => $table->dropColumn('discount_amount'));
    }
};
