<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('images')->nullable()->after('description');                       // up to 8 image references (https or /media/<name>)
            $table->decimal('compare_at_price', 12, 2)->nullable()->after('base_price');    // the "was" price shown struck through
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['images', 'compare_at_price']);
        });
    }
};
