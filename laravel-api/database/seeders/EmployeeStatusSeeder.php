<?php

namespace Database\Seeders;

use App\Models\EmployeeStatus;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        EmployeeStatus::create([
            'name' => 'Pegawai Tidak Tetap',
            'abbreviation' => 'PTT'
        ]);
        EmployeeStatus::create([
            'name' => 'Pegawai Tetap Yayasan',
            'abbreviation' => 'PTY'
        ]);
        EmployeeStatus::create([
            'name' => 'Guru Tidak Tetap',
            'abbreviation' => 'GTT'
        ]);
        EmployeeStatus::create([
            'name' => 'Guru Tetap Yayasan',
            'abbreviation' => 'GTY'
        ]);
        EmployeeStatus::create([
            'name' => 'Pegawai Negeri Sipil',
            'abbreviation' => 'PNS'
        ]);
    }
}
