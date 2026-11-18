<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installation_bookings', function (Blueprint $table) {
            $table->timestamp('no_show_flagged_at')->nullable();   // when the customer and ArmorHub were asked whether the centre came
        });
    }

    public function down(): void
    {
        Schema::table('installation_bookings', fn (Blueprint $table) => $table->dropColumn('no_show_flagged_at'));
    }
};
