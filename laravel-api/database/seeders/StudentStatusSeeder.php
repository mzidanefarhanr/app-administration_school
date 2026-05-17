<?php

namespace Database\Seeders;

use App\Models\StudentStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        StudentStatus::create([
            'name' => 'Aktif'
        ]);
        StudentStatus::create([
            'name' => 'Mutasi Keluar'
        ]);
        StudentStatus::create([
            'name' => 'Drop Out'
        ]);
        StudentStatus::create([
            'name' => 'Mengundurkan Diri'
        ]);
        StudentStatus::create([
            'name' => 'Meninggal Dunia'
        ]);
        StudentStatus::create([
            'name' => 'Lulus'
        ]);
    }
}
