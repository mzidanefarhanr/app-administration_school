<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\Employment;
use App\Models\Gender;
use App\Models\MaritalStatus;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Religion;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $this->guardEnvironment();

        DB::transaction(function () {
            $teacherType = EmployeeType::firstOrCreate(['name' => 'Pendidik']);
            $staffType = EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
            $teacherStatus = EmployeeStatus::firstOrCreate(['name' => 'Guru Tetap Yayasan'], ['abbreviation' => 'GTY']);
            $staffStatus = EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap Yayasan'], ['abbreviation' => 'PTY']);
            $male = Gender::firstOrCreate(['name' => 'Laki-Laki']);
            $female = Gender::firstOrCreate(['name' => 'Perempuan']);
            $religion = Religion::firstOrCreate(['name' => 'Islam']);
            $maritalStatus = MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);
            $province = Province::firstOrCreate(['name' => 'Synthetic Province']);
            $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Synthetic Regency']);
            $district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Synthetic District']);
            $subjects = collect(['Matematika', 'Bahasa Inggris', 'Ilmu Pengetahuan Alam (IPA)'])
                ->map(fn ($name) => Subject::firstOrCreate(['name' => $name], ['description' => 'Synthetic UAT reference']));
            $teacherJobs = collect(['Guru Mata Pelajaran', 'Guru Bimbingan Konseling'])
                ->map(fn ($name) => Employment::firstOrCreate(['name' => $name], ['description' => 'Synthetic UAT reference']));
            $staffJobs = collect([
                'Tenaga Administrasi Sekolah', 'Operator Sekolah', 'Laboran Sekolah',
                'Kepala Perpustakaan Sekolah', 'Pustakawan/Pustakawati Sekolah', 'Programmer Sekolah',
                'Dokter Sekolah', 'Perawat Sekolah', 'Satpam Sekolah', 'Supir Sekolah',
                'Penjaga Sekolah', 'Kebersihan Sekolah', 'Pramubakti Sekolah',
                'Kepala Bendahara Sekolah', 'Bendahara Sekolah',
            ])->map(fn ($name) => Employment::firstOrCreate(['name' => $name], ['description' => 'Synthetic UAT reference']));

            foreach ([true, false] as $teacher) {
                $type = $teacher ? $teacherType : $staffType;
                $jobs = $teacher ? $teacherJobs : $staffJobs;
                $prefix = $teacher ? 'Synthetic Teacher ' : 'Synthetic Staff ';

                for ($i = 1; $i <= 100; $i++) {
                    $name = $prefix.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                    if (Employee::withTrashed()->where('name', $name)->where('employee_type_id', $type->id)->exists()) {
                        continue;
                    }

                    Employee::create([
                        'name' => $name,
                        'nik' => null,
                        'user_nik' => null,
                        'user_id' => null,
                        'legacy_profile_at' => null,
                        'version' => 1,
                        'employee_type_id' => $type->id,
                        'employee_status_id' => ($teacher ? $teacherStatus : $staffStatus)->id,
                        'employment_id' => $jobs[($i - 1) % $jobs->count()]->id,
                        'subject_id' => $teacher ? $subjects[($i - 1) % $subjects->count()]->id : null,
                        'religion_id' => $religion->id,
                        'district_id' => $district->id,
                        'marital_status_id' => $maritalStatus->id,
                        'gender_id' => ($i % 2 === 0 ? $female : $male)->id,
                        'date_of_birth' => '1985-01-'.str_pad((string) (($i - 1) % 28 + 1), 2, '0', STR_PAD_LEFT),
                        'place_of_birth' => 'Synthetic City',
                        'address' => 'Synthetic UAT Address '.$i,
                        'email' => ($teacher ? 'teacher' : 'staff').$i.'@example.invalid',
                        'tmt_employee' => '2020-01-01',
                    ]);
                }
            }
        });
    }

    public function guardEnvironment(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        $memory = app()->environment('testing') && $connection === 'sqlite' && $database === ':memory:';
        $expected = [
            base_path('../.uat-phase04b/database.sqlite'),
            base_path('../.uat-phase05/database.sqlite'),
        ];
        $uat = false;

        if (app()->environment('uat') && $connection === 'sqlite' && is_file((string) $database)) {
            foreach ($expected as $path) {
                if (is_file($path) && realpath((string) $database) === realpath($path)) {
                    $uat = true;
                    break;
                }
            }
        }

        if ((! $memory && ! $uat) || config("database.connections.{$connection}.url")) {
            throw new RuntimeException('Employee seeding requires SQLite in-memory tests or an isolated SQLite UAT database (.uat-phase04b/.uat-phase05).');
        }
    }
}
