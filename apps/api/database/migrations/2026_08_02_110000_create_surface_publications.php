<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A request to make a surface's draft live: waiting for a second admin, and/or waiting for its publish time.
        Schema::create('surface_publications', function (Blueprint $table) {
            $table->id();
            $table->string('surface_key', 120);
            $table->string('locale', 5);
            $table->string('content_hash', 40);                          // the draft as it was when requested: any later edit makes the request stale
            $table->string('status', 20)->default('pending');           // pending | scheduled | published | rejected | cancelled | stale
            $table->string('note', 200)->nullable();
            $table->timestamp('publish_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedInteger('published_version')->nullable();
            $table->timestamps();

            $table->index(['status', 'publish_at']);
            $table->index(['surface_key', 'locale', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surface_publications');
    }
};
