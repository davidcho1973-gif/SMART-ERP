<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 of the access redesign: every permission-sensitive act leaves a row —
 * role grants, terminations, deletions, manual punches, correction decisions.
 * Payroll disputes and compliance reviews read this stream.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();  // employee id (null: system/demo persona)
            $table->string('actor_name');                        // frozen display name
            $table->string('action');                            // e.g. role.grant · employee.terminate
            $table->string('target')->nullable();                // e.g. "Carlos Martínez (#106)"
            $table->string('detail')->nullable();                // e.g. "worker → hr_admin"
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
