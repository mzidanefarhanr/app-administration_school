<?php

namespace Database\Seeders;

use App\Models\SchoolProfile;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SchoolProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        SchoolProfile::create([
            'education_school_npsn' => '20107400',
            'principal_id' => '6',
            'school_year_id' => '7',
            'status_principal_id' => '1',
            'nds' => 'A. 02034002',
            'nss' => '302016104047',
            'nis' => '30030',
            'nrks' => '19023L0680164241130595',
            'tmt_principal' => '2017-09-06',
            'official_number' => '0214300653',
            'email' => 'smayappendaunggul@gmail.com',
            'website' => 'www.smayappenda.sch.id',
            'school_committee_name' => 'Dra. Lulus Purwandari',
            'school_committee_number' => '081213892665',
        ]);
    }
}
