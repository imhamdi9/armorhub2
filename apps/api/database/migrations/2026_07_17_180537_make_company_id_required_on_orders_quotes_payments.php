<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable(false)
                ->change();
        });


        Schema::table('installation_quotes', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable(false)
                ->change();
        });


        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable(false)
                ->change();
        });
    }


    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->change();
        });


        Schema::table('installation_quotes', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->change();
        });


        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->change();
        });
    }
};