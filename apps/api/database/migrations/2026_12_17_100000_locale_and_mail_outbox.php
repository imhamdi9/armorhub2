<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The language a person last used (ar or en): what e-mails and notices are written in when they are not the one making the request.
        Schema::table('users', fn (Blueprint $table) => $table->string('locale', 5)->default('en'));
        // A guest has no account, so the language of the page they ordered from is kept with the order.
        Schema::table('orders', fn (Blueprint $table) => $table->string('guest_locale', 5)->nullable());

        // Every e-mail the platform sends when the "outbox" mailer is on (development and demos): readable by administrators, never leaving the machine.
        Schema::create('mail_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('to', 255)->index();
            $table->string('subject', 255);
            $table->longText('html')->nullable();
            $table->longText('text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_outbox');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('guest_locale'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
