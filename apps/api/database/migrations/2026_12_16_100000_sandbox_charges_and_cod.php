<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The sandbox provider's hosted checkout: a charge waits here until the (test) card is approved or declined on its own page.
        Schema::create('sandbox_charges', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 48)->unique();
            $table->bigInteger('payment_id');                       // the order id, a negative installation job id, or a cart purchase's payment id
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 10)->default('pending');       // pending | paid | cancelled
            $table->string('return_url', 255);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 160)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_charges');
    }
};
