<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('installation', 12)->default('none')->after('requires_shipping'); // none | optional | required
        });

        // The measures the ranking uses; filled in by the rating engine and kept on the partner so ranking is one query.
        Schema::table('partners', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('completed_jobs')->default(0);
            $table->unsignedInteger('avg_response_minutes')->nullable();
            $table->decimal('acceptance_rate', 5, 2)->nullable(); // % of requests answered with an offer
        });

        Schema::create('installation_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();   // the store (market) the customer belongs to
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();   // the goods order, when the parts were bought here
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('subject', 160);                       // e.g. "Toyota Camry 2020" or "iPhone 14"
            $table->json('parts');                                // what must be fitted or repaired
            $table->string('work_type', 16)->default('install');  // install | repair | maintenance
            $table->text('notes')->nullable();
            // Only exact when the customer allowed the device location; never typed in by hand.
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('location_consent');
            $table->string('city', 80)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('status', 24)->default('open')->index(); // open | offers_received | selected | awaiting_payment | paid | in_progress | proof_submitted | released | disputed | cancelled | expired
            $table->unsignedBigInteger('selected_offer_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('installation_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->index();
            $table->unsignedBigInteger('partner_id')->index();
            $table->decimal('price', 12, 2);
            $table->unsignedSmallInteger('eta_days')->default(1);
            $table->string('note', 300)->nullable();
            $table->string('status', 16)->default('submitted'); // submitted | selected | rejected | withdrawn
            $table->decimal('distance_km', 7, 2)->nullable();   // exact, kept server-side; customers only ever see it rounded
            $table->timestamps();
            $table->unique(['request_id', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_offers');
        Schema::dropIfExists('installation_requests');
        Schema::table('partners', fn (Blueprint $t) => $t->dropColumn(['rating_avg', 'rating_count', 'completed_jobs', 'avg_response_minutes', 'acceptance_rate']));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('installation'));
    }
};
