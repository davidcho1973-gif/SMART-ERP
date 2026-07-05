<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each site gets an optional geofence: a center point + a radius (metres).
        Schema::table('sites', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('code');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->unsignedInteger('radius_m')->default(150)->after('lng');
        });

        // A punch remembers where the clock-in / clock-out actually happened, how
        // accurate the fix was, and whether it fell inside the site geofence.
        // geo_ok is nullable: null = no coordinates (permission denied / unavailable).
        Schema::table('punches', function (Blueprint $table) {
            $table->decimal('in_lat', 10, 7)->nullable()->after('source');
            $table->decimal('in_lng', 10, 7)->nullable()->after('in_lat');
            $table->decimal('in_acc', 8, 2)->nullable()->after('in_lng');
            $table->decimal('out_lat', 10, 7)->nullable()->after('in_acc');
            $table->decimal('out_lng', 10, 7)->nullable()->after('out_lat');
            $table->decimal('out_acc', 8, 2)->nullable()->after('out_lng');
            $table->boolean('in_geo_ok')->nullable()->after('out_acc');
            $table->boolean('out_geo_ok')->nullable()->after('in_geo_ok');
        });
    }

    public function down(): void
    {
        Schema::table('punches', function (Blueprint $table) {
            $table->dropColumn([
                'in_lat', 'in_lng', 'in_acc',
                'out_lat', 'out_lng', 'out_acc',
                'in_geo_ok', 'out_geo_ok',
            ]);
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'radius_m']);
        });
    }
};
