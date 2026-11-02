<?php

namespace Tests\Support;

use App\Models\AcademicSetting;
use App\Models\District;
use App\Models\EducationLevel;
use App\Models\EducationSchool;
use App\Models\Employee;
use App\Models\Letter;
use App\Models\LetterSettings;
use App\Models\Province;
use App\Models\Regency;
use App\Models\SchoolLevel;
use App\Models\SchoolProfile;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEntry;
use App\Models\StudentMutation;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * Fixture sintetis untuk surat bertipe dan setelan kop (Fase 07c).
 *
 * Tidak memuat identitas nyata; seluruh file ditulis ke disk local palsu.
 */
trait CreatesLetterFixtures
{
    protected function seedLetterContext(): void
    {
        StudentStatus::insertOrIgnore([
            ['id' => 1, 'name' => 'Aktif', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Mutasi Keluar', 'created_at' => now(), 'updated_at' => now()],
        ]);

        StudentEntry::insertOrIgnore([
            ['id' => 1, 'name' => 'Siswa Baru', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Mutasi Masuk', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $province = Province::firstOrCreate(['name' => 'UAT P07C Province']);
        $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'UAT P07C Regency']);
        $district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'UAT P07C District']);
        $educationLevel = EducationLevel::firstOrCreate(['name' => 'UAT P07C Level']);

        $this->school = EducationSchool::create([
            'version' => 1,
            'name' => 'SMA Negeri 1 UAT',
            'npsn' => '20512345',
            'education_level_id' => $educationLevel->id,
            'status_education' => 'Negeri',
            'address' => 'Jalan Pendidikan Nomor 1',
            'district_id' => $district->id,
        ]);

        $this->nextSchool = EducationSchool::create([
            'version' => 1,
            'name' => 'SMA Negeri 2 UAT',
            'npsn' => '20512346',
            'education_level_id' => $educationLevel->id,
            'status_education' => 'Negeri',
            'address' => 'Jalan Pendidikan Nomor 2',
            'district_id' => $district->id,
        ]);

        $this->destinationSchool = EducationSchool::create([
            'version' => 1,
            'name' => 'SMA Tujuan UAT',
            'npsn' => '81000002',
            'education_level_id' => $educationLevel->id,
            'status_education' => 'Swasta',
            'address' => 'Jalan Tujuan Nomor 2',
            'district_id' => $district->id,
        ]);

        $this->activeYear = SchoolYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $this->nextYear = SchoolYear::create([
            'name' => '2027/2028',
            'start_date' => '2027-07-01',
            'end_date' => '2028-06-30',
        ]);

        AcademicSetting::find(AcademicSetting::SINGLETON_ID)
            ->update(['active_school_year_id' => $this->activeYear->id]);

        $this->profile = $this->makeProfile($this->school, $this->activeYear);

        $level = SchoolLevel::create(['name' => 'Grade 10']);
        $nextLevel = SchoolLevel::create(['name' => 'Grade 11']);

        $this->rombel = SchoolRombel::create([
            'version' => 1,
            'school_year_id' => $this->activeYear->id,
            'school_level_id' => $level->id,
            'name' => '10-A',
        ]);

        $this->nextRombel = SchoolRombel::create([
            'version' => 1,
            'school_year_id' => $this->nextYear->id,
            'school_level_id' => $nextLevel->id,
            'name' => '11-A',
        ]);

        $this->student = Student::factory()->create([
            'name' => 'Synthetic Student Active',
            'nis' => '0710001',
            'nisn' => '0071000001',
            'birthplace' => 'Synthetic City',
            'birthdate' => '2010-05-10',
        ]);

        $this->studentRombel = StudentRombel::create([
            'version' => 1,
            'student_id' => $this->student->id,
            'student_nik' => $this->student->nik,
            'school_rombel' => $this->rombel->id,
            'student_status_id' => 1,
            'student_entry_id' => 1,
            'latest_student_entry_date' => '2026-07-10',
        ]);

        $this->otherStudent = Student::factory()->create([
            'name' => 'Synthetic Student Inactive',
            'nis' => '0710002',
            'nisn' => '0071000002',
            'birthplace' => 'Synthetic City',
            'birthdate' => '2010-06-11',
        ]);

        $this->employee = Employee::factory()->create([
            'name' => 'Synthetic Employee Active',
            'nuptk' => '1234567890123456',
            'exit_date' => null,
        ]);

        $this->exitedEmployee = Employee::factory()->create([
            'name' => 'Synthetic Employee Exited',
            'nuptk' => '6543210987654321',
            'exit_date' => '2026-01-31',
        ]);

        $this->mutation = StudentMutation::create([
            'version' => 1,
            'type' => StudentMutation::TYPE_OUT,
            'school_year_id' => $this->activeYear->id,
            'student_id' => $this->student->id,
            'student_rombel_id' => $this->studentRombel->id,
            'school_rombel_id' => $this->rombel->id,
            'effective_date' => '2026-10-01',
            'school_npsn' => $this->destinationSchool->npsn,
            'reason' => 'Synthetic mutation reason.',
            'status' => StudentMutation::STATUS_ACTIVE,
            'created_by' => $this->operator->id,
        ]);

        $this->cancelledMutation = StudentMutation::create([
            'version' => 1,
            'type' => StudentMutation::TYPE_OUT,
            'school_year_id' => $this->activeYear->id,
            'student_id' => $this->student->id,
            'student_rombel_id' => $this->studentRombel->id,
            'school_rombel_id' => $this->rombel->id,
            'effective_date' => '2026-09-01',
            'school_npsn' => $this->destinationSchool->npsn,
            'reason' => 'Synthetic cancelled mutation.',
            'status' => StudentMutation::STATUS_CANCELLED,
            'created_by' => $this->operator->id,
        ]);

        $this->inboundMutation = StudentMutation::create([
            'version' => 1,
            'type' => StudentMutation::TYPE_IN,
            'school_year_id' => $this->activeYear->id,
            'student_id' => $this->student->id,
            'student_rombel_id' => $this->studentRombel->id,
            'school_rombel_id' => $this->rombel->id,
            'effective_date' => '2026-08-01',
            'school_npsn' => $this->school->npsn,
            'reason' => 'Synthetic inbound mutation.',
            'status' => StudentMutation::STATUS_ACTIVE,
            'created_by' => $this->operator->id,
        ]);
    }

