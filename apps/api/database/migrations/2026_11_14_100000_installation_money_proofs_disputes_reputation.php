<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installation_requests', function (Blueprint $t) {
            $t->string('currency', 3)->nullable();
            $t->string('market', 2)->nullable();
            $t->decimal('price', 12, 2)->nullable();            // the accepted centre price
            $t->decimal('fee_percent', 5, 2)->nullable();       // the platform fee at the time of accepting
            $t->decimal('fee_amount', 12, 2)->nullable();
            $t->decimal('centre_amount', 12, 2)->nullable();    // what the centre earns: price - fee
            $t->decimal('discount_amount', 12, 2)->nullable();  // loyalty points used, paid for by the platform out of its own fee
            $t->unsignedInteger('points_used')->default(0);
            $t->decimal('pay_amount', 12, 2)->nullable();       // what the customer pays: price - discount
            $t->timestamp('terms_accepted_at')->nullable();
            $t->string('terms_version', 12)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('proof_submitted_at')->nullable();
            $t->timestamp('objection_deadline')->nullable();
            $t->timestamp('released_at')->nullable();
            $t->timestamp('warranty_ends_at')->nullable();
            $t->string('proxy_extension', 8)->nullable();      // the temporary substitute number: platform number + this extension
            $t->timestamp('proxy_expires_at')->nullable();
            $t->decimal('refund_amount', 12, 2)->nullable();
            $t->string('refund_status', 12)->nullable();        // pending | refunded
            $t->string('refund_reference', 120)->nullable();
        });

        Schema::table('partners', function (Blueprint $t) {
            $t->string('tier', 10)->default('new');             // new | bronze | silver | gold
            $t->unsignedSmallInteger('strikes')->default(0);
            $t->unsignedSmallInteger('disputes_lost')->default(0);
        });

        Schema::create('installation_invites', function (Blueprint $t) {     // which centres were told about a request (for the "answers how often" measure)
            $t->id();
            $t->unsignedBigInteger('request_id')->index();
            $t->unsignedBigInteger('partner_id')->index();
            $t->timestamps();
            $t->unique(['request_id', 'partner_id']);
        });

        Schema::create('installation_payments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('request_id')->index();
            $t->decimal('amount', 12, 2);
            $t->string('currency', 3);
            $t->string('provider', 40)->nullable();
            $t->string('transaction_id', 120)->nullable()->index();
            $t->string('status', 12)->default('pending');   // pending | completed | expired
            $t->timestamps();
        });

        Schema::create('installation_proofs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('request_id')->index();
            $t->unsignedBigInteger('user_id');
            $t->string('side', 8);                           // centre | customer
            $t->string('kind', 12);                          // before | after | serial | document | evidence
            $t->string('path', 160)->nullable();             // private disk, random name, only ever served to the two sides and admins
            $t->string('text', 300)->nullable();             // serial number / warranty card number / note
            $t->timestamp('taken_at')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->decimal('distance_km', 7, 2)->nullable();    // from the job site, when the position is known
            $t->boolean('flagged')->default(false);          // too far from the job, or an old photo
            $t->timestamps();
        });

        Schema::create('installation_disputes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('request_id')->index();
            $t->unsignedBigInteger('opened_by');
            $t->string('side', 8);
            $t->string('type', 16);                          // not_done | defect | leak | maintenance | other
            $t->text('description');
            $t->string('status', 12)->default('open');       // open | resolved
            $t->string('decision', 16)->nullable();          // release | refund_full | refund_partial
            $t->decimal('refund_amount', 12, 2)->nullable();
            $t->text('decision_note')->nullable();
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });

        Schema::create('installation_reviews', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('request_id')->unique();
            $t->unsignedBigInteger('partner_id')->index();
            $t->unsignedBigInteger('customer_id');
            $t->unsignedTinyInteger('rating');
            $t->string('comment', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('installation_violations', function (Blueprint $t) {  // "this centre tried to take the deal off the platform"
            $t->id();
            $t->unsignedBigInteger('request_id')->index();
            $t->unsignedBigInteger('partner_id')->index();
            $t->unsignedBigInteger('reported_by');
            $t->string('note', 500)->nullable();
            $t->string('status', 12)->default('open');       // open | confirmed | dismissed
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->timestamps();
        });

        Schema::create('centre_wallet_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();   // the centre's company: money belongs to the business
            $t->unsignedBigInteger('partner_id')->nullable();
            $t->unsignedBigInteger('request_id')->nullable()->index();
            $t->decimal('amount', 12, 2);
            $t->string('currency', 3);
            $t->string('status', 12)->default('held');       // held | available | in_withdrawal | paid | void
            $t->unsignedBigInteger('withdrawal_id')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('centre_withdrawals', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('requested_by');
            $t->decimal('amount', 12, 2);
            $t->string('currency', 3);
            $t->string('method', 8);                         // bank | wallet
            $t->string('account_name', 120);
            $t->text('account_number');                      // encrypted (IBAN or wallet phone)
            $t->string('bank_name', 120)->nullable();
            $t->string('status', 12)->default('requested');  // requested | paid | rejected
            $t->string('reference', 120)->nullable();
            $t->string('note', 300)->nullable();
            $t->unsignedBigInteger('processed_by')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('loyalty_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->integer('points');                           // negative when spent
            $t->string('reason', 24);                        // job | review | redeemed | refund_reversal
            $t->unsignedBigInteger('request_id')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['loyalty_entries', 'centre_withdrawals', 'centre_wallet_entries', 'installation_violations', 'installation_reviews', 'installation_disputes', 'installation_proofs', 'installation_payments', 'installation_invites'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('partners', fn (Blueprint $t) => $t->dropColumn(['tier', 'strikes', 'disputes_lost']));
        Schema::table('installation_requests', fn (Blueprint $t) => $t->dropColumn(['currency', 'market', 'price', 'fee_percent', 'fee_amount', 'centre_amount', 'discount_amount', 'points_used', 'pay_amount', 'terms_accepted_at', 'terms_version', 'paid_at', 'started_at', 'proof_submitted_at', 'objection_deadline', 'released_at', 'warranty_ends_at', 'proxy_extension', 'proxy_expires_at', 'refund_amount', 'refund_status', 'refund_reference']));
    }
};
