<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Numbers for documents people refer to: RFQ-2026-000012, QT-2026-000034.
        Schema::create('document_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last')->default(0);
            $table->unique(['kind', 'year']);
        });

        // How a product is sold: at its price, or only against a quote.
        Schema::table('products', function (Blueprint $table) {
            $table->string('sale_mode', 10)->default('fixed');      // fixed | quote
        });

        // A customer asks for a price: for a quote-only product, or for a part nobody lists (sourcing).
        Schema::create('product_quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->unsignedBigInteger('company_id')->index();      // the store (market) of the customer
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('product_id')->nullable()->constrained();
            $table->string('category', 80)->nullable();             // what kind of part, when no product is named: decides which sellers are asked
            $table->string('title', 160);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 12)->default('open')->index();  // open | accepted | closed | expired
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('accepted_quote_id')->nullable();
            $table->timestamps();
        });

        Schema::create('product_quote_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('product_quote_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('seller_id')->index();       // the seller's company
            $table->timestamps();
            $table->unique(['request_id', 'seller_id']);
        });

        Schema::create('product_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('request_id')->constrained('product_quote_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('seller_id')->index();
            $table->foreignId('product_id')->constrained();         // the seller's own product that fills the request
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('available_qty')->nullable();
            $table->unsignedSmallInteger('lead_days')->default(0);
            $table->string('condition', 12)->default('new');        // new | oem | aftermarket | used
            $table->unsignedSmallInteger('warranty_days')->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamp('valid_until');
            $table->string('status', 12)->default('submitted');     // submitted | accepted | rejected | withdrawn
            $table->timestamps();
            $table->unique(['request_id', 'seller_id']);
        });

        // Installation offers become formal quotes too: numbered, with a validity, a warranty and the earliest day the centre can start.
        Schema::table('installation_offers', function (Blueprint $table) {
            $table->string('number', 30)->nullable()->unique();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedSmallInteger('warranty_days')->nullable();
            $table->date('earliest_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('installation_offers', fn (Blueprint $t) => $t->dropColumn(['number', 'valid_until', 'warranty_days', 'earliest_date']));
        Schema::dropIfExists('product_quotes');
        Schema::dropIfExists('product_quote_invites');
        Schema::dropIfExists('product_quote_requests');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('sale_mode'));
        Schema::dropIfExists('document_numbers');
    }
};
