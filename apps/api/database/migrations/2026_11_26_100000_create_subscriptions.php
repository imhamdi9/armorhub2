<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique();             // free | growth | pro
            $table->string('name', 60);
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->unsignedInteger('customer_limit')->nullable(); // customers a month; null means no limit
            $table->decimal('commission_percentage', 5, 2);        // the commission taken from orders on this plan
            $table->unsignedInteger('team_seats')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 10);               // partner | seller
            $table->unsignedBigInteger('subject_id');
            $table->string('plan_key', 20);
            $table->string('status', 12)->default('active');  // active | cancelled
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();           // null on the free plan
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id']);
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('plan_key', 20);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 12)->default('paid');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('reference', 60)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        $currency = 'SAR'; // the first market is Saudi Arabia; an administrator sets other markets' prices in the plan editor
        DB::table('subscription_plans')->insert([
            ['key' => 'free', 'name' => 'Free', 'price_monthly' => 0, 'currency' => $currency, 'customer_limit' => 15, 'commission_percentage' => 10, 'team_seats' => 1, 'is_active' => true, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'growth', 'name' => 'Growth', 'price_monthly' => 299, 'currency' => $currency, 'customer_limit' => 60, 'commission_percentage' => 8, 'team_seats' => 5, 'is_active' => true, 'sort' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pro', 'name' => 'Pro', 'price_monthly' => 799, 'currency' => $currency, 'customer_limit' => 200, 'commission_percentage' => 6, 'team_seats' => 20, 'is_active' => true, 'sort' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
