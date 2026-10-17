<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Small editable lists and defaults of the catalogue (Subjects and levels page, Past papers page):
 * book types, book badges and the default prices and exam time for new papers of each level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_options', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->json('value');
            $table->timestamps();
        });

        $now = now();
        $rows = [
            'book_types' => ['Practical workbook', 'Textbook', 'Revision book', 'Revision guide', 'Workbook', 'Study guide', 'Practical guide', 'Activity workbook'],
            'book_badges' => ['Bestseller', 'New curriculum', 'Popular', 'PLE', 'Lower primary'],
            'paper_defaults' => [
                'ple'  => ['print' => 3000, 'pdf' => 2000, 'time' => '2 hours 30 minutes'],
                'uce'  => ['print' => 4000, 'pdf' => 2500, 'time' => '2 hours 15 minutes'],
                'uace' => ['print' => 5000, 'pdf' => 3000, 'time' => '2 hours 30 minutes'],
            ],
        ];
        foreach ($rows as $key => $value) {
            DB::table('catalogue_options')->insert(['key' => $key, 'value' => json_encode($value), 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_options');
    }
};
