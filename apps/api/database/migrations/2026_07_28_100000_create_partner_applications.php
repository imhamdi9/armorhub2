<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A business asks to join a store's marketplace as a service partner. Nothing here grants access:
        // the account, partner profile and role are only created when a reviewer approves.
        Schema::create('partner_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();   // the store they apply to
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('business_name', 150);
            $table->string('contact_name', 120);
            $table->string('email');
            $table->string('email_canonical')->index();
            $table->string('phone', 20);
            $table->string('city', 100);
            $table->string('country', 2);
            $table->string('description', 400)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('password');                                          // hashed; becomes the account's password
            $table->string('status', 20)->default('pending')->index();           // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 300)->nullable();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_applications');
    }
};
