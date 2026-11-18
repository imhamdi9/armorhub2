<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A conversation about one order between the customer, the seller and (when called in) ArmorHub. A "problem" is a message the customer flags for ArmorHub.
        Schema::create('order_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->string('side', 10);                     // customer | seller | support
            $table->string('kind', 10)->default('message'); // message | problem
            $table->text('body');
            $table->timestamp('handled_at')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->timestamps();
            $table->index(['kind', 'handled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_messages');
    }
};
