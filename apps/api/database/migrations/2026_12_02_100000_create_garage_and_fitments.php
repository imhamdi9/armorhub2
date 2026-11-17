<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The customer's own cars: structured (make, model, year), so a part can be checked against them and a fitting request names them exactly.
        Schema::create('customer_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_model_id')->constrained('vehicle_models');
            $table->unsignedSmallInteger('year');
            $table->string('nickname', 60)->nullable();
            $table->text('vin')->nullable();              // encrypted
            $table->string('color', 30)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index('customer_id');
        });

        // Which cars a product fits. A row with neither make nor model means every vehicle; years are an optional range.
        Schema::create('product_fitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_make_id')->nullable()->constrained('vehicle_makes');
            $table->foreignId('vehicle_model_id')->nullable()->constrained('vehicle_models');
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('note', 160)->nullable();
            $table->timestamps();
            $table->index('product_id');
        });

        Schema::table('products', function (Blueprint $table) {
            // When true the product cannot be bought without saying which car it is for, and it must fit.
            $table->boolean('fitment_required')->default(false);
        });
        Schema::table('installation_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_vehicle_id')->nullable()->after('subject');
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->unsignedBigInteger('vehicle_id')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', fn (Blueprint $t) => $t->dropColumn('vehicle_id'));
        Schema::table('installation_requests', fn (Blueprint $t) => $t->dropColumn('customer_vehicle_id'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('fitment_required'));
        Schema::dropIfExists('product_fitments');
        Schema::dropIfExists('customer_vehicles');
    }
};
