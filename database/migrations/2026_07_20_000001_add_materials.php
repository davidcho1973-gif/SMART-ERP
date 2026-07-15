<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting module M3 — materials inbound. A "batch" is one inbound document
 * (a delivery slip, a manual no-slip entry, or an opening stock-take) with
 * several line items. Approved delivery/manual batches feed the site's material
 * cost (자재비 pillar); OPENING batches record quantity only (already paid for
 * in a prior period), so they never touch this month's cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_batches', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->string('vendor')->nullable();
            $table->date('spent_on');
            // delivery = has a slip (OCR) · manual = bought, no slip (estimated) · opening = already on site
            $table->string('kind')->default('delivery');
            $table->string('status')->default('pending')->index(); // pending·approved·rejected
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->string('note')->nullable();
            // the slip image (delivery) or a photo of the pile (opening) — object storage
            $table->string('att_disk')->nullable();
            $table->string('att_path')->nullable();
            $table->string('att_name')->nullable();
            $table->string('att_mime')->nullable();
            $table->unsignedInteger('att_size')->nullable();
            $table->timestamps();
        });

        Schema::create('material_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('material_batches')->cascadeOnDelete();
            $table->string('name');                       // item name / spec
            $table->string('unit')->default('ea');        // ea·m·box·kg·roll…
            $table->decimal('qty', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0); // qty × unit_price
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_lines');
        Schema::dropIfExists('material_batches');
    }
};
