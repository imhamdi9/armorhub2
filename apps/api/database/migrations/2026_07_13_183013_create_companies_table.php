<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->uuid('uuid')
                ->unique();

            $table->string('name');

            // Public address of the company's storefront: /s/<slug>
            $table->string('slug')
                ->nullable()
                ->unique();

            $table->string('legal_name')
                ->nullable();

            $table->string('email')
                ->nullable();

            $table->string('phone')
                ->nullable();

            $table->string('logo')
                ->nullable();

            $table->enum('type', [
                'platform',
                'partner',
                'supplier'
            ])
            ->default('partner');


            $table->enum('status', [
                'pending',
                'active',
                'inactive',
                'suspended'
            ])
            ->default('pending');


            $table->json('settings')
                ->nullable();


            $table->timestamps();

            $table->softDeletes();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};