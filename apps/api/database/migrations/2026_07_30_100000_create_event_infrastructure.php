<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every domain event, written in the same transaction as the change that caused it (transactional outbox).
        Schema::create('event_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('name', 120)->index();            // e.g. order.paid
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('aggregate_type', 60)->nullable();
            $table->string('aggregate_id', 60)->nullable();
            $table->json('payload');
            $table->string('actor_type', 40)->nullable();
            $table->string('actor_id', 60)->nullable();
            $table->uuid('correlation_id')->index();
            $table->uuid('causation_id')->nullable();
            $table->string('source', 60)->nullable();          // the module that owns the event
            $table->timestamp('occurred_at', 6);
            $table->timestamps();

            $table->index(['aggregate_type', 'aggregate_id']);
        });

        // One row per subscriber per event: what has been delivered, what failed, what is dead.
        Schema::create('event_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('event_outbox')->cascadeOnDelete();
            $table->string('subscriber', 150);
            $table->string('status', 20)->default('pending')->index();   // pending | processing | delivered | failed | dead | skipped
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'subscriber']);
        });

        // Which modules a tenant has turned off (default: every registered module is on).
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('module', 80);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_modules');
        Schema::dropIfExists('event_deliveries');
        Schema::dropIfExists('event_outbox');
    }
};
