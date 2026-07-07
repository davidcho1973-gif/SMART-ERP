<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punches', function (Blueprint $table) {
            // where the person belonged AT CLOCK TIME — team moves later must not
            // rewrite history (timesheets, exports, per-day crew attribution)
            $table->string('team_id')->nullable()->after('source');
            $table->string('company_id')->nullable()->after('team_id');
            $table->string('site_id')->nullable()->after('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('punches', function (Blueprint $table) {
            $table->dropColumn(['team_id', 'company_id', 'site_id']);
        });
    }
};
