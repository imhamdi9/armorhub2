<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Which product-page layout this product uses (a key from Product::PAGE_TEMPLATES). Null means the default one.
            $table->string('page_template', 40)->nullable()->after('installation');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('page_template');
        });
    }
};
