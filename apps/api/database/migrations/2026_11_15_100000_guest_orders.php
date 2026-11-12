<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A guest buys without an account: the order has no customer, only the buyer's name and e-mail and a secret link.
        Schema::table('orders', function (Blueprint $t) {
            $t->string('guest_name', 120)->nullable();
            $t->string('guest_email', 190)->nullable()->index();
            $t->string('guest_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['guest_name', 'guest_email', 'guest_token']));
    }
};
