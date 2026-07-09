<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen the site geofence. Tight radii (some well under 60 m) plus ordinary
 * indoor GPS drift were flagging legitimate on-site clock-ins as "off-site".
 * Raise the column default and every existing site to at least a 500 m floor;
 * larger fences are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->unsignedInteger('radius_m')->default(500)->change();
        });

        DB::table('sites')
            ->where(function ($q) {
                $q->whereNull('radius_m')->orWhere('radius_m', '<', 500);
            })
            ->update(['radius_m' => 500]);
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->unsignedInteger('radius_m')->default(150)->change();
        });
    }
};
