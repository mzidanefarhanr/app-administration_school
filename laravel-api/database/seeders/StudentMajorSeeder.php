<?php

namespace Database\Seeders;

use App\Models\StudentMajor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentMajorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        StudentMajor::create([
            'name' => 'Ilmu Pengetahuan Alam (IPA)'
        ]);
        StudentMajor::create([
            'name' => 'Ilmu Pengetahuan Sosial (IPS)'
        ]);
        StudentMajor::create([
            'name' => 'Bahasa'
        ]);
        StudentMajor::create([
            'name' => 'Kurikulum Merdeka'
        ]);
    }
}
