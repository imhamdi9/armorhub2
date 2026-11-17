<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a centre decides for itself: how far it travels, how much it can take, what it will not work for, what it does.
        Schema::table('partners', function (Blueprint $table) {
            $table->unsignedSmallInteger('service_radius_km')->default(100);
            $table->unsignedSmallInteger('daily_capacity')->default(5);      // jobs it takes on one day
            $table->unsignedTinyInteger('bays')->default(1);                 // jobs it can do at the same time
            $table->unsignedTinyInteger('job_hours')->default(3);            // how long a typical job keeps a bay busy
            $table->decimal('min_job_price', 12, 2)->nullable();             // it does not answer for less
            $table->boolean('accepts_requests')->default(true);              // off while on holiday or full
            $table->json('work_types')->nullable();                          // install | repair | maintenance; empty = all
        });

        Schema::create('partner_working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');                          // 0 = Sunday ... 6 = Saturday
            $table->string('opens', 5);
            $table->string('closes', 5);
            $table->timestamps();
            $table->unique(['partner_id', 'weekday']);
        });

        Schema::create('partner_time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reason', 120)->nullable();
            $table->timestamps();
            $table->unique(['partner_id', 'date']);
        });

        // The day and time the customer and the centre agreed for a paid job.
        Schema::create('installation_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('installation_requests')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 12)->default('booked');                 // booked | cancelled | done
            $table->unsignedTinyInteger('reschedules')->default(0);
            $table->string('cancel_reason', 200)->nullable();
            $table->timestamps();
            $table->index(['partner_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_bookings');
        Schema::dropIfExists('partner_time_off');
        Schema::dropIfExists('partner_working_hours');
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['service_radius_km', 'daily_capacity', 'bays', 'job_hours', 'min_job_price', 'accepts_requests', 'work_types']);
        });
    }
};
