<?php

namespace Database\Seeders;

use App\Models\Gender;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEntry;
use App\Models\StudentMajor;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use Illuminate\Database\Seeder;
use RuntimeException;

class SyntheticDataSeeder extends Seeder
{
    /**
     * Seed synthetic student fixtures for testing/development environments.
     * Strictly prohibited on production or unapproved databases.
     */
    public function run(): void
    {
        $this->guardEnvironment();

        // 1. Ensure required reference data exists
        $year = SchoolYear::firstOrCreate(['name' => '2026/2027']);
        $level = SchoolLevel::firstOrCreate(['name' => 'X']);
        $genderMale = Gender::firstOrCreate(['name' => 'Laki-Laki']);
        $genderFemale = Gender::firstOrCreate(['name' => 'Perempuan']);
        $statusActive = StudentStatus::firstOrCreate(['name' => 'Aktif']);
        $entryNew = StudentEntry::firstOrCreate(['name' => 'Siswa Baru']);
        $major = StudentMajor::firstOrCreate(['name' => 'Umum']);

        $rombel = SchoolRombel::firstOrCreate(
            ['name' => 'X-1', 'school_year_id' => $year->id],
            ['school_level_id' => $level->id, 'student_major_id' => $major->id, 'class_teach_id' => null]
        );

        for ($i = 1; $i <= 100; $i++) {
            $nik = '04'.str_pad((string) $i, 14, '0', STR_PAD_LEFT);
            $gender = ($i % 2 === 0) ? $genderFemale : $genderMale;

            $student = Student::firstOrCreate(
                ['nik' => $nik],
                [
                    'name' => 'Synthetic Student '.$i,
                    'birthplace' => 'Synthetic City',
                    'birthdate' => '2010-01-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT),
                    'gender_id' => $gender->id,
                    'nis' => str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                    'nisn' => str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                ]
            );

            StudentRombel::firstOrCreate(
                ['student_nik' => $student->nik, 'school_rombel' => $rombel->id],
                [
                    'student_id' => $student->id,
                    'student_status_id' => $statusActive->id,
                    'student_entry_id' => $entryNew->id,
                ]
            );
        }
    }

    /**
     * Fail-safe: prevent synthetic seeding on production or operational database.
     */
    public function guardEnvironment(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Synthetic data seeding is strictly prohibited in production environment.');
        }

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $isMemory = ($connection === 'sqlite' && $database === ':memory:');
        $isAllowedUat = in_array(app()->environment(), ['testing', 'uat', 'local']) && (
            str_contains(strtolower((string) $database), 'uat') ||
            str_contains(strtolower((string) $database), 'test') ||
            $isMemory
        );

        if (! $isMemory && ! $isAllowedUat) {
            throw new RuntimeException(sprintf(
                'Synthetic data seeding refused for connection "%s" and database "%s". Only isolated test/UAT databases are permitted.',
                (string) $connection,
                (string) $database
            ));
        }
    }
}
