<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Which campaign brought an order or an application (only when the visitor allowed marketing), and a log of what was sent to the ad platforms. */
    public function up(): void
    {
        foreach (['orders', 'partner_applications'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('attribution')->nullable();
                $t->json('ad_context')->nullable();
            });
        }

        Schema::create('ad_conversion_logs', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 20)->index();
            $t->string('event_name', 40);
            $t->string('event_id', 80)->index();
            $t->string('subject_type', 30);
            $t->unsignedBigInteger('subject_id');
            $t->unsignedBigInteger('tenant_id')->nullable()->index();
            $t->string('status', 12);                     // sent | failed | skipped
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('note', 300)->nullable();
            $t->timestamps();
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_conversion_logs');
        foreach (['orders', 'partner_applications'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['attribution', 'ad_context']));
        }
    }
};
