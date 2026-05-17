<?php

namespace Database\Seeders;

use App\Models\StudentEntry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        StudentEntry::create([
            'name' => 'Siswa Baru'
        ]);
        StudentEntry::create([
            'name' => 'Mutasi Masuk'
        ]);
    }
}
