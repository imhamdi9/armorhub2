<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When a delivered order's earnings unlock, if the market holds them for some days after delivery (null = not delayed).
        Schema::table('seller_ledger_entries', function (Blueprint $table) {
            $table->timestamp('release_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('seller_ledger_entries', fn (Blueprint $t) => $t->dropColumn('release_at'));
    }
};
