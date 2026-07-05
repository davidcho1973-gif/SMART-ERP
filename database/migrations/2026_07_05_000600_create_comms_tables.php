<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A conversation surface: org announcement, a company room, a crew room, or a 1:1 DM.
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('type');                          // announcement | company | team | dm
            $table->string('name')->nullable();              // display name (null for DMs — derived from members)
            $table->string('company_id')->nullable();        // company room binding
            $table->string('team_id')->nullable();           // crew room binding
            $table->unsignedBigInteger('created_by')->nullable(); // employee id that opened the room
            $table->timestamps();
            $table->index(['type', 'company_id', 'team_id']);
        });

        // DM participants + per-employee read cursor (created lazily for shared rooms on first open).
        Schema::create('channel_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            $table->unique(['channel_id', 'employee_id']);
            $table->index('employee_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sender_id');         // employee id
            $table->text('body');
            $table->timestamps();
            $table->index(['channel_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('channel_members');
        Schema::dropIfExists('channels');
    }
};
