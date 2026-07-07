<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // team work shift — minutes since midnight. Null = not configured, so the
        // team keeps the legacy "guess from punch-in" behavior until a lead sets it.
        Schema::table('teams', function (Blueprint $table) {
            $table->integer('shift_in')->nullable()->after('color');   // e.g. 300 = 5:00 AM
            $table->integer('shift_out')->nullable()->after('shift_in'); // e.g. 840 = 2:00 PM
            $table->integer('sat_in')->nullable()->after('shift_out');   // Saturday shift (optional)
            $table->integer('sat_out')->nullable()->after('sat_in');
        });

        // team-lead adjustment on a day's punch — overrides the auto-settled paid
        // time (approves overtime, restores an early-leave). Raw punch is untouched.
        Schema::table('punches', function (Blueprint $table) {
            $table->integer('adj_in_min')->nullable()->after('out_geo_ok');
            $table->integer('adj_out_min')->nullable()->after('adj_in_min');
            $table->string('adj_reason')->nullable()->after('adj_out_min');
            $table->unsignedBigInteger('adj_by')->nullable()->after('adj_reason'); // lead's employee id
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['shift_in', 'shift_out', 'sat_in', 'sat_out']);
        });
        Schema::table('punches', function (Blueprint $table) {
            $table->dropColumn(['adj_in_min', 'adj_out_min', 'adj_reason', 'adj_by']);
        });
    }
};
