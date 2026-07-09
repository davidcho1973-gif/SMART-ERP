<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 휴가: a date-range leave request that a lead/manager approves. While an
        // approved leave covers today, the worker reads as "휴가중" everywhere.
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('start_date', 10);            // YYYY-MM-DD (inclusive)
            $table->string('end_date', 10);              // YYYY-MM-DD (inclusive)
            $table->string('reason')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'start_date']);
        });

        // 결근: an explicit no-show record for one work day. kind distinguishes an
        // excused call-in (사유 있음) from an unexcused no-call-no-show (무단결근).
        // Created by the worker (self-report), the lead (marking 미출근), or the
        // end-of-day close job (attendance:close-day → auto unexcused).
        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('work_date', 10);             // YYYY-MM-DD
            $table->string('kind')->default('unexcused'); // excused | unexcused
            $table->string('reason')->nullable();
            $table->string('source')->default('lead');    // worker | lead | auto
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
            $table->index('work_date');
        });

        // 퇴사 신청: a self-requested last working day, pending admin approval.
        // Approval reuses the existing terminate flow (emp='terminated', term=date).
        Schema::table('employees', function (Blueprint $table) {
            $table->string('resign_on', 10)->nullable()->after('term');   // requested last day
            $table->string('resign_reason')->nullable()->after('resign_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('absences');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['resign_on', 'resign_reason']);
        });
    }
};
