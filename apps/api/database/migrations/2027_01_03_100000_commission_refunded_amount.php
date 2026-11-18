<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The part of a commission given back by a partial refund (the amount itself stays as it was charged).
        Schema::table('commissions', function (Blueprint $table) {
            $table->decimal('refunded_amount', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('commissions', fn (Blueprint $t) => $t->dropColumn('refunded_amount'));
    }
};
