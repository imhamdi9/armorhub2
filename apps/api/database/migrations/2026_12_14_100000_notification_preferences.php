<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How a person wants to be told. The notification inside the app is always kept; e-mail and phone push can be switched off.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_push')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['notify_email', 'notify_push']));
    }
};
