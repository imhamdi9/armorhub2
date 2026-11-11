<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a seller is owed. Entries are written by event subscribers and never edited except to change status.
        Schema::create('seller_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('pending')->index();   // pending | paid
            $table->string('reference', 120)->nullable();                // e.g. the bank transfer number
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seller_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();   // the seller
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                                            // sale (+) | commission (-)
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('held')->index();              // held (until delivered) | available | paid
            $table->timestamp('available_at')->nullable();
            $table->foreignId('payout_id')->nullable()->constrained('seller_payouts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'type']);
            $table->index(['company_id', 'currency', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_ledger_entries');
        Schema::dropIfExists('seller_payouts');
    }
};
