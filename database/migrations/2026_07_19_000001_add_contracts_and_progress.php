<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting module M4 — contract value per site + monthly progress snapshots.
 * Progress billing (기성) = contract × completion%.  A snapshot stores the
 * CUMULATIVE % complete at the end of a month, so this month's billing is
 * contract × (thisMonth% − lastMonth%) and cumulative billing is contract × %.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();          // one contract per site
            $table->decimal('amount', 14, 2)->default(0); // 계약금액 (USD)
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('progress_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->string('ym', 7);                      // YYYY-MM
            $table->decimal('pct', 5, 2)->default(0);     // cumulative % complete (0–100)
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'ym']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_snapshots');
        Schema::dropIfExists('contracts');
    }
};
