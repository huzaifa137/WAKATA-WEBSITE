<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // What was bought. No foreign key on purpose: deleting a product later must never break old orders.
            $table->string('item_type', 10);                              // book | paper | set | pack
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_slug', 120);
            $table->string('format', 10)->nullable();                     // papers only: print | pdf

            // Snapshot at the time of purchase (prices and titles can change afterwards)
            $table->string('title');
            $table->string('level', 10)->nullable();                      // ple | uce | uace
            $table->string('subject', 80)->nullable();
            $table->unsignedSmallInteger('year')->nullable();             // papers
            $table->string('paper_no', 20)->nullable();                   // papers, e.g. "1" from "Paper 1"
            $table->unsignedInteger('unit_price');                        // UGX
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('line_total');                        // UGX
            $table->boolean('is_physical')->default(true);                // false = PDF, delivered by email/download
            $table->timestamps();

            $table->index(['item_type', 'item_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
