<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Set WAKATA_SEED_PASSWORD in .env. Local installs fall back to a throwaway password; production must set one.
        $password = config('wakata.seed_password') ?: (app()->isProduction() ? null : 'Wakata@2026');

        if (! $password) {
            $this->command?->warn('AdminSeeder skipped: set WAKATA_SEED_PASSWORD in .env first.');

            return;
        }

        // firstOrCreate so re-seeding never resets a password someone has already changed.
        Admin::firstOrCreate(
            ['email' => 'grace@wakatastationers.com'],
            ['name' => 'Grace Namutebi', 'role' => 'super_admin', 'status' => 'active', 'password' => $password]
        );
    }
}
