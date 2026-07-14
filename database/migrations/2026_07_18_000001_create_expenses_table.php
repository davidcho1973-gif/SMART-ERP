<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site expenses / receipts (accounting module M2). Each row is one receipt:
 * what was bought, how much, which site & category it belongs to, plus the
 * receipt image on object storage. Approved expenses feed the site's cost
 * (the "경비" pillar) on the accounting dashboard and, later, progress billing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->nullable()->index();   // sites use string ids
            $table->string('category')->default('other');     // fuel·meal·transport·tool·supply·rental·other
            $table->string('vendor')->nullable();
            $table->decimal('amount', 12, 2)->default(0);      // USD
            $table->date('spent_on');
            $table->text('note')->nullable();
            $table->string('status')->default('pending')->index(); // pending·approved·rejected
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('reject_reason')->nullable();
            // receipt image on object storage (same shape as message attachments)
            $table->string('att_disk')->nullable();
            $table->string('att_path')->nullable();
            $table->string('att_name')->nullable();
            $table->string('att_mime')->nullable();
            $table->unsignedInteger('att_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