    protected function makeProfile(EducationSchool $school, SchoolYear $year): SchoolProfile
    {
        return SchoolProfile::create([
            'education_school_npsn' => $school->npsn,
            'principal_id' => $this->principal->id,
            'school_year_id' => $year->id,
            'status_principal_id' => 1,
            'email' => 'school@example.test',
        ]);
    }

    protected function makeLetterSettings(array $overrides = []): LetterSettings
    {
        $profileId = (int) $this->profile->id;
        $disk = Storage::disk('local');
        $directory = 'attachments/letter-settings/'.$profileId;

        $attributes = [];
        $palettes = [
            'logo' => [20, 60, 120],
            'signature' => [10, 10, 10],
            'stamp' => [150, 20, 20],
        ];

        foreach ($palettes as $asset => [$red, $green, $blue]) {
            $bytes = $this->pngBytes($red, $green, $blue);
            $path = $directory.'/'.$asset.'-fixture.png';
            $disk->put($path, $bytes);

            $attributes[$asset.'_path'] = $path;
            $attributes[$asset.'_mime'] = 'image/png';
            $attributes[$asset.'_size_bytes'] = strlen($bytes);
            $attributes[$asset.'_sha256'] = hash('sha256', $bytes);
        }

        return LetterSettings::create(array_merge([
            'school_profile_id' => $profileId,
            'version' => 1,
            'government_line' => 'PEMERINTAH PROVINSI UAT',
            'school_name' => 'SMA Negeri 1 UAT',
            'address_line' => 'Jalan Pendidikan Nomor 1',
            'contact_line' => 'school@example.test',
            'place_of_issue' => 'Synthetic City',
            'signatory_name' => 'Synthetic Principal',
            'signatory_title' => 'Kepala Sekolah',
            'signatory_identifier_label' => 'NIP',
            'signatory_identifier' => '198001012006041001',
            'updated_by' => $this->operator->id,
        ], $attributes, $overrides));
    }

