<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A customer's saved delivery addresses, so repeat purchases do not mean retyping.
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('label', 40)->nullable();       // Home, Work...
            $table->string('name', 120);
            $table->string('phone', 30);
            $table->string('line1', 200);
            $table->string('line2', 200)->nullable();
            $table->string('city', 100);
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
