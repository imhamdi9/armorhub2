<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            // A paid plan is paid right after the application is sent, before anyone has an account: the token proves who is paying.
            $table->string('plan_pay_token', 64)->nullable();
            $table->string('plan_payment_reference', 60)->nullable();
            $table->timestamp('plan_paid_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            $table->dropColumn(['plan_pay_token', 'plan_payment_reference', 'plan_paid_at']);
        });
    }
};
