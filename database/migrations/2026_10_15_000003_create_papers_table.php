<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();                 // pp-uce-mathematics-paper-1-2026
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('paper', 30);                           // Paper 1
            $table->unsignedSmallInteger('year');
            $table->string('title');                               // Mathematics Paper 1 (PLE papers: "PLE Mathematics")
            $table->string('duration', 40);                        // 2 hours 30 minutes
            $table->unsignedInteger('print_price');                // UGX, printed copy
            $table->unsignedInteger('pdf_price');                  // UGX, PDF download
            $table->string('cover_image')->nullable();             // first-page photo, path under public/assets/img
            $table->string('status', 20)->default('published');
            $table->unsignedSmallInteger('featured_order')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['level_id', 'subject_id', 'paper', 'year']);
            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papers');
    }
};
