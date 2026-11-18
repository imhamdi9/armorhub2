<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('delivery_code', 8)->nullable();          // the customer tells it to the courier at the door: proof the parcel reached them
            $table->unsignedTinyInteger('attempts')->default(0);     // failed delivery attempts so far
            $table->string('failed_reason', 250)->nullable();
            $table->date('reschedule_for')->nullable();              // the day the customer asked for after a failed attempt
            $table->string('reschedule_note', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['delivery_code', 'attempts', 'failed_reason', 'reschedule_for', 'reschedule_note']);
        });
    }
};
