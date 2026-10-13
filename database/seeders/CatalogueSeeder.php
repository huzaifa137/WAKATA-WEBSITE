<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\ExamSet;
use App\Models\HolidayPackage;
use App\Models\Level;
use App\Models\Paper;
use App\Models\Subject;
use App\Support\Catalogue;
use Illuminate\Database\Seeder;

/**
 * Loads the starter catalogue (the prototype's data.js) from database/seeders/data/catalogue.json.
 *
 * Safe to run again: records are matched by slug (or code / name) and only created when missing, so edits made
 * later in the admin console are never overwritten. All prices are the prototype's SAMPLE values: review them.
 */
class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(
            file_get_contents(database_path('seeders/data/catalogue.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($data['levels'] as $row) {
            Level::firstOrCreate(['code' => $row['code']], $row);
        }
        foreach ($data['subjects'] as $row) {
            Subject::firstOrCreate(['name' => $row['name']], $row);
        }

        $levels   = Level::pluck('id', 'code');
        $subjects = Subject::pluck('id', 'name');

        foreach ($data['books'] as $row) {
            $row['level_id']   = $levels[$row['level']];
            $row['subject_id'] = $subjects[$row['subject']];
            unset($row['level'], $row['subject']);
            Book::firstOrCreate(['slug' => $row['slug']], $row);
        }

        foreach ($data['papers'] as $row) {
            $row['level_id']   = $levels[$row['level']];
            $row['subject_id'] = $subjects[$row['subject']];
            unset($row['level'], $row['subject']);
            Paper::firstOrCreate(['slug' => $row['slug']], $row);
        }

        foreach ($data['sets'] as $row) {
            $row['level_id'] = $levels[$row['level']];
            unset($row['level']);
            ExamSet::firstOrCreate(['slug' => $row['slug']], $row);
        }

        foreach ($data['packages'] as $row) {
            $row['level_id'] = $levels[$row['level']];
            unset($row['level']);
            HolidayPackage::firstOrCreate(['slug' => $row['slug']], $row);
        }

        Catalogue::flush();
    }
}
