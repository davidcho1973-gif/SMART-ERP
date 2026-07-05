<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee ↔ client-company involvement (many-to-many).
 * An employee (of NAHSHON) can be involved with several companies at once —
 * each row is one involvement: which company, which crew, and the relation
 * ("파견"/dispatch by default, or a free-text "기타"/other).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('company_id');
            $table->string('team_id')->nullable();
            $table->string('relation')->default('파견');
            $table->timestamps();

            $table->index(['employee_id']);
            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
