<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();                 // public id used in links and the cart, e.g. bio-uce-practical
            $table->string('title');
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('price');                      // UGX
            $table->unsignedInteger('old_price')->nullable();      // UGX, shown struck through
            $table->string('badge', 60)->nullable();
            $table->string('cover_image')->nullable();             // path under public/assets/img, e.g. books/biology-uce.jpg
            $table->string('back_image')->nullable();              // front and back cover photo
            $table->unsignedSmallInteger('pages')->nullable();
            $table->string('edition', 60)->nullable();
            $table->string('format', 60);                          // Practical workbook, Textbook ...
            $table->text('description');
            $table->string('status', 20)->default('published');    // published | draft
            $table->unsignedSmallInteger('featured_order')->nullable(); // set = shown on the home page, in this order
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
