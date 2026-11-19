<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A tenant's own version of a workflow (e.g. a market that allows cancelling later). The default comes from the module.
        Schema::create('workflow_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_key', 120);
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workflow_key', 'tenant_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_overrides');
    }
};
