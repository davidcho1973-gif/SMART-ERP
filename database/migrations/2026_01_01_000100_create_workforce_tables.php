<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->string('id')->primary();     // e.g. s1
            $table->string('name');
            $table->string('city')->default('');
            $table->string('gc')->default('');   // general contractor
            $table->string('code')->default('');
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->string('id')->primary();     // e.g. c1
            $table->string('name');
            $table->string('site_id');
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->string('id')->primary();     // e.g. t1
            $table->string('name');
            $table->string('company_id');
            $table->unsignedBigInteger('lead')->nullable();  // employee id of crew lead
            $table->string('color')->default('#3B72E0');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id');            // human/badge id, e.g. HOF-AZ-100311 or N-xxxxxxxxx
            $table->string('first');
            $table->string('last');
            $table->string('ko')->nullable();    // Korean display name
            $table->string('nat')->default('');
            $table->string('code')->default(''); // nationality code
            $table->string('team_id')->nullable();
            $table->string('company_id')->nullable();
            $table->string('site_id')->nullable();
            $table->string('role')->default('');
            $table->string('type')->default('worker');   // worker | manager
            $table->string('lang')->default('es');       // en | es | ko
            $table->string('access')->default('worker'); // admin | manager | worker
            $table->decimal('rate', 8, 2)->default(0);   // USD/hr
            $table->string('issued')->default('');       // MM/DD/YYYY
            $table->string('phone')->default('');
            $table->string('email')->default('');
            $table->string('status')->default('off');    // present | late | absent | off
            $table->string('in_t')->default('—');
            $table->string('out_t')->default('—');
            $table->integer('wh')->default(0);           // hours worked in current bi-weekly period
            $table->string('emp')->default('active');    // active | terminated
            $table->string('term')->nullable();          // termination date
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('sites');
    }
};
