<?php

namespace Database\Seeders;

use App\Models\SchoolLevel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SchoolLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        SchoolLevel::create([
            'name' => 'X'
        ]);
        SchoolLevel::create([
            'name' => 'XI'
        ]);
        SchoolLevel::create([
            'name' => 'XII'
        ]);
    }
}
