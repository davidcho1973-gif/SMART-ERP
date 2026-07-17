<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a worker clocks in but forgets to clock out, the end-of-day close fills
 * the missing clock-out with the scheduled shift end and marks it out_auto = true
 * — so pay is not lost, the day reads "clocked out (auto)", and a lead can still
 * correct it to the real time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punches', function (Blueprint $table) {
            $table->boolean('out_auto')->default(false)->after('out_min');
        });
    }

    public function down(): void
    {
        Schema::table('punches', function (Blueprint $table) {
            $table->dropColumn('out_auto');
        });
    }
};
