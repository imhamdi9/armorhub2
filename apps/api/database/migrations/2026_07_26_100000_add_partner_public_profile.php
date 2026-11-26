<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A partner appears on the public home page only when its manager opts in.
        Schema::table('partners', function (Blueprint $table) {
            $table->boolean('public_profile')->default(false);
            $table->string('description', 400)->nullable();
            $table->string('logo_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partners', fn (Blueprint $t) => $t->dropColumn(['public_profile', 'description', 'logo_url']));
    }
};
