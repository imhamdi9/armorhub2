<?php

use App\Support\CategoryTaxonomy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $t->string('key')->unique();
            $t->string('name_en');
            $t->string('name_ar')->nullable();
            $t->unsignedInteger('position')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::table('products', function (Blueprint $t) {
            $t->string('sku', 40)->nullable()->unique();
            $t->unsignedInteger('units_per_carton')->nullable();
            $t->decimal('carton_length_cm', 8, 2)->nullable();
            $t->decimal('carton_width_cm', 8, 2)->nullable();
            $t->decimal('carton_height_cm', 8, 2)->nullable();
            $t->decimal('carton_weight_kg', 8, 3)->nullable();
            $t->unsignedInteger('stock_cartons')->default(0);
        });

        CategoryTaxonomy::ensureDefault();

        // Everything already on sale keeps its category (unknown ones are filed under "Other") and gets a SKU.
        foreach (DB::table('products')->whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category') as $key) {
            CategoryTaxonomy::adopt($key);
        }
        foreach (DB::table('products')->orderBy('id')->get(['id', 'market']) as $p) {
            DB::table('products')->where('id', $p->id)->update(['sku' => sprintf('AH-%s-%06d', $p->market ?: 'GL', $p->id)]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->dropColumn(['sku', 'units_per_carton', 'carton_length_cm', 'carton_width_cm', 'carton_height_cm', 'carton_weight_kg', 'stock_cartons']);
        });
        Schema::dropIfExists('product_categories');
    }
};
