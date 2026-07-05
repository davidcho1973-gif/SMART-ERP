<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // What the approver actually applied — equal to the requested times on a plain
    // approve, or the approver's edited values when they adjust before approving.
    public function up(): void
    {
        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->integer('appl_in_min')->nullable()->after('req_out_min');
            $table->integer('appl_out_min')->nullable()->after('appl_in_min');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->dropColumn(['appl_in_min', 'appl_out_min']);
        });
    }
};
