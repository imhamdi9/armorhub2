<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {   
        

        Schema::table('installation_centers', function (Blueprint $table) {

         $table->decimal('latitude', 10, 7)
            ->nullable()
            ->after('city');

         $table->decimal('longitude', 10, 7)
            ->nullable()
            ->after('latitude');

         $table->boolean('location_enabled')
            ->default(false)
            ->after('longitude');

        });
    
        Schema::table('installation_centers', function (Blueprint $table) {
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {   
        
        Schema::table('installation_centers', function (Blueprint $table) {

         $table->dropColumn([
            'latitude',
            'longitude',
            'location_enabled',
         ]);

        });

        Schema::table('installation_centers', function (Blueprint $table) {
            //
        });
    }
};
