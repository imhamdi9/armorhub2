<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** What each ad account spent per campaign per day, read from the ad platforms (read only). */
    public function up(): void
    {
        Schema::create('ad_spend_daily', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_tenant_id')->nullable()->index();   // null: the platform's own account; else the market that has its own account
            $t->string('platform', 20);
            $t->string('account_id', 60);
            $t->date('day');
            $t->string('campaign_id', 60);
            $t->string('campaign_name', 200);
            $t->string('campaign_key', 200)->index();                        // the name as the campaign links write it (lower case, dashes)
            $t->decimal('spend', 14, 2)->default(0);
            $t->string('currency', 8)->nullable();
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->timestamps();
            $t->unique(['platform', 'account_id', 'day', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_spend_daily');
    }
};
