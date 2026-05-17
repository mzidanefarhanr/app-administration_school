<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EducationLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        EducationLevel::create([
            'name'     => 'Tidak/Belum Sekolah'
        ]);
        EducationLevel::create([
            'name'     => 'Belum Tamat SD/Sederajat'
        ]);
        EducationLevel::create([
            'name'     => 'SD/Sederajat'
        ]);
        EducationLevel::create([
            'name'     => 'Belum Tamat SMP/SLTP/Sederajat'
        ]);
        EducationLevel::create([
            'name'     => 'SMP/SLTP/Sederajat'
        ]);
        EducationLevel::create([
            'name'     => 'Belum Tamat SMA/SLTA/Sederajat'
        ]);
        EducationLevel::create([
            'name'     => 'SMA/SLTA/Sederajat'
        ]);
        EducationLevel::create([
            'name'     => 'Diploma I/II'
        ]);
        EducationLevel::create([
            'name'     => 'Diploma III/Akademi/S.Muda'
        ]);
        EducationLevel::create([
            'name'     => 'Diploma IV/Strata I'
        ]);
        EducationLevel::create([
            'name'     => 'Strata II'
        ]);
        EducationLevel::create([
            'name'     => 'Strata III'
        ]);
    }
}
