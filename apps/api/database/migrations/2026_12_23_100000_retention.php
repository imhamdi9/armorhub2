<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 12)->nullable()->unique();     // what a customer shares with friends
            $table->unsignedBigInteger('referred_by')->nullable()->index(); // who brought this customer
        });

        // One entry per cause: the same delivery or the same referral can never pay out twice.
        Schema::table('loyalty_entries', function (Blueprint $table) {
            $table->string('ref', 40)->nullable();
            $table->unique(['user_id', 'ref']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable();                   // when the "you left something behind" reminder was sent
        });
    }

    public function down(): void
    {
        Schema::table('carts', fn (Blueprint $table) => $table->dropColumn('reminded_at'));
        Schema::table('loyalty_entries', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'ref']);
            $table->dropColumn('ref');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropColumn(['referral_code', 'referred_by']);
        });
    }
};
