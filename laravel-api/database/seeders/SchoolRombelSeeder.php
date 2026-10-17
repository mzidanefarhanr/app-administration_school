<?php

namespace Database\Seeders;

use App\Models\SchoolRombel;
use Illuminate\Database\Seeder;

class SchoolRombelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rombels = [
            ['school_level_id' => '10', 'school_year_id' => '6', 'name' => 'X - 1', 'student_major_id' => '4'],
            ['school_level_id' => '10', 'school_year_id' => '6', 'name' => 'X - 2', 'student_major_id' => '4'],
            ['school_level_id' => '10', 'school_year_id' => '6', 'name' => 'X - 3', 'student_major_id' => '4'],
            ['school_level_id' => '10', 'school_year_id' => '6', 'name' => 'X - 4', 'student_major_id' => '4'],
            ['school_level_id' => '10', 'school_year_id' => '6', 'name' => 'X - 5', 'student_major_id' => '4'],
            ['school_level_id' => '10', 'school_year_id' => '6', 'name' => 'X - 6', 'student_major_id' => '4'],
            ['school_level_id' => '10', 'school_year_id' => '6', 'name' => 'X - 7', 'student_major_id' => '4'],
            ['school_level_id' => '11', 'school_year_id' => '6', 'name' => 'XI SOSHUM 1', 'student_major_id' => '4'],
            ['school_level_id' => '11', 'school_year_id' => '6', 'name' => 'XI SOSHUM 2', 'student_major_id' => '4'],
            ['school_level_id' => '11', 'school_year_id' => '6', 'name' => 'XI MIPA 1', 'student_major_id' => '4'],
            ['school_level_id' => '11', 'school_year_id' => '6', 'name' => 'XI MIPA 2', 'student_major_id' => '4'],
            ['school_level_id' => '11', 'school_year_id' => '6', 'name' => 'XI TERAPAN 1', 'student_major_id' => '4'],
            ['school_level_id' => '11', 'school_year_id' => '6', 'name' => 'XI TERAPAN 2', 'student_major_id' => '4'],
            ['school_level_id' => '11', 'school_year_id' => '6', 'name' => 'XI FORMAL 1', 'student_major_id' => '4'],
            ['school_level_id' => '11', 'school_year_id' => '6', 'name' => 'XI FORMAL 2', 'student_major_id' => '4'],
            ['school_level_id' => '12', 'school_year_id' => '6', 'name' => 'XII IPA 1', 'student_major_id' => '1'],
            ['school_level_id' => '12', 'school_year_id' => '6', 'name' => 'XII IPA 2', 'student_major_id' => '1'],
            ['school_level_id' => '12', 'school_year_id' => '6', 'name' => 'XII IPA 3', 'student_major_id' => '1'],
            ['school_level_id' => '12', 'school_year_id' => '6', 'name' => 'XII IPA 4', 'student_major_id' => '1'],
            ['school_level_id' => '12', 'school_year_id' => '6', 'name' => 'XII IPA 5', 'student_major_id' => '1'],
            ['school_level_id' => '12', 'school_year_id' => '6', 'name' => 'XII IPA 6', 'student_major_id' => '1'],
            ['school_level_id' => '12', 'school_year_id' => '6', 'name' => 'XII IPS 1', 'student_major_id' => '2'],
            ['school_level_id' => '12', 'school_year_id' => '6', 'name' => 'XII IPS 2', 'student_major_id' => '2'],
            ['school_level_id' => '12', 'school_year_id' => '6', 'name' => 'XII IPS 3', 'student_major_id' => '2'],
            ['school_level_id' => '12', 'school_year_id' => '6', 'name' => 'XII IPS 4', 'student_major_id' => '2'],
            ['school_level_id' => '12', 'school_year_id' => '6', 'name' => 'XII IPS 5', 'student_major_id' => '2'],
        ];

        foreach ($rombels as $item) {
            SchoolRombel::firstOrCreate(
                ['school_year_id' => $item['school_year_id'], 'name' => $item['name']],
                [
                    'school_level_id' => $item['school_level_id'],
                    'student_major_id' => $item['student_major_id'],
                    'class_teach_id' => null,
                ]
            );
        }
    }
}
