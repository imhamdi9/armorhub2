<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A market is a country (ArmorHub Egypt) and runs several industries: cars, electronics, beauty...
        Schema::create('tenant_industries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('industry_id')->index();
            $t->timestamps();
            $t->unique(['tenant_id', 'industry_id']);
        });

        // TiDB and MySQL refuse to change a column that a foreign key uses: drop the key (the column stays indexed), then allow empty.
        try {
            Schema::table('tenants', fn (Blueprint $t) => $t->dropForeign(['industry_id']));
        } catch (\Throwable) {
            // no such key (already dropped, or the database never made one)
        }
        Schema::table('tenants', fn (Blueprint $t) => $t->unsignedBigInteger('industry_id')->nullable()->change());

        // Every existing tenant keeps its industry as the first industry of its market.
        foreach (DB::table('tenants')->whereNotNull('industry_id')->get(['id', 'industry_id']) as $row) {
            DB::table('tenant_industries')->insertOrIgnore(['tenant_id' => $row->id, 'industry_id' => $row->industry_id, 'created_at' => now(), 'updated_at' => now()]);
        }

        // A centre works in one industry (or all of them when empty): an auto installer is not asked about a beauty job.
        Schema::table('partners', fn (Blueprint $t) => $t->unsignedBigInteger('industry_id')->nullable()->after('company_id'));
        Schema::table('installation_requests', fn (Blueprint $t) => $t->unsignedBigInteger('industry_id')->nullable()->after('product_id'));
    }

    public function down(): void
    {
        Schema::table('installation_requests', fn (Blueprint $t) => $t->dropColumn('industry_id'));
        Schema::table('partners', fn (Blueprint $t) => $t->dropColumn('industry_id'));
        Schema::dropIfExists('tenant_industries');
    }
};
