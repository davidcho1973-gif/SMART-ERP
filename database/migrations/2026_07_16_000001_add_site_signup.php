<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service site sign-up: a site carries a join_token that a public /join/{token}
 * page resolves, so remote workers register themselves. Their chosen password is
 * held on the employee (join_password) until an approver activates the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('join_token')->nullable()->unique()->after('radius_m');
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->string('join_password')->nullable()->after('dispatch_note');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('join_token');
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('join_password');
        });
    }
};
