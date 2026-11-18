<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['order_groups', 'orders'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('delivery_speed', 12)->default('standard');   // standard | express
                $table->date('preferred_day')->nullable();                   // the day the customer would like it delivered
                $table->string('gift_note', 200)->nullable();                // a message for the person receiving it: the seller packs it as a gift
            });
        }
    }

    public function down(): void
    {
        foreach (['order_groups', 'orders'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['delivery_speed', 'preferred_day', 'gift_note']);
            });
        }
    }
};
