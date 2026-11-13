<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Departments exist for the platform (tenant null) and for each market (its own heads and staff).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', fn (Blueprint $t) => $t->unsignedBigInteger('tenant_id')->nullable()->index());
        Schema::table('departments', fn (Blueprint $t) => $t->dropUnique(['key']));
        Schema::table('departments', fn (Blueprint $t) => $t->unique(['key', 'tenant_id']));
    }

    public function down(): void
    {
    }
};
