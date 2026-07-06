<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // salary | hourly | both  — drives whether payroll is calculated
            $table->string('pay_type')->default('hourly')->after('type');
        });

        // Backfill: Koreans and managers/site managers are salaried; keep the rest hourly.
        DB::table('employees')->where('type', 'manager')->update(['pay_type' => 'salary']);
        DB::table('employees')->where('lang', 'ko')->update(['pay_type' => 'salary']);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('pay_type');
        });
    }
};
