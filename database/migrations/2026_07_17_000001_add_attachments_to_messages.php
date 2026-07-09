<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File attachments on internal-comms messages. The file itself lives on the
 * configured storage disk (object storage in production); the row keeps only
 * metadata + the disk path, and downloads are gated by channel membership.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('att_disk')->nullable()->after('body');
            $table->string('att_path')->nullable()->after('att_disk');
            $table->string('att_name')->nullable()->after('att_path');   // original filename
            $table->string('att_mime')->nullable()->after('att_name');
            $table->unsignedInteger('att_size')->nullable()->after('att_mime'); // bytes
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['att_disk', 'att_path', 'att_name', 'att_mime', 'att_size']);
        });
    }
};
