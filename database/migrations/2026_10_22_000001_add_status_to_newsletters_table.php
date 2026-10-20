<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Module 11: a big newsletter is sent in the background, so its history line says "sending" until the counts are final. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->string('status', 10)->default('sent')->after('audience');   // sending | sent
            $table->unsignedInteger('total')->default(0)->after('status');      // how many people it was sent to
        });
    }

    public function down(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->dropColumn(['status', 'total']);
        });
    }
};
