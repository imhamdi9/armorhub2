<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One application table for both kinds of business: a service partner (joins a store) or a seller
        // (joins a marketplace to sell its own products). Sellers have no store yet, so company_id is optional.
        Schema::table('partner_applications', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });
        Schema::table('partner_applications', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->string('type', 20)->default('partner')->after('id')->index();   // partner | seller
            $table->string('registration_number', 40)->nullable();
            $table->string('tax_number', 30)->nullable();
        });

        // Verification documents: private files, reviewed by hand. Never public.
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('partner_applications')->cascadeOnDelete();
            $table->string('kind', 30);            // id | commercial_registration | tax_certificate | license
            $table->string('original_name', 200);
            $table->string('path', 300);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->string('sha256', 64);
            $table->timestamps();

            $table->unique(['application_id', 'kind']);
        });

        // Products can belong to a seller. Null = the shared catalog (platform / store owners).
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('industry_id')->constrained('companies')->nullOnDelete();
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_id');
        });
        Schema::dropIfExists('application_documents');
        Schema::table('partner_applications', fn (Blueprint $t) => $t->dropColumn(['type', 'registration_number', 'tax_number']));
    }
};
