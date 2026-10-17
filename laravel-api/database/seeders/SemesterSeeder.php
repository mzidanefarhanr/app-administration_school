<?php

namespace Database\Seeders;

use App\Models\Semester;
use Illuminate\Database\Seeder;

class SemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Semester::firstOrCreate([
            'name' => 'Ganjil',
        ], [
            'name_other' => 'I',
        ]);
        Semester::firstOrCreate([
            'name' => 'Genap',
        ], [
            'name_other' => 'II',
        ]);
    }
}
