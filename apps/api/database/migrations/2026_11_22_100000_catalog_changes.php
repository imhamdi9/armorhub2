<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A market admin proposes catalog and category changes; they go live only when the platform admin approves them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_changes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable()->index();
            $t->unsignedBigInteger('requested_by')->index();
            $t->string('target', 60);              // "category" or "catalog:<alias>" (vehicle_part, room_type...)
            $t->string('action', 10);              // create, update, delete
            $t->unsignedBigInteger('record_id')->nullable();
            $t->json('payload')->nullable();
            $t->string('summary', 200)->nullable();
            $t->string('status', 10)->default('pending')->index(); // pending, approved, rejected
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->string('note', 300)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_changes');
    }
};
