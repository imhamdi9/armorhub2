<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->json('price_list')->nullable();                 // what the centre usually charges, shown next to its offers
        });
        Schema::table('installation_reviews', function (Blueprint $table) {
            $table->string('reply', 500)->nullable();               // the centre's public answer to the customer
            $table->timestamp('replied_at')->nullable();
        });
        Schema::table('installation_bookings', function (Blueprint $table) {
            $table->timestamp('on_the_way_at')->nullable();         // the centre said it is heading to the customer
        });
    }

    public function down(): void
    {
        Schema::table('partners', fn (Blueprint $t) => $t->dropColumn('price_list'));
        Schema::table('installation_reviews', fn (Blueprint $t) => $t->dropColumn(['reply', 'replied_at']));
        Schema::table('installation_bookings', fn (Blueprint $t) => $t->dropColumn('on_the_way_at'));
    }
};
