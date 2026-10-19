<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 10: the activity log. One row per change a team member makes in the console (and every sign-in).
 * The person's name is copied into the row, so the log still reads properly after a team member is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('admin_name', 120);
            $table->string('area', 20)->index();            // book, order, auth ... (see App\Support\Activity::AREAS)
            $table->string('icon', 20)->default('edit');    // a name from config/icons.php
            $table->string('verb', 120);                    // "Updated book"
            $table->string('object', 255)->nullable();      // "UCE Biology Workbook"
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activities');
    }
};
