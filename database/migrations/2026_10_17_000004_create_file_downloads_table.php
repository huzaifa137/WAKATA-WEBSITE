<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per downloadable file of an order. The row is created on the first download.
        Schema::create('file_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_key', 40);                        // i12 (the PDF), i12-guide, i12-timetable
            $table->unsignedSmallInteger('used')->default(0);
            $table->unsignedSmallInteger('max_downloads');         // 5 by default; support (or the admin, module 8) can raise it
            $table->timestamp('last_downloaded_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'file_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_downloads');
    }
};
