<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('amount', 10, 2)
                ->default(0);

            $table->decimal('percentage', 5, 2)
                ->default(0);

            $table->string('status')
                ->default('pending');

        });
    }


    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {

            $table->dropForeign(['order_id']);

            $table->dropColumn([
                'order_id',
                'amount',
                'percentage',
                'status'
            ]);

        });
    }
};