<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Compatibility wrapper. Seeds synthetic students in non-production environments.
     */
    public function run(): void
    {
        $this->call(SyntheticDataSeeder::class);
    }
}
