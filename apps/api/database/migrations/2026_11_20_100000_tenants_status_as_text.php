<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A market can be paused or merged into another one: the old fixed list (active, inactive, suspended) refused those values on MySQL/TiDB.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->string('status', 20)->default('active')->change());
    }

    public function down(): void
    {
    }
};
