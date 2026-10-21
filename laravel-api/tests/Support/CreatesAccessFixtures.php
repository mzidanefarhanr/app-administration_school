<?php

namespace Tests\Support;

use App\Models\Ability;
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
use App\Support\AbilityCatalog;

/**
 * Helper fixture sintetis untuk tes akses (Fase 01a).
 *
 * Tidak memuat identitas nyata. Membuat data referensi minimum dan peran/kemampuan
 * sesuai katalog, agar tes dapat berjalan pada database SQLite in-memory terisolasi.
 */
trait CreatesAccessFixtures
{
    /**
     * Pastikan tabel referensi users (type_users, status_users) dan peran/kemampuan ada.
     */
    protected function seedAccessReferenceData(): void
    {
        TypeUser::firstOrCreate(['name' => 'Admin']);
        TypeUser::firstOrCreate(['name' => 'User']);

        StatusUser::firstOrCreate(['name' => 'Aktif']);
        StatusUser::firstOrCreate(['name' => 'Tidak Aktif']);

        foreach (AbilityCatalog::definitions() as $name => $def) {
            Ability::firstOrCreate(
                ['name' => $name],
                [
                    'group' => $def['group'],
                    'description' => $def['description'],
                    'is_sensitive' => $def['sensitive'],
                ]
            );
        }

        foreach (AbilityCatalog::initialRoles() as $slug => $roleDef) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $roleDef['name'],
                    'description' => $roleDef['description'],
                    'is_protected' => $roleDef['protected'],
                ]
            );

            if ($role->abilities()->count() === 0) {
                $role->abilities()->sync(
                    Ability::whereIn('name', $roleDef['abilities'])->pluck('id')->all()
                );
            }
        }
    }

    protected function role(string $slug): Role
    {
        return Role::where('slug', $slug)->firstOrFail();
    }

    /**
     * Buat pengguna dengan peran tertentu (dan status aktif).
     *
     * @param  string|array<int, string>|null  $roleSlugs
     */
    protected function makeUserWithRole($roleSlugs = null, array $attributes = []): User
    {
        $this->seedAccessReferenceData();

        $user = User::factory()->create(array_merge([
            'status_active' => true,
        ], $attributes));

        if ($roleSlugs !== null) {
            $ids = collect((array) $roleSlugs)
                ->map(fn (string $slug) => $this->role($slug)->id)
                ->all();
            $user->roles()->sync($ids);
        }

        $user->flushAbilityCache();

        return $user;
    }

    /**
     * Buat data siswa sintetis untuk satu tahun ajaran.
     * Mengembalikan array dengan school_year_id dan daftar baris student_rombels.
     *
     * @return array{school_year_id: int, other_school_year_id: int, student_rombels: \Illuminate\Support\Collection}
     */
    protected function seedStudentData(int $count = 3): array
    {
        $level = SchoolLevel::firstOrCreate(['name' => 'Level UAT']);

        $year = SchoolYear::firstOrCreate(['name' => '2026/2027']);
        $otherYear = SchoolYear::firstOrCreate(['name' => '2025/2026']);

        $gender = Gender::firstOrCreate(['name' => 'Laki-Laki']);
        $status = StudentStatus::firstOrCreate(['name' => 'Aktif']);
        $entry = StudentEntry::firstOrCreate(['name' => 'Siswa Baru']);

        $rombel = SchoolRombel::create([
            'school_level_id' => $level->id,
            'school_year_id' => $year->id,
            'name' => 'Rombel UAT 1',
        ]);

        $otherRombel = SchoolRombel::create([
            'school_level_id' => $level->id,
            'school_year_id' => $otherYear->id,
            'name' => 'Rombel UAT Lain',
        ]);

        $made = collect();

        for ($i = 1; $i <= $count; $i++) {
            // NIK diawali nol untuk menguji leading zero tidak hilang.
            $nik = '0'.str_pad((string) $i, 15, '0', STR_PAD_LEFT);

            $student = Student::create([
                'name' => 'Synthetic Student '.$i,
                'nik' => $nik,
                'birthplace' => 'Synthetic City',
                'birthdate' => '2010-01-0'.(($i % 9) + 1),
                'gender_id' => $gender->id,
                'nis' => '00'.$i,
                'nisn' => '000'.$i,
            ]);

            $made->push(StudentRombel::create([
                'student_id' => $student->id,
                'student_nik' => $student->nik,
                'school_rombel' => $rombel->id,
                'student_status_id' => $status->id,
                'student_entry_id' => $entry->id,
            ]));
        }

        // Satu siswa di tahun ajaran lain (tidak boleh ikut ekspor tahun ini).
        $otherStudent = Student::create([
            'name' => 'Synthetic Student Other Year',
            'nik' => '0999999999999999',
            'gender_id' => $gender->id,
        ]);
        StudentRombel::create([
            'student_nik' => $otherStudent->nik,
            'school_rombel' => $otherRombel->id,
            'student_status_id' => $status->id,
            'student_entry_id' => $entry->id,
        ]);

        return [
            'school_year_id' => $year->id,
            'other_school_year_id' => $otherYear->id,
            'student_rombels' => $made,
            'other_rombel_id' => $otherRombel->id,
        ];
    }
}
