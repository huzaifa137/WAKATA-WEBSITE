<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 10);                       // book | paper | set | pack
            $table->string('item_slug', 120);                      // catalogue slug, same ids the shop uses
            $table->string('format', 10)->default('');             // papers only: print | pdf ('' for everything else)
            $table->timestamps();

            // '' instead of NULL for the format so the database can keep one row per product and format.
            $table->unique(['customer_id', 'item_type', 'item_slug', 'format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_items');
    }
};
