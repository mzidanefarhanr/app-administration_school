<?php

namespace Database\Seeders;

use App\Models\Employment;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmploymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        Employment::create([
            'name' => 'Kepala Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Kepala Bendahara Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Bendahara Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Tenaga Administrasi Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Operator Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Laboran Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Kepala Perpustakaan Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Pustakawan/Pustakawati Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Programmer Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Dokter Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Perawat Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Satpam Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Supir Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Penjaga Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Kebersihan Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Pramubakti Sekolah',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Guru Mata Pelajaran',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Guru Bimbingan Konseling',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Wakil Kepala Kurikulum',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Wakil Kepala Humas dan Prasarana',
            'description' => 'None'
        ]);
        Employment::create([
            'name' => 'Wakil Kepala Kesiswaan',
            'description' => 'None'
        ]);
    }
}
