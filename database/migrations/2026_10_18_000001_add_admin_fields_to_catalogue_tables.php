<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 7: what the admin console needs to manage on top of the public catalogue.
 *
 *  - books, exam sets, packages: SKU, printed stock, low-stock alert level
 *  - papers: printed and PDF can each be switched on or off, printed stock, marking guide file
 *  - `options` (json) keeps the form settings that are saved now and used by the order and delivery work in module 8
 *    (bulk threshold, watermark, automatic email, "include marking guides", "allow swapping a book" ...)
 *
 * Existing rows get SKUs in catalogue order (BK-100, SET-200, PK-300 ...), exactly like the prototype showed.
 * Stock starts at 0 everywhere: the real shelf counts are entered under Inventory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('sku', 30)->nullable()->unique();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedSmallInteger('low_stock_at')->default(20);
            $table->string('meta_description', 300)->nullable();
        });

        Schema::table('papers', function (Blueprint $table) {
            $table->boolean('print_on')->default(true);
            $table->boolean('pdf_on')->default(true);
            $table->unsignedInteger('print_stock')->default(0);
            $table->unsignedSmallInteger('low_stock_at')->default(25);
            $table->string('guide_path')->nullable();
            $table->json('options')->nullable();
        });

        Schema::table('exam_sets', function (Blueprint $table) {
            $table->string('sku', 30)->nullable()->unique();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedSmallInteger('low_stock_at')->default(10);
            $table->json('options')->nullable();
        });

        Schema::table('holiday_packages', function (Blueprint $table) {
            $table->string('sku', 30)->nullable()->unique();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedSmallInteger('low_stock_at')->default(10);
            $table->json('options')->nullable();
        });

        foreach (['books' => ['BK-', 100], 'exam_sets' => ['SET-', 200], 'holiday_packages' => ['PK-', 300]] as $table => [$prefix, $start]) {
            DB::table($table)->orderBy('id')->pluck('id')->each(function ($id, $i) use ($table, $prefix, $start) {
                DB::table($table)->where('id', $id)->update(['sku' => $prefix . ($start + $i)]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('holiday_packages', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn(['sku', 'stock', 'low_stock_at', 'options']);
        });
        Schema::table('exam_sets', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn(['sku', 'stock', 'low_stock_at', 'options']);
        });
        Schema::table('papers', function (Blueprint $table) {
            $table->dropColumn(['print_on', 'pdf_on', 'print_stock', 'low_stock_at', 'guide_path', 'options']);
        });
        Schema::table('books', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn(['sku', 'stock', 'low_stock_at', 'meta_description']);
        });
    }
};
