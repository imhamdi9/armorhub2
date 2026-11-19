<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A seller's product goes live only after ArmorHub's catalogue team approves it. Everything that existed before counts as approved.
        Schema::table('products', function (Blueprint $table) {
            $table->string('review_status', 10)->default('approved')->index();      // draft | pending | approved | rejected
            $table->string('review_note', 300)->nullable();                          // why it was sent back
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['review_status', 'review_note', 'reviewed_by', 'submitted_at', 'reviewed_at']));
    }
};
