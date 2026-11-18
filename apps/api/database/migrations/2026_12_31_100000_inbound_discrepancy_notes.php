<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Why a count differs from what the seller announced (damaged, missing, wrong item), written by whoever counted.
        Schema::table('inbound_shipment_items', function (Blueprint $table) {
            $table->string('note', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inbound_shipment_items', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
