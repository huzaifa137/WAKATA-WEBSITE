<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sets', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();                 // set-uce-2026
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('year_label', 40);                      // 2026 or "2022 to 2026"
            $table->unsignedInteger('price');                      // UGX
            $table->unsignedInteger('worth_price');                // UGX value if bought separately
            $table->unsignedSmallInteger('papers_count');
            $table->json('contents');                              // lines shown under "N papers included"
            $table->json('previews');                              // preview image file names in public/assets/img/papers
            $table->text('blurb');
            $table->string('status', 20)->default('published');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        Schema::create('holiday_packages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();                 // hp-p7
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('audience');                            // "Primary 7 candidates"
            $table->unsignedInteger('price');                      // UGX
            $table->boolean('is_best')->default(false);            // "Most chosen" ribbon
            $table->json('includes');                              // bullet list
            $table->string('status', 20)->default('published');
            $table->unsignedSmallInteger('featured_order')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_packages');
        Schema::dropIfExists('exam_sets');
    }
};
