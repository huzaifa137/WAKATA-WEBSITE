<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Every change to printed stock made under Inventory, so the movement chart and the history are real. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            // What changed. No foreign key on purpose: the history outlives the product.
            $table->string('item_type', 10);                     // book | paper | set | pack
            $table->unsignedBigInteger('item_id');
            $table->string('item_slug', 120);
            $table->string('item_name');
            $table->string('mode', 10);                          // add | remove | set
            $table->integer('quantity');                         // as typed in the form
            $table->integer('delta');                            // what the stock actually moved by
            $table->unsignedInteger('stock_after');
            $table->string('reason', 20);                        // print | count | damage | return | other
            $table->string('note')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_name')->nullable();
            $table->timestamps();
            $table->index(['item_type', 'item_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
