<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A company can be temporarily unassigned from a site (e.g. after the site is
    // deleted), mirroring how employees.company_id / site_id already work.
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('site_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('site_id')->nullable(false)->change();
        });
    }
};
