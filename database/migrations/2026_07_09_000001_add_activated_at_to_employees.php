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
            // null = invited / awaiting first login; set on first authenticated login
            $table->timestamp('activated_at')->nullable()->after('emp');
        });

        // existing employees are already onboarded — don't show them as "invited"
        DB::table('employees')->whereNull('activated_at')->update(['activated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('activated_at');
        });
    }
};
