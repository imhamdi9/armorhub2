<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who at the centre does the job. A technician sees only the jobs given to them; the manager sees them all.
        Schema::table('installation_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('technician_id')->nullable()->index();
            $table->timestamp('assigned_at')->nullable();
        });

        // What was used on the job: the film, the coating, the kit. It goes into the work order and the warranty record.
        Schema::create('installation_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('installation_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name', 160);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->default('pcs');
            $table->string('batch', 80)->nullable();               // roll or batch number, for the warranty
            $table->unsignedBigInteger('recorded_by');
            $table->timestamps();
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_materials');
        Schema::table('installation_requests', fn (Blueprint $t) => $t->dropColumn(['technician_id', 'assigned_at']));
    }
};
