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
            'name' => 'I'
        ]);
        SchoolLevel::create([
            'name' => 'II'
        ]);
        SchoolLevel::create([
            'name' => 'III'
        ]);
        SchoolLevel::create([
            'name' => 'IV'
        ]);
        SchoolLevel::create([
            'name' => 'V'
        ]);
        SchoolLevel::create([
            'name' => 'VI'
        ]);
        SchoolLevel::create([
            'name' => 'VII'
        ]);
        SchoolLevel::create([
            'name' => 'VIII'
        ]);
        SchoolLevel::create([
            'name' => 'IX'
        ]);
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
