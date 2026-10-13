<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paths on the private disk (storage/app/private). The admin catalogue module (7) adds the upload screens;
        // until then the files can be copied there by hand.
        Schema::table('papers', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('cover_image');
        });
        Schema::table('exam_sets', function (Blueprint $table) {
            $table->string('guide_path')->nullable()->after('blurb');
        });
        Schema::table('holiday_packages', function (Blueprint $table) {
            $table->string('timetable_path')->nullable()->after('includes');
        });
    }

    public function down(): void
    {
        Schema::table('holiday_packages', fn (Blueprint $t) => $t->dropColumn('timetable_path'));
        Schema::table('exam_sets', fn (Blueprint $t) => $t->dropColumn('guide_path'));
        Schema::table('papers', fn (Blueprint $t) => $t->dropColumn('pdf_path'));
    }
};
