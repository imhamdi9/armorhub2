<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The areas a market is open in. A market with no active zone is open everywhere (as before); one with zones serves only inside them,
        // so a launch can be one district at a time and widen when the first has enough centres.
        Schema::create('service_zones', function (Blueprint $table) {
            $table->id();
            $table->string('market', 2)->index();
            $table->string('key', 60);
            $table->string('name_en', 120);
            $table->string('name_ar', 120)->nullable();
            $table->string('city', 80)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('radius_km')->default(10);
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['market', 'key']);
        });

        // Where customers asked for help outside the open zones: the demand that says where to open next. Coordinates are rounded to about a kilometre.
        Schema::create('zone_waitlist', function (Blueprint $table) {
            $table->id();
            $table->string('market', 2)->index();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('latitude', 6, 2);
            $table->decimal('longitude', 6, 2);
            $table->string('city', 80)->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'latitude', 'longitude']);
        });

        // Requests go to the nearest few qualified centres first, then to the next few if too few answer.
        Schema::table('installation_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('invite_wave')->default(1);
            $table->unsignedBigInteger('zone_id')->nullable();
        });
        Schema::table('installation_invites', function (Blueprint $table) {
            $table->unsignedTinyInteger('wave')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('installation_invites', fn (Blueprint $t) => $t->dropColumn('wave'));
        Schema::table('installation_requests', fn (Blueprint $t) => $t->dropColumn(['invite_wave', 'zone_id']));
        Schema::dropIfExists('zone_waitlist');
        Schema::dropIfExists('service_zones');
    }
};
