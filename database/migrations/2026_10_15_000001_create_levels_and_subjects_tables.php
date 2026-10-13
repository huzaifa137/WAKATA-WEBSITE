<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exam levels: PLE, UCE, UACE. `book_code` / `book_label` are how the books pages name the same level.
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();          // ple | uce | uace
            $table->string('label', 60);                   // Primary (PLE)
            $table->string('short', 12);                   // PLE
            $table->string('book_code', 12)->unique();     // primary | o | a
            $table->string('book_label', 30);              // Primary | O-Level | A-Level
            $table->string('full_name', 120);              // Primary Leaving Examination
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Subjects, with the three colours the generated book covers use.
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('color_1', 9)->nullable();
            $table->string('color_2', 9)->nullable();
            $table->string('color_3', 9)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('levels');
    }
};
