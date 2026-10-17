<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use App\Models\EducationSchool;
use App\Models\EmployeeType;
use App\Models\Gender;
use App\Models\Province;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Seed all reference and catalog tables required for application operation.
     * Contains zero PII / personal data. Idempotent and safe to rerun.
     */
    public function run(): void
    {
        // 1. Core access and user status (always idempotent)
        $this->call([
            StatusUserSeeder::class,
            TypeUserSeeder::class,
            AccessControlSeeder::class,
            SchoolYearSeeder::class,
            SemesterSeeder::class,
        ]);

        // 2. Education levels and school levels
        if (EducationLevel::count() === 0) {
            $this->call(EducationLevelSeeder::class);
        }

        if (SchoolLevel::count() === 0) {
            $this->call(SchoolLevelSeeder::class);
        }

        // 3. Regional tables (only seeded if not yet present)
        if (Province::count() === 0) {
            $this->call([
                ProvinceSeeder::class,
                RegencySeeder::class,
                DistrictSeeder::class,
                VillageSeeder::class,
            ]);
        }

        // 4. Demographics and student reference catalogs
        if (Gender::count() === 0) {
            $this->call([
                GenderSeeder::class,
                ReligionSeeder::class,
                BloodTypeSeeder::class,
                ProfessionSeeder::class,
                FamilyStatusSeeder::class,
                StudentStatusSeeder::class,
                StudentEntrySeeder::class,
                StudentMajorSeeder::class,
                PhaseStatusSeeder::class,
            ]);
        }

        if (EmployeeType::count() === 0) {
            $this->call([
                MaritalStatusSeeder::class,
                EmployeeStatusSeeder::class,
                EmployeeTypeSeeder::class,
                EmploymentSeeder::class,
            ]);
        }

        if (Subject::count() === 0) {
            $this->call(SubjectSeeder::class);
        }

        // 5. Dependent reference tables (requires education levels and districts)
        if (EducationSchool::count() === 0) {
            $this->call(EducationSchoolSeeder::class);
        }

        if (SchoolRombel::count() === 0) {
            $this->call(SchoolRombelSeeder::class);
        }

        $this->call(SchoolProfileSeeder::class);
    }
}
