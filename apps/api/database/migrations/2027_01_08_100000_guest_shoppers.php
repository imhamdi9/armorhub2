<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Someone who shops without signing up gets a quiet shopper record (no password anyone knows): a cart, orders and payment work exactly as for
        // everyone else. Their real e-mail is only a place to write to; they can turn it into a full account at any time.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false)->index();
            $table->string('contact_email', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_guest', 'contact_email']));
    }
};
