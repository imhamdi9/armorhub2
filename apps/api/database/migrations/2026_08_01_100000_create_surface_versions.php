<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every saved version of every editable surface. Nothing is ever overwritten: publishing and rolling back add rows.
        Schema::create('surface_versions', function (Blueprint $table) {
            $table->id();
            $table->string('surface_key', 120);
            $table->unsignedBigInteger('tenant_id')->nullable();        // null = platform-wide
            $table->string('locale', 5);                                 // en | ar
            $table->unsignedInteger('version');
            $table->json('content');
            $table->string('status', 20)->default('draft');              // draft | published | archived
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['surface_key', 'tenant_id', 'locale', 'status']);
            $table->unique(['surface_key', 'tenant_id', 'locale', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surface_versions');
    }
};
