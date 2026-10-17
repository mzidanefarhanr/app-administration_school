<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with required reference data.
     * Default seeder only seeds reference data without any default accounts or passwords.
     * Use 'php artisan app:bootstrap-admin' to create the initial system administrator.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);
    }
}
