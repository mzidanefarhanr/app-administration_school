<?php

namespace Tests\Feature\Integration;

use App\Logging\PiiRedactionProcessor;
use App\Models\AcademicSetting;
use App\Models\ArchiveFileVersion;
use App\Models\District;
use App\Models\EducationLevel;
use App\Models\EducationSchool;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\Employment;
use App\Models\Gender;
use App\Models\LeaveRequest;
use App\Models\Letter;
use App\Models\MaritalStatus;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Religion;
use App\Models\SchoolLevel;
use App\Models\SchoolProfile;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\StatusUser;
use App\Models\Student;
use App\Models\StudentEntry;
use App\Models\StudentStatus;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TeachingJournal;
use App\Models\TeachingSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\Support\CreatesAccessFixtures;
use Tests\TestCase;

class EndToEndIntegrationTest extends TestCase
{
    use CreatesAccessFixtures, RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected User $principal;

    protected User $vicePrincipal;

    protected User $teacherUser;

    protected Employee $teacherEmployee;

    protected SchoolYear $activeYear;

    protected Semester $semester;

    protected SchoolRombel $rombel;

    protected Subject $mathSubject;

    protected EducationSchool $school;

    protected SchoolProfile $schoolProfile;

    protected function setUp(): void
    {
        parent::setUp();

        // Tuesday, 2026-11-10 09:00:00 Asia/Jakarta
        Carbon::setTestNow(Carbon::create(2026, 11, 10, 9, 0, 0, 'Asia/Jakarta'));

        Storage::fake('local');
        Storage::fake('public');

        config([
            'letters.numbering.school_code' => '20512345',
            'letters.numbering.format' => '{seq:03d}/{school_code}/{year}',
        ]);

        $this->seedAccessReferenceData();

        // 5 Canonical Roles
        $this->admin = $this->makeUserWithRole('admin-sistem', ['username' => 'admin.test']);
        $this->operator = $this->makeUserWithRole('operator-sekolah', ['username' => 'operator.test']);
        $this->principal = $this->makeUserWithRole('kepala-sekolah', ['username' => 'kepsek.test']);
        $this->vicePrincipal = $this->makeUserWithRole('wakil-kesiswaan', ['username' => 'waka.test']);
        $this->teacherUser = $this->makeUserWithRole('guru', ['username' => 'guru.test']);

        // Reference Data
        StudentStatus::insertOrIgnore([
            ['id' => 1, 'name' => 'Aktif', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Mutasi Keluar', 'created_at' => now(), 'updated_at' => now()],
        ]);
        StudentEntry::insertOrIgnore([
            ['id' => 1, 'name' => 'Siswa Baru', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Mutasi Masuk', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $province = Province::firstOrCreate(['name' => 'DKI Jakarta']);
        $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Pusat']);
        $district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Gambir']);
        $level = EducationLevel::firstOrCreate(['name' => 'SMA']);

        $this->school = EducationSchool::create([
            'version' => 1,
            'name' => 'SMA Negeri 1 Integrasi',
            'npsn' => '20512345',
            'education_level_id' => $level->id,
            'status_education' => 'Negeri',
            'address' => 'Jl. Medan Merdeka Barat No. 1',
            'district_id' => $district->id,
        ]);

        $this->activeYear = SchoolYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $this->semester = Semester::firstOrCreate(['name' => 'I'], ['name_other' => 'Ganjil']);

        AcademicSetting::find(AcademicSetting::SINGLETON_ID)
            ->update([
                'active_school_year_id' => $this->activeYear->id,
                'active_semester_id' => $this->semester->id,
            ]);

        $statusUser = StatusUser::firstOrCreate(['name' => 'Aktif']);
        $this->schoolProfile = SchoolProfile::create([
            'education_school_npsn' => $this->school->npsn,
            'principal_id' => $this->principal->id,
            'school_year_id' => $this->activeYear->id,
            'status_principal_id' => $statusUser->id,
            'email' => 'sma1@sekolah.sch.id',
        ]);

        $schoolLevel = SchoolLevel::firstOrCreate(['name' => 'Grade 10']);
        $this->rombel = SchoolRombel::create([
            'version' => 1,
            'school_year_id' => $this->activeYear->id,
            'school_level_id' => $schoolLevel->id,
            'name' => '10-A',
        ]);

        $gender = Gender::firstOrCreate(['name' => 'Laki-Laki']);
        $religion = Religion::firstOrCreate(['name' => 'Islam']);
        $marital = MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);
        $empStatus = EmployeeStatus::firstOrCreate(['name' => 'PNS'], ['abbreviation' => 'PNS']);
        $teacherType = EmployeeType::firstOrCreate(['name' => 'Pendidik']);
        $employment = Employment::firstOrCreate(['name' => 'Guru Mapel'], ['description' => 'Guru']);

        $this->mathSubject = Subject::firstOrCreate(['name' => 'Matematika'], ['description' => 'Matematika']);

        $this->teacherEmployee = Employee::factory()->create([
            'name' => 'Drs. Budi Santoso, M.Pd.',
            'nik' => '3171010101800001',
            'nuptk' => '1234567890123456',
            'user_id' => $this->teacherUser->id,
            'user_nik' => $this->teacherUser->nik,
            'employee_type_id' => $teacherType->id,
            'employee_status_id' => $empStatus->id,
            'employment_id' => $employment->id,
            'subject_id' => $this->mathSubject->id,
            'religion_id' => $religion->id,
            'gender_id' => $gender->id,
            'marital_status_id' => $marital->id,
            'district_id' => $district->id,
            'tmt_employee' => '2020-01-01',
            'version' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * T1: Full Role-Based Permission Matrix Audit across 5 Canonical Roles.
     */
    public function test_full_role_based_permission_matrix(): void
    {
        // 1. Admin Sistem: Can manage users & roles, but CANNOT sign letters or approve leave by default (DEC-03)
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/users')->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/roles')->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/backups')->assertOk();
        // Executive approval endpoints are rejected for admin
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/leaves/999/approve', ['reason' => 'test'])->assertForbidden();

        // 2. Operator Sekolah: Can manage students, but CANNOT manage users or backups
        $this->actingAs($this->operator, 'sanctum')->getJson("/api/students?school_year_id={$this->activeYear->id}")->assertOk();
        $this->actingAs($this->operator, 'sanctum')->getJson('/api/users')->assertForbidden();
        $this->actingAs($this->operator, 'sanctum')->getJson('/api/backups')->assertForbidden();

        // 3. Kepala Sekolah: Can view students/employees, view audit, but CANNOT manage users or edit master
        $this->actingAs($this->principal, 'sanctum')->getJson("/api/students?school_year_id={$this->activeYear->id}")->assertOk();
        $this->actingAs($this->principal, 'sanctum')->getJson('/api/activities')->assertOk();
        $this->actingAs($this->principal, 'sanctum')->getJson('/api/users')->assertForbidden();

        // 4. Waka Kurikulum: Can view students and manage teaching, but CANNOT view audit or backups
        $this->actingAs($this->vicePrincipal, 'sanctum')->getJson('/api/teaching/schedules')->assertOk();
        $this->actingAs($this->vicePrincipal, 'sanctum')->getJson('/api/activities')->assertForbidden();
        $this->actingAs($this->vicePrincipal, 'sanctum')->getJson('/api/backups')->assertForbidden();

        // 5. Guru: Can access self-service teaching and leaves, but CANNOT access student directory management or user accounts
        $this->actingAs($this->teacherUser, 'sanctum')->getJson('/api/teaching/schedules')->assertOk();
        $this->actingAs($this->teacherUser, 'sanctum')->getJson('/api/students')->assertForbidden();
        $this->actingAs($this->teacherUser, 'sanctum')->getJson('/api/users')->assertForbidden();
        $this->actingAs($this->teacherUser, 'sanctum')->getJson('/api/letters/outgoing')->assertForbidden();
        $this->actingAs($this->teacherUser, 'sanctum')->getJson('/api/archives')->assertForbidden();
    }

    /**
     * T2: E2E Workflow 1 (Kesiswaan -> Rombel -> Surat Keluar -> Repositori Arsip).
     */
    public function test_e2e_workflow_student_rombel_letter_and_archive(): void
    {
        // Step 1: Operator creates a new student
        $studentRes = $this->actingAs($this->operator, 'sanctum')->postJson('/api/students', [
            'name' => 'Ahmad Rizky Pratama',
            'nis' => '261001',
            'nisn' => '0089123456',
            'nik' => '3171010101100001',
            'gender_id' => Gender::first()->id,
            'religion_id' => Religion::first()->id,
            'birthplace' => 'Jakarta',
            'birthdate' => '2010-04-15',
        ]);
        $studentRes->assertCreated();
        $studentId = $studentRes->json('data.id');

        // Step 2: Operator places student in active rombel using Subfase 03d assign-rombel
        $placementRes = $this->actingAs($this->operator, 'sanctum')->postJson("/api/students/{$studentId}/assign-rombel", [
            'school_year_id' => $this->activeYear->id,
            'school_rombel_id' => $this->rombel->id,
            'expected_student_version' => 1,
        ]);
        $placementRes->assertOk();

        // Step 3: Operator creates outgoing letter draft for this student
        $letterRes = $this->actingAs($this->operator, 'sanctum')->postJson('/api/letters/outgoing', [
            'received_date' => '2026-11-10',
            'letter_date' => '2026-11-10',
            'sender' => 'SMA Negeri 1 Integrasi',
            'recipient' => 'Orang Tua / Wali Murid',
            'subject' => 'Surat Keterangan Siswa Aktif - Ahmad Rizky',
            'category' => 'Notification',
            'notes' => 'Keperluan pengurusan beasiswa.',
        ]);
        $letterRes->assertOk();
        $letterId = $letterRes->json('data.id');

        // Step 4: Operator uploads an official issued document for the letter
        $samplePdf = UploadedFile::fake()->create('surat-aktif-ahmad.pdf', 50, 'application/pdf');
        $archiveRes = $this->actingAs($this->operator, 'sanctum')->postJson('/api/archives', [
            'title' => 'Surat Keterangan Aktif - Ahmad Rizky Pratama',
            'document_number' => '001/20512345/2026',
            'document_date' => '2026-11-10',
            'category' => 'student_record',
            'subject_type' => 'student',
            'subject_id' => $studentId,
            'description' => 'Arsip surat keterangan aktif resmi Ahmad Rizky Pratama.',
            'file' => $samplePdf,
        ]);
        $archiveRes->assertOk();
        $archiveId = $archiveRes->json('data.id');

        // Step 5: Verify archive document integrity (version exists, sha256 checksum recorded)
        $detailRes = $this->actingAs($this->operator, 'sanctum')->getJson("/api/archives/{$archiveId}");
        $detailRes->assertOk();
        $detailRes->assertJsonPath('data.title', 'Surat Keterangan Aktif - Ahmad Rizky Pratama');
        $detailRes->assertJsonPath('data.subject.type', 'student');
        $detailRes->assertJsonPath('data.subject.id', $studentId);

        $version = ArchiveFileVersion::where('archive_document_id', $archiveId)->first();
        $this->assertNotNull($version);
        $this->assertNotEmpty($version->checksum_sha256);
        $this->assertEquals('application/pdf', $version->mime_type);

        // Step 6: Verify principal can access archive, but teacher is denied
        $this->actingAs($this->principal, 'sanctum')->getJson("/api/archives/{$archiveId}")->assertOk();
        $this->actingAs($this->teacherUser, 'sanctum')->getJson("/api/archives/{$archiveId}")->assertForbidden();
    }

    /**
     * T3: E2E Workflow 2 (PTK -> Penugasan -> Jadwal Bebas Bentrok -> Jurnal Mengajar -> Laporan Kepatuhan).
     */
    public function test_e2e_workflow_teacher_assignment_schedule_journal_and_report(): void
    {
        // Step 1: Operator assigns teacher to subject and rombel
        $assignment = TeachingAssignment::create([
            'school_year_id' => $this->activeYear->id,
            'semester_id' => $this->semester->id,
            'school_rombel_id' => $this->rombel->id,
            'employee_id' => $this->teacherEmployee->id,
            'subject_id' => $this->mathSubject->id,
            'version' => 1,
        ]);

        // Step 2: Operator creates weekly schedule on Tuesday (day 2, period 1-2, 07:30 - 09:00)
        $schedule = TeachingSchedule::create([
            'teaching_assignment_id' => $assignment->id,
            'day_of_week' => 2, // Tuesday
            'period_start' => 1,
            'period_end' => 2,
            'start_time' => '07:30',
            'end_time' => '09:00',
            'room' => 'Ruang 101',
            'is_active' => true,
            'version' => 1,
        ]);

        // Step 3: Conflict detection: Attempt to schedule overlapping class for same teacher is rejected
        $conflictRes = $this->actingAs($this->operator, 'sanctum')->postJson('/api/teaching/schedules', [
            'teaching_assignment_id' => $assignment->id,
            'day_of_week' => 2,
            'period_start' => 2,
            'period_end' => 3,
            'start_time' => '08:15',
            'end_time' => '09:45',
            'room' => 'Ruang 102',
        ]);
        $conflictRes->assertStatus(422);

        // Step 4: Teacher fills teaching journal for today (Tuesday 2026-11-10) within 7-day grace window
        $journalPayload = [
            'teaching_schedule_id' => $schedule->id,
            'session_date' => '2026-11-10',
            'topic' => 'Aljabar Linier & Matriks Dasar',
            'method' => 'lecture',
            'summary' => 'Pembahasan operasi perkalian matriks ordo 2x2 dan latihan soal mandiri.',
            'notes' => 'Seluruh siswa hadir lengkap dan aktif mengikuti latihan.',
        ];

        $journalRes = $this->actingAs($this->teacherUser, 'sanctum')->postJson('/api/teaching/journals', $journalPayload);
        $journalRes->assertCreated();
        $journalId = $journalRes->json('data.id');

        // Step 5: Idempotent retry: Re-submitting identical payload returns 200 OK without creating duplicate
        $retryRes = $this->actingAs($this->teacherUser, 'sanctum')->postJson('/api/teaching/journals', $journalPayload);
        $retryRes->assertOk();
        $this->assertEquals($journalId, $retryRes->json('data.id'));
        $this->assertEquals(1, TeachingJournal::where('teaching_schedule_id', $schedule->id)->where('session_date', '2026-11-10')->count());

        // Step 6: Principal reviews Missing Journals Report: Session is fulfilled (0 missing)
        $reportRes = $this->actingAs($this->principal, 'sanctum')->getJson("/api/teaching/reports/missing-journals?school_year_id={$this->activeYear->id}&semester_id={$this->semester->id}&date_from=2026-11-10&date_to=2026-11-10");
        $reportRes->assertOk();
        $byTeacher = $reportRes->json('data.by_teacher');
        $this->assertNotEmpty($byTeacher);
        $teacherRow = collect($byTeacher)->firstWhere('employee_id', $this->teacherEmployee->id);
        $this->assertNotNull($teacherRow);
        $this->assertEquals(1, $teacherRow['recorded_journals']);
        $this->assertEquals(0, $teacherRow['missing_journals']);
        $this->assertEquals(100.0, (float) $teacherRow['compliance_percentage']);
    }

    /**
     * T4: E2E Workflow 3 (Pengajuan Izin Guru -> Pusat Tugas Operator -> Persetujuan Kepala Sekolah).
     */
    public function test_e2e_workflow_leave_request_tasks_and_principal_approval(): void
    {
        // Step 1: Teacher submits a leave request for own employee profile
        $leaveRes = $this->actingAs($this->teacherUser, 'sanctum')->postJson('/api/leaves', [
            'subject_type' => LeaveRequest::SUBJECT_EMPLOYEE,
            'employee_id' => $this->teacherEmployee->id,
            'type' => 'sick',
            'start_date' => '2026-11-11',
            'end_date' => '2026-11-12',
            'reason' => 'Sakit demam tinggi sesuai surat keterangan dokter terlampir.',
        ]);
        $leaveRes->assertOk();
        $leaveId = $leaveRes->json('data.id');

        // Submit the leave
        $submitRes = $this->actingAs($this->teacherUser, 'sanctum')->postJson("/api/leaves/{$leaveId}/submit", [
            'expected_version' => 1,
        ]);
        $submitRes->assertOk();

        // Step 2: Operational Tasks: The pending leave surfaces in task center for principal
        $tasksRes = $this->actingAs($this->principal, 'sanctum')->getJson('/api/tasks?tab=system');
        $tasksRes->assertOk();
        $tasks = $tasksRes->json('data');
        $leaveTask = collect($tasks)->first(fn ($t) => ($t['domain'] ?? '') === 'leaves' && str_contains($t['id'] ?? '', (string) $leaveId));
        $this->assertNotNull($leaveTask, 'Pending leave task should surface in operational task center.');

        // Step 3: Self-Approval Prevention: Teacher cannot approve own leave request
        $selfApprovalRes = $this->actingAs($this->teacherUser, 'sanctum')->postJson("/api/leaves/{$leaveId}/approve", [
            'reason' => 'Saya menyetujui izin saya sendiri.',
        ]);
        $selfApprovalRes->assertForbidden();

        // Step 4: Principal approves the leave request officially
        $approvalRes = $this->actingAs($this->principal, 'sanctum')->postJson("/api/leaves/{$leaveId}/approve", [
            'reason' => 'Disetujui, semoga lekas sembuh.',
            'expected_version' => 2,
        ]);
        $approvalRes->assertOk();
        $this->assertEquals(LeaveRequest::STATUS_APPROVED, $approvalRes->json('data.status'));

        // Step 5: Verify the leave request status is updated in database
        $leave = LeaveRequest::find($leaveId);
        $this->assertEquals(LeaveRequest::STATUS_APPROVED, $leave->status);
    }

    /**
     * T5: E2E Workflow 4 (Cadangan Data & Observabilitas Correlation ID).
     */
    public function test_e2e_backup_status_and_correlation_id_observability(): void
    {
        // Step 1: Admin inspects backups status
        $res = $this->actingAs($this->admin, 'sanctum')->getJson('/api/backups/status');
        $res->assertOk();
        $res->assertHeader('X-Correlation-ID');

        $correlationId = $res->headers->get('X-Correlation-ID');
        $this->assertNotEmpty($correlationId);
        $this->assertTrue(Str::isUuid($correlationId) || strlen($correlationId) >= 16);

        // Step 2: Unprivileged user (guru) is denied from backups
        $this->actingAs($this->teacherUser, 'sanctum')->getJson('/api/backups/status')->assertForbidden();
    }

    /**
     * T6: Zero PII Leak Verification on Monolog Redaction and API Responses.
     */
    public function test_zero_pii_leak_on_monolog_logs_and_api(): void
    {
        // 1. Monolog PiiRedactionProcessor test
        $processor = new PiiRedactionProcessor;
        $record = new LogRecord(
            new \DateTimeImmutable,
            'testing',
            Level::Info,
            'Sensitive user login attempt for NIK 3171010101800001 with password SecretPass123!',
            [
                'password' => 'superSecretPassword',
                'nik' => '3171010101800001',
                'token' => 'plain-text-token-value',
                'phone' => '081234567890',
            ]
        );

        $processed = $processor($record);

        // Message level redaction
        $this->assertStringNotContainsString('SecretPass123!', $processed->message);
        $this->assertStringNotContainsString('3171010101800001', $processed->message);
        $this->assertStringContainsString('[REDACTED]', $processed->message);

        // Context level redaction
        $this->assertEquals('[REDACTED]', $processed->context['password']);
        $this->assertEquals('[REDACTED]', $processed->context['nik']);
        $this->assertEquals('[REDACTED]', $processed->context['token']);
        $this->assertEquals('[REDACTED]', $processed->context['phone']);

        // 2. Standardized error format check with Correlation ID
        $errorRes = $this->getJson('/api/non-existent-endpoint-integration-audit');
        $errorRes->assertNotFound();
        $errorRes->assertHeader('X-Correlation-ID');
        $errorRes->assertJsonStructure([
            'message',
            'correlation_id',
        ]);
        $this->assertNotEmpty($errorRes->json('correlation_id'));
    }
}
