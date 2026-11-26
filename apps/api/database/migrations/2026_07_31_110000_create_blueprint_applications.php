<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The record of a blueprint having been applied: what was asked for and what it created.
        Schema::create('blueprint_applications', function (Blueprint $table) {
            $table->id();
            $table->string('blueprint_id', 120)->index();
            $table->unsignedInteger('blueprint_version');
            $table->string('params_hash', 64);
            $table->json('params');
            $table->json('outputs')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['blueprint_id', 'params_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprint_applications');
    }
};
