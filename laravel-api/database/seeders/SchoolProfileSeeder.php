<?php

namespace Database\Seeders;

use App\Models\SchoolProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class SchoolProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $principal = User::first();
        if (! $principal) {
            // School profile requires a principal user referencing the users table.
            // When seeding reference data on a clean database without accounts, skip until an account exists.
            return;
        }

        SchoolProfile::firstOrCreate(
            ['education_school_npsn' => '20107400'],
            [
                'principal_id' => $principal->id,
                'school_year_id' => '1',
                'status_principal_id' => '1',
                'nds' => 'A. 02034002',
                'nss' => '302016104047',
                'nis' => '30030',
                'nrks' => '19023L0680164241130595',
                'tmt_principal' => '2017-09-06',
                'official_number' => '0210000000',
                'email' => 'info@school.test',
                'website' => 'www.school.test',
                'school_committee_name' => 'School Committee',
                'school_committee_number' => '080000000000',
            ]
        );
    }
}
