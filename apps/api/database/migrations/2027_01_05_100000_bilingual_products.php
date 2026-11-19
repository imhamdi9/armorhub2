<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A listing in two languages: shoppers see the one they browse in (name/description stay the main, usually English, copy).
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_ar', 255)->nullable();
            $table->text('description_ar')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['name_ar', 'description_ar']));
    }
};
