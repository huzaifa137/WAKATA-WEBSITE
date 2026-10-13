<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Demo customer (orders, addresses and a support thread come from DemoPortalSeeder). Never created in production.
        if (app()->isProduction()) {
            return;
        }

        Customer::firstOrCreate(
            ['email' => 'joseph.ssemakula@greenhill.ac.ug'],
            [
                'name'         => 'Joseph Ssemakula',
                'phone'        => '+256 772 123 456',
                'type'         => 'Teacher',
                'school'       => 'Green Hill Secondary School',
                'password'     => config('wakata.seed_password') ?: 'Wakata@2026',
            ]
        );
    }
}
