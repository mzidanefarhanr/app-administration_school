<?php

namespace Database\Seeders;

use App\Models\Gender;
use App\Models\Role;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\StatusUser;
use App\Models\Student;
use App\Models\StudentEntry;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder UAT SINTETIS (Fase 01a review).
 *
 * Membuat akun uji untuk empat peran + satu akun nonaktif + data referensi
 * minimum. TIDAK memuat identitas pribadi (berbeda dari DatabaseSeeder).
 *
 * Kata sandi TIDAK ditulis di kode/dokumen. Ambil dari lingkungan:
 *   set UAT_SEED_PASSWORD=... sebelum menjalankan `php artisan db:seed --class=UatSeeder`.
 * Bila tidak diset, memakai kata sandi acak yang dicetak SEKALI ke stdout
 * (jangan disimpan ke dokumen terlacak).
 */
class UatSeeder extends Seeder
{
    public function run(): void
    {
        $this->guardEnvironment();

        // 1. Data referensi minimum.
        TypeUser::firstOrCreate(['name' => 'Admin']);
        TypeUser::firstOrCreate(['name' => 'User']);
        StatusUser::firstOrCreate(['name' => 'Aktif']);
        StatusUser::firstOrCreate(['name' => 'Tidak Aktif']);

        // 2. Katalog kemampuan + peran (idempotent).
        $this->call(AccessControlSeeder::class);

        // 3. Kata sandi sintetis (tidak disimpan di dokumen).
        $password = getenv('UAT_SEED_PASSWORD');
        $generated = false;
        if (! $password) {
            $password = 'uat-'.bin2hex(random_bytes(8)); // min 12 karakter
            $generated = true;
        }

        $adminType = TypeUser::where('name', 'Admin')->first();
        $userType = TypeUser::where('name', 'User')->first();
        $aktif = StatusUser::where('name', 'Aktif')->first();
        $nonaktif = StatusUser::where('name', 'Tidak Aktif')->first();

        // Akun sintetis; nama/NIK/email jelas bukan identitas nyata.
        $blueprints = [
            ['role' => 'admin-sistem',    'name' => 'UAT Admin Sistem',        'username' => 'uat.admin',    'nik' => '9000000000000001', 'active' => true],
            ['role' => 'operator-sekolah', 'name' => 'UAT Operator Sekolah',    'username' => 'uat.operator', 'nik' => '9000000000000002', 'active' => true],
            ['role' => 'kepala-sekolah',  'name' => 'UAT Kepala Sekolah',      'username' => 'uat.kepsek',   'nik' => '9000000000000003', 'active' => true],
            ['role' => 'wakil-kesiswaan', 'name' => 'UAT Wakil Kesiswaan',     'username' => 'uat.waka',     'nik' => '9000000000000004', 'active' => true],
            ['role' => 'operator-sekolah', 'name' => 'UAT Akun Nonaktif',       'username' => 'uat.inactive', 'nik' => '9000000000000005', 'active' => false],
        ];

        foreach ($blueprints as $bp) {
            $user = User::withTrashed()->firstOrNew(['username' => $bp['username']]);
            $user->fill([
                'name' => $bp['name'],
                'email' => $bp['username'].'@uat.local',
                'email_verified_at' => now(),
                'password' => bcrypt($password),
                'nik' => $bp['nik'],
                'type_user_id' => $bp['role'] === 'admin-sistem' ? $adminType->id : $userType->id,
                'status_user_id' => $bp['active'] ? $aktif->id : $nonaktif->id,
                'first_login_at' => 0,
                'status_active' => $bp['active'],
            ]);
            $user->deleted_at = null;
            $user->save();

            $role = Role::where('slug', $bp['role'])->first();
            if ($role) {
                $user->roles()->sync([$role->id]);
            }
        }

        $this->command->info('UatSeeder selesai. Akun: uat.admin, uat.operator, uat.kepsek, uat.waka, uat.inactive.');
        if ($generated) {
            // Dicetak sekali ke stdout; jangan salin ke dokumen terlacak.
            $this->command->warn('UAT_SEED_PASSWORD tidak diset. Kata sandi sintetis sesi ini: '.$password);
        }

        $this->seedSyntheticStudents();
    }

    /**
     * Data siswa sintetis untuk UAT ekspor (tanpa identitas nyata).
     * NIK/NIS/NISN diawali nol untuk menguji leading zero.
     */
    private function seedSyntheticStudents(): void
    {
        $level = SchoolLevel::firstOrCreate(['name' => 'UAT Level']);
        $year = SchoolYear::firstOrCreate(['name' => '2026/2027']);
        $gender = Gender::firstOrCreate(['name' => 'Laki-Laki']);
        $status = StudentStatus::firstOrCreate(['name' => 'Aktif']);
        $entry = StudentEntry::firstOrCreate(['name' => 'Siswa Baru']);

        $rombel = SchoolRombel::firstOrCreate(
            ['school_year_id' => $year->id, 'name' => 'UAT Rombel 1'],
            ['school_level_id' => $level->id]
        );

        $rows = [
            ['name' => 'Synthetic Student One', 'nik' => '0000000000000001', 'nis' => '001', 'nisn' => '0001'],
            ['name' => 'Synthetic Student Two', 'nik' => '0000000000000002', 'nis' => '002', 'nisn' => '0002'],
            ['name' => '=FORMULA TEST, "quoted"', 'nik' => '0000000000000003', 'nis' => '003', 'nisn' => '0003'],
        ];

        foreach ($rows as $row) {
            $student = Student::updateOrCreate(
                ['nik' => $row['nik']],
                [
                    'name' => $row['name'],
                    'birthplace' => 'Synthetic City',
                    'birthdate' => '2010-01-01',
                    'gender_id' => $gender->id,
                    'nis' => $row['nis'],
                    'nisn' => $row['nisn'],
                ]
            );

            StudentRombel::firstOrCreate(
                ['student_nik' => $student->nik, 'school_rombel' => $rombel->id],
                [
                    'student_id' => $student->id,
                    'student_status_id' => $status->id,
                    'student_entry_id' => $entry->id,
                ]
            );
        }

        $this->command->info('UatSeeder: data siswa sintetis dibuat untuk tahun ajaran 2026/2027.');
    }

    /**
     * Fail-safe: menolak seeding UAT pada lingkungan produksi atau database operasional.
     */
    protected function guardEnvironment(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('UAT seeding is strictly prohibited in production environment.');
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
            throw new \RuntimeException(sprintf(
                'UAT seeding refused for connection "%s" and database "%s". Only isolated test/UAT databases are permitted.',
                (string) $connection,
                (string) $database
            ));
        }
    }
}
