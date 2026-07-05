<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Worker-submitted attendance-correction requests with a team-lead / HR approval flow.
 * The row doubles as the audit record: it snapshots what the worker was assigned to on
 * the work date (company/crew/lead), the punch's original times, the requested times,
 * and who decided the request and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();  // the worker whose punch
            $table->string('work_date', 10);                     // YYYY-MM-DD being corrected
            $table->string('type')->default('set');              // set | delete

            // requested corrected values (null out = clocked in only; ignored for delete)
            $table->integer('req_in_min')->nullable();
            $table->integer('req_out_min')->nullable();

            // snapshot of the punch at request time (audit / drift detection)
            $table->integer('orig_in_min')->nullable();
            $table->integer('orig_out_min')->nullable();

            $table->text('reason');

            // who the worker was assigned to on the work date (frozen so history stays correct)
            $table->string('company_id')->nullable();
            $table->string('team_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();   // responsible team lead (employee id)

            $table->string('status')->default('pending');        // pending | approved | rejected
            $table->unsignedBigInteger('decided_by')->nullable(); // approver employee id
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();          // rejection reason / note

            $table->unsignedBigInteger('channel_id')->nullable(); // comms room the request notified
            $table->timestamps();

            $table->index(['status', 'lead_id']);
            $table->index(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
