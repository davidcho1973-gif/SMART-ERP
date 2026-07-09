<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Out-of-state dispatch: a NAHSHON employee temporarily working in another state.
 * dispatch_to (free text, e.g. "Texas · Samsung Taylor") being non-empty means
 * "currently dispatched"; the dates and note are optional context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('dispatch_to')->nullable()->after('resign_reason');
            $table->string('dispatch_from', 10)->nullable()->after('dispatch_to');
            $table->string('dispatch_until', 10)->nullable()->after('dispatch_from');
            $table->string('dispatch_note')->nullable()->after('dispatch_until');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['dispatch_to', 'dispatch_from', 'dispatch_until', 'dispatch_note']);
        });
    }
};
