<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The money ledger: double entry, append only. Every movement of money is one transaction whose lines add up to zero, so what comes in always
 * equals what goes out. Mistakes are corrected with a new, opposite transaction, never by editing an old one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120);                       // e.g. seller:12:held
            $table->string('currency', 3);
            $table->string('type', 12);                        // asset | liability | revenue | expense
            $table->string('name', 160);
            $table->string('owner_type', 20)->nullable();      // seller | centre | partner | company
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamps();
            $table->unique(['code', 'currency']);
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 160)->unique();  // posting the same event twice is a no-op
            $table->string('kind', 40)->index();               // order_paid, seller_released, payout_paid...
            $table->string('description', 255)->nullable();
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('posted_at')->useCurrent();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('ledger_transactions');
            $table->foreignId('account_id')->constrained('ledger_accounts');
            $table->string('currency', 3);
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->timestamp('posted_at')->useCurrent();
            $table->index(['account_id', 'posted_at']);
        });

        $driver = DB::getDriverName();
        foreach (['ledger_entries', 'ledger_transactions'] as $t) {
            if ($driver === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$t}_no_update BEFORE UPDATE ON {$t} BEGIN SELECT RAISE(ABORT, 'The ledger is append-only.'); END;");
                DB::unprepared("CREATE TRIGGER {$t}_no_delete BEFORE DELETE ON {$t} BEGIN SELECT RAISE(ABORT, 'The ledger is append-only.'); END;");
            } elseif ($driver === 'pgsql') {
                DB::unprepared("CREATE OR REPLACE FUNCTION ledger_append_only() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'The ledger is append-only.'; END; \$\$ LANGUAGE plpgsql;");
                DB::unprepared("CREATE TRIGGER {$t}_append_only BEFORE UPDATE OR DELETE ON {$t} FOR EACH ROW EXECUTE FUNCTION ledger_append_only();");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
    }
};
