<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punches', function (Blueprint $table) {
            // the crew's shift AS OF the day worked — frozen at clock-in so a
            // later shift edit can never rewrite already-earned paid hours
            $table->integer('shift_in_snap')->nullable()->after('adj_by');
            $table->integer('shift_out_snap')->nullable()->after('shift_in_snap');
            // the hottest filter (daily timesheet, dashboards) is by date alone;
            // the existing unique(employee_id, work_date) can't serve it
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::table('punches', function (Blueprint $table) {
            $table->dropIndex(['work_date']);
            $table->dropColumn(['shift_in_snap', 'shift_out_snap']);
        });
    }
};
