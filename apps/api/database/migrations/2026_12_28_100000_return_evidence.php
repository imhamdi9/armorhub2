<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_refund_requests', function (Blueprint $table) {
            $table->json('photos')->nullable();   // up to four pictures of the problem, uploaded by the customer
        });
    }

    public function down(): void
    {
        Schema::table('order_refund_requests', fn (Blueprint $table) => $table->dropColumn('photos'));
    }
};
