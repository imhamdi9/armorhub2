<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->json('photos')->nullable();               // up to four uploaded pictures of the product as received
        });

        // A shopper asks, the seller answers, and everyone reads the answer on the product page.
        Schema::create('product_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('question', 300);
            $table->text('answer')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->string('status', 20)->default('published');   // published | hidden (hidden by a platform administrator)
            $table->timestamps();

            $table->index(['product_id', 'status', 'answered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_questions');
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};
