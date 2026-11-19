<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a seller's earnings are sent: a bank account or a mobile wallet. A person at ArmorHub checks it before any money moves.
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('method', 10);                       // bank | wallet
            $table->string('holder', 120);
            $table->string('bank_name', 120)->nullable();
            $table->text('account_number')->nullable();         // encrypted: IBAN or account number
            $table->text('wallet_phone')->nullable();           // encrypted
            $table->string('status', 10)->default('pending');   // pending | verified | rejected
            $table->string('note', 200)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::table('seller_payouts', function (Blueprint $table) {
            $table->string('source', 10)->default('admin');     // admin | seller (who asked for it)
            $table->json('account')->nullable();                // where it goes, as it was when the payout was made
        });
    }

    public function down(): void
    {
        Schema::table('seller_payouts', fn (Blueprint $table) => $table->dropColumn(['source', 'account']));
        Schema::dropIfExists('payout_accounts');
    }
};
