<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        $this->call([
            StatusUserSeeder::class,
            TypeUserSeeder::class,
        ]);
        User::factory()->create([
            'name' => 'Muhamad Zidane Farhan Ramadhan, S.Kom.',
            'email' => 'mzidanefarhanr@gmail.com',
            'username' => 'mzidanefarhanr',
            'nik' => '3175032408000015',
        ]);
        User::factory()->create([
            'name' => 'Tardiyanto, S.Pd.',
            'email' => 'tardiyanto@gmail.com',
            'username' => 'tardiyanto',
            'nik' => '3172030705670010',
            'type_user_id' => 2
        ]);
        User::factory()->create([
            'name' => 'Sungadi',
            'email' => 'sungadi@gmail.com',
            'username' => 'sungadi',
            'nik' => '3175091208690012',
            'type_user_id' => 2
        ]);
        User::factory()->create([
            'name' => 'Dadang Darsono',
            'email' => 'dadangdarsono@gmail.com',
            'username' => 'dadangdarsono',
            'nik' => '3172020712690004',
            'type_user_id' => 2
        ]);
        User::factory()->create([
            'name' => 'Asroh, S.M.',
            'email' => 'asroh@gmail.com',
            'username' => 'asroh',
            'nik' => '3175092805810014',
            'type_user_id' => 2
        ]);

        $this->call([
            SchoolYearSeeder::class,
            SemesterSeeder::class,
            ProvinceSeeder::class,
            RegencySeeder::class,
            DistrictSeeder::class,
            VillageSeeder::class,
            EducationLevelSeeder::class,
            GenderSeeder::class,
            ReligionSeeder::class,
            BloodTypeSeeder::class,
            ProfessionSeeder::class,
            FamilyStatusSeeder::class,
            EducationSchoolSeeder::class,
            StudentStatusSeeder::class,
            StudentEntrySeeder::class,
            StudentMajorSeeder::class,
            PhaseStatusSeeder::class,
            SchoolLevelSeeder::class,
            SchoolRombelSeeder::class,
            StudentSeeder::class,
            StudentRombelSeeder::class,
        ]);
    }
}
