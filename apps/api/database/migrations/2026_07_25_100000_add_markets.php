<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The country a tenant operates in (a key of config/markets.php). Null = the platform default.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('market', 2)->nullable()->after('industry_id');
        });

        // Tax is recorded on the order at the time it was priced, so later rate changes never rewrite history.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('market', 2)->nullable()->after('currency');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('market');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
        });

        // A product can be limited to one country (its prices are in that country's currency); null = every market.
        Schema::table('products', function (Blueprint $table) {
            $table->string('market', 2)->nullable()->after('industry_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('tax_number', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('market'));
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('tax_number'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('phone'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['market', 'tax_rate', 'tax_amount']));
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn('market'));
    }
};