    protected function pngBytes(int $red = 20, int $green = 60, int $blue = 120): string
    {
        $image = imagecreatetruecolor(16, 16);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);
        imagefilledrectangle($image, 2, 2, 13, 13, imagecolorallocate($image, $red, $green, $blue));

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    protected function pngUpload(string $name = 'asset.png', ?string $bytes = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes ?? $this->pngBytes());
    }

    /**
     * @return array<string, mixed>
     */
    protected function outgoingPayload(array $overrides = []): array
    {
        return array_merge([
            'received_date' => '2026-11-09',
            'letter_date' => '2026-11-08',
            'sender' => 'SMA Negeri 1 UAT',
            'recipient' => 'Dinas Pendidikan Kabupaten',
            'subject' => 'Synthetic outgoing subject',
            'category' => 'Notification',
            'notes' => null,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function typedPayload(string $type, array $overrides = []): array
    {
        $base = match ($type) {
            'student_active' => [
                'letter_type' => 'student_active',
                'document_subject_id' => $this->student->id,
                'document_fields' => ['purpose' => 'Melanjutkan pendidikan ke jenjang berikutnya'],
            ],
            'student_mutation' => [
                'letter_type' => 'student_mutation',
                'document_subject_id' => $this->mutation->id,
                'document_fields' => [],
            ],
            'employee_duty' => [
                'letter_type' => 'employee_duty',
                'document_subject_id' => $this->employee->id,
                'document_fields' => [
                    'duty_description' => 'Menghadiri kegiatan peningkatan kompetensi tenaga pendidik.',
                    'start_date' => '2026-11-12',
                    'end_date' => '2026-11-14',
                    'location' => 'Dinas Pendidikan Kabupaten',
                ],
            ],
            default => throw new \InvalidArgumentException("Unknown typed payload [{$type}]."),
        };

        return $this->outgoingPayload(array_merge($base, $overrides));
    }

    protected function createTypedViaApi(string $type, array $overrides = []): Letter
    {
        Sanctum::actingAs($this->operator);

        $id = $this->postJson('/api/letters/outgoing', $this->typedPayload($type, $overrides))
            ->assertOk()
            ->json('data.id');

        return Letter::findOrFail($id);
    }

    protected function submitViaApi(Letter $letter): Letter
    {
        Sanctum::actingAs($this->operator);

        $this->postJson("/api/letters/outgoing/{$letter->id}/submit", [
            'expected_version' => $letter->version,
        ])->assertOk();

        return $letter->fresh();
    }

    protected function approveViaApi(Letter $letter): Letter
    {
        Sanctum::actingAs($this->principal);

        $this->postJson("/api/letters/outgoing/{$letter->id}/approve", [
            'expected_version' => $letter->version,
        ])->assertOk();

        return $letter->fresh();
    }

    protected function issueViaApi(Letter $letter): Letter
    {
        Sanctum::actingAs($this->principal);

        $this->postJson("/api/letters/outgoing/{$letter->id}/issue", [
            'expected_version' => $letter->version,
        ])->assertOk();

        return $letter->fresh();
    }

    protected function issueTypedViaApi(string $type, array $overrides = []): Letter
    {
        $letter = $this->createTypedViaApi($type, $overrides);
        $letter = $this->submitViaApi($letter);
        $letter = $this->approveViaApi($letter);

        return $this->issueViaApi($letter);
    }

    protected function typedLetterRecord(string $type, array $overrides = []): Letter
    {
        $definition = config('letters.letter_types.'.$type);
        $fields = match ($type) {
            'student_active' => ['purpose' => 'Keperluan administrasi sekolah'],
            'student_mutation' => [],
            'employee_duty' => [
                'duty_description' => 'Menghadiri kegiatan peningkatan kompetensi tenaga pendidik.',
                'start_date' => '2026-11-12',
                'end_date' => '2026-11-14',
                'location' => 'Dinas Pendidikan Kabupaten',
            ],
            default => throw new \InvalidArgumentException("Unknown typed letter [{$type}]."),
        };

        return Letter::create(array_merge([
            'direction' => Letter::DIRECTION_OUTGOING,
            'version' => 1,
            'received_date' => '2026-11-09',
            'letter_date' => '2026-11-08',
            'sender' => 'SMA Negeri 1 UAT',
            'recipient' => 'Dinas Pendidikan Kabupaten',
            'subject' => 'Synthetic typed letter',
            'category' => 'Notification',
            'notes' => null,
            'letter_type' => $type,
            'document_subject_type' => $definition['subject_type'],
            'document_subject_id' => match ($type) {
                'student_active' => $this->student->id,
                'student_mutation' => $this->mutation->id,
                'employee_duty' => $this->employee->id,
            },
            'document_fields' => $fields,
            'status' => Letter::STATUS_ACTIVE,
            'workflow_status' => Letter::WORKFLOW_DRAFT,
            'created_by' => $this->operator->id,
        ], $overrides));
    }
}
