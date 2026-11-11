<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

            $table->string('name');

            $table->foreignId('industry_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('domain')
                ->nullable()
                ->unique();

            $table->enum('status', [
                'active',
                'inactive',
                'suspended'
            ])
            ->default('active');

            $table->json('settings')
                ->nullable();

            $table->timestamps();

            $table->softDeletes();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};