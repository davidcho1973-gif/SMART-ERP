<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->after('id');
            $table->string('access')->default('worker')->after('email'); // admin | manager | worker
            $table->string('google_id')->nullable()->after('access');
        });

        // one row per employee per work day; times stored as minutes since midnight (MST)
        Schema::create('punches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('work_date', 10);              // YYYY-MM-DD
            $table->integer('in_min')->nullable();
            $table->integer('out_min')->nullable();
            $table->boolean('no_lunch')->default(false);
            $table->string('early_reason')->nullable();
            $table->string('source')->default('manual');  // worker | manual | qr
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('period_start', 10);
            $table->string('period_end', 10);
            $table->string('check_no');
            $table->string('pay_date');
            $table->decimal('amount', 10, 2);
            $table->integer('reg_hours')->default(0);
            $table->integer('ot_hours')->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('punches');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_id', 'access', 'google_id']);
        });
    }
};
