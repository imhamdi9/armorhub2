<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Per market: is selling (sellers and their products) on, and are installation centres on. Both start on. */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('selling_enabled')->default(true);
            $table->boolean('installation_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['selling_enabled', 'installation_enabled']);
        });
    }
};
