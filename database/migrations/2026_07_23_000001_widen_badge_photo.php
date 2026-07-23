<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * badge_photo holds a downscaled selfie/badge as a data URI. It was `text`
 * (65 KB on MySQL) — a self-service sign-up selfie could exceed that and 500 the
 * insert. Widen to mediumText (16 MB) so it never overflows; selfies are also
 * downscaled client-side and size-guarded server-side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->mediumText('badge_photo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->text('badge_photo')->nullable()->change();
        });
    }
};
