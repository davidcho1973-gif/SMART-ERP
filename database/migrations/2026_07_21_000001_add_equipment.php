<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting M3 · STEP 2 — equipment registry. Owned (구매) assets carry a
 * purchase cost + depreciation; rented (랜트) units carry a rental rate + return
 * date. Photos (nameplate · main · meter · condition) live in equipment_photos;
 * every check-out/in/return/maintenance is logged in equipment_events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();            // 굴착기·용접기·리프트…
            $table->string('acquisition')->default('rented'); // owned | rented
            $table->string('serial')->nullable();
            $table->string('asset_tag')->nullable();
            $table->string('qr_token')->unique();
            $table->string('status')->default('available')->index(); // available·out·maintenance·returned·disposed
            $table->string('site_id')->nullable()->index(); // current site
            $table->unsignedBigInteger('holder_id')->nullable(); // current holder (employee)
            $table->decimal('meter', 12, 1)->nullable();    // hours / km
            $table->string('meter_unit')->default('hours');
            $table->string('condition')->nullable();
            // owned
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 14, 2)->nullable();
            $table->unsignedInteger('useful_life_months')->nullable();
            $table->decimal('salvage_value', 14, 2)->nullable();
            // rented
            $table->string('vendor')->nullable();
            $table->decimal('rental_rate', 12, 2)->nullable();
            $table->string('rate_unit')->default('day');    // day | week | month
            $table->date('rental_start')->nullable();
            $table->date('rental_end')->nullable();         // expected return
            $table->decimal('deposit', 12, 2)->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('kind')->default('main');        // main·plate·meter·side·condition
            $table->string('att_disk')->nullable();
            $table->string('att_path')->nullable();
            $table->string('att_name')->nullable();
            $table->string('att_mime')->nullable();
            $table->unsignedInteger('att_size')->nullable();
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('type');                          // checkout·checkin·maintenance·return·note
            $table->string('site_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->timestamp('at')->nullable();
            $table->decimal('meter', 12, 1)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_events');
        Schema::dropIfExists('equipment_photos');
        Schema::dropIfExists('equipment');
    }
};
