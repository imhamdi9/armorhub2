<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industries', function (Blueprint $table) {

            $table->id();

            // Matches IndustryModule::key() of a registered module.
            $table->string('key')->unique();

            $table->string('name');

            $table->text('description')->nullable();

            $table->json('settings')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industries');
    }
};
