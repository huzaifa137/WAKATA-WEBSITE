<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            CustomerSeeder::class,
            CatalogueSeeder::class,
            DemoPortalSeeder::class,   // after the catalogue: demo orders use real products
        ]);
    }
}
