<?php

use App\Models\AcademicSetting;
use App\Models\Activity;
use App\Models\Employee;
use App\Models\LeaveAttachment;
use App\Models\LeaveDecision;
use App\Models\LeaveRequest;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEntry;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 10, 9, 0, 0, 'Asia/Jakarta'));

    Storage::fake('local');

    $this->seedAccessReferenceData();

    $this->admin = $this->makeUserWithRole('admin-sistem');
    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->wakil = $this->makeUserWithRole('wakil-kesiswaan');
    $this->teacher = $this->makeUserWithRole('guru');
    $this->unlinkedTeacher = $this->makeUserWithRole('guru');

    $this->year = SchoolYear::create([
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
        ->update(['active_school_year_id' => $this->year->id]);

    StudentStatus::insertOrIgnore([
        ['id' => 1, 'name' => 'Aktif', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Mutasi Keluar', 'created_at' => now(), 'updated_at' => now()],
    ]);

    StudentEntry::insertOrIgnore([
        ['id' => 1, 'name' => 'Siswa Baru', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $level = SchoolLevel::create(['name' => 'Grade 10']);
    $nextLevel = SchoolLevel::create(['name' => 'Grade 11']);

    $this->rombel = SchoolRombel::create([
        'version' => 1,
        'school_year_id' => $this->year->id,
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
        'name' => 'Synthetic Student One',
        'nis' => '0810001',
        'nisn' => '0081000001',
    ]);

    $this->membership = StudentRombel::create([
        'version' => 1,
        'student_id' => $this->student->id,
        'student_nik' => $this->student->nik,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => 1,
        'student_entry_id' => 1,
        'latest_student_entry_date' => '2026-07-10',
    ]);

    $this->secondStudent = Student::factory()->create([
        'name' => 'Synthetic Student Two',
        'nis' => '0810002',
        'nisn' => '0081000002',
    ]);

    $this->secondMembership = StudentRombel::create([
        'version' => 1,
        'student_id' => $this->secondStudent->id,
        'student_nik' => $this->secondStudent->nik,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => 1,
        'student_entry_id' => 1,
        'latest_student_entry_date' => '2026-07-10',
    ]);

    $this->archivedStudent = Student::factory()->create([
        'name' => 'Synthetic Student Archived',
        'nis' => '0810003',
        'nisn' => '0081000003',
    ]);

    StudentRombel::create([
        'version' => 1,
        'student_id' => $this->archivedStudent->id,
        'student_nik' => $this->archivedStudent->nik,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => 1,
        'student_entry_id' => 1,
        'archived_at' => '2026-10-01',
    ]);

    $this->studentWithoutMembership = Student::factory()->create([
        'name' => 'Synthetic Student Without Membership',
        'nis' => '0810004',
        'nisn' => '0081000004',
    ]);

    $this->employee = Employee::factory()->create([
        'name' => 'Synthetic Teacher Linked',
        'user_id' => $this->teacher->id,
        'exit_date' => null,
    ]);

    $this->otherEmployee = Employee::factory()->create([
        'name' => 'Synthetic Employee Unlinked',
        'user_id' => null,
        'exit_date' => null,
    ]);

    $this->exitedEmployee = Employee::factory()->create([
        'name' => 'Synthetic Employee Exited',
        'user_id' => null,
        'exit_date' => '2026-10-01',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return array<string, mixed>
 */
function p08aStudentPayload(int $studentId, array $overrides = []): array
{
    return array_merge([
        'subject_type' => 'student',
        'student_id' => $studentId,
        'type' => 'sick',
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-12',
        'reason' => 'Synthetic medical leave for the operator test.',
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function p08aEmployeePayload(int $employeeId, array $overrides = []): array
{
    return array_merge([
        'subject_type' => 'employee',
        'employee_id' => $employeeId,
        'type' => 'annual',
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-12',
        'reason' => 'Synthetic annual leave for the employee test.',
    ], $overrides);
}

function p08aCreateStudent(\Tests\TestCase $test, array $overrides = []): LeaveRequest
{
    Sanctum::actingAs($test->operator);

    $id = $test->postJson('/api/leaves', p08aStudentPayload($test->student->id, $overrides))
        ->assertOk()
        ->json('data.id');

    return LeaveRequest::findOrFail($id);
}

function p08aSubmit(\Tests\TestCase $test, LeaveRequest $leave): LeaveRequest
{
    Sanctum::actingAs($test->operator);

    $test->postJson("/api/leaves/{$leave->id}/submit", [
        'expected_version' => $leave->version,
    ])->assertOk();

    return $leave->fresh();
}

function p08aPdf(string $name = 'leave.pdf'): UploadedFile
{
    $content = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    return UploadedFile::fake()->createWithContent($name, $content);
}

// T1 — guest dan akun nonaktif.
it('rejects guests and inactive accounts on leave endpoints', function () {
    $this->getJson('/api/leaves/options')->assertStatus(401);
    $this->getJson('/api/leaves')->assertStatus(401);
    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id))->assertStatus(401);

    $inactive = $this->makeUserWithRole('admin-sistem', ['status_active' => false]);
    Sanctum::actingAs($inactive);

    $this->getJson('/api/leaves/options')->assertStatus(403);
    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id))->assertStatus(403);

    expect(LeaveRequest::count())->toBe(0);
});

// T2 — matriks ability per peran.
it('enforces the leave ability matrix', function () {
    $draft = p08aCreateStudent($this, [
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-10',
    ]);

    Sanctum::actingAs($this->teacher);
    $this->getJson('/api/leaves')->assertStatus(403);
    $this->getJson('/api/leaves/mine')->assertOk();
    $this->getJson('/api/leaves/options')->assertOk();
    $this->getJson('/api/leaves/subjects/students')->assertStatus(403);
    $this->getJson('/api/leaves/subjects/employees')->assertStatus(403);
    $this->getJson("/api/leaves/{$draft->id}")->assertStatus(404);

    Sanctum::actingAs($this->wakil);
    $this->getJson('/api/leaves')->assertOk();
    $this->getJson('/api/leaves/options')->assertOk();
    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id))->assertStatus(403);
    $this->postJson('/api/leaves', p08aEmployeePayload($this->otherEmployee->id))->assertStatus(403);

    Sanctum::actingAs($this->operator);
    $this->getJson('/api/leaves/options')->assertOk();
    $this->getJson('/api/leaves/subjects/students')->assertOk();
    $this->getJson('/api/leaves/subjects/employees')->assertOk();

    expect(LeaveRequest::count())->toBe(1);
    expect($draft->fresh()->status)->toBe(LeaveRequest::STATUS_DRAFT);
});

// T3 — operator membuat izin siswa.
it('lets an operator create a student leave draft with subject snapshot', function () {
    Sanctum::actingAs($this->operator);

    $response = $this->postJson('/api/leaves', p08aStudentPayload($this->student->id))
        ->assertOk()
        ->assertJsonPath('data.subject_type', 'student')
        ->assertJsonPath('data.subject.id', $this->student->id)
        ->assertJsonPath('data.subject.class', '10-A')
        ->assertJsonPath('data.subject.school_year', '2026/2027')
        ->assertJsonPath('data.type', 'sick')
        ->assertJsonPath('data.type_label', 'Sick Leave')
        ->assertJsonPath('data.days', 3)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.can.edit', true)
        ->assertJsonPath('data.can.submit', true);

    $leave = LeaveRequest::findOrFail($response->json('data.id'));

    expect($leave->student_id)->toBe($this->student->id)
        ->and($leave->school_year_id)->toBe($this->year->id)
        ->and($leave->student_rombel_id)->toBe($this->membership->id)
        ->and($leave->requested_by)->toBe($this->operator->id)
        ->and($leave->submitted_at)->toBeNull();
});

// T4 — guru default tidak boleh mengajukan untuk siswa/pegawai lain.
it('denies teachers from creating student or other employee leave requests', function () {
    Sanctum::actingAs($this->teacher);

    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id))
        ->assertStatus(403);

    $this->postJson('/api/leaves', p08aEmployeePayload($this->otherEmployee->id))
        ->assertStatus(403);

    expect(LeaveRequest::count())->toBe(0);
});

// T5 — guru self service.
it('lets a teacher request leave for their own linked employee profile', function () {
    Sanctum::actingAs($this->teacher);

    $this->getJson('/api/leaves/options')
        ->assertOk()
        ->assertJsonPath('data.self_employee.id', $this->employee->id)
        ->assertJsonPath('data.self_employee.name', 'Synthetic Teacher Linked');

    $response = $this->postJson('/api/leaves', p08aEmployeePayload($this->employee->id))
        ->assertOk()
        ->assertJsonPath('data.subject_type', 'employee')
        ->assertJsonPath('data.subject.id', $this->employee->id)
        ->assertJsonPath('data.subject.name', 'Synthetic Teacher Linked');

    $leave = LeaveRequest::findOrFail($response->json('data.id'));
    expect($leave->employee_id)->toBe($this->employee->id)
        ->and($leave->requested_by)->toBe($this->teacher->id);

    $this->getJson('/api/leaves/mine')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.id', $leave->id);
});

// T6 — tanpa tautan akun / tautan bukan milik aktor.
it('denies self requests without an exact employee account link', function () {
    Sanctum::actingAs($this->unlinkedTeacher);

    $this->getJson('/api/leaves/options')
        ->assertOk()
        ->assertJsonPath('data.self_employee', null);

    $this->postJson('/api/leaves', p08aEmployeePayload($this->otherEmployee->id))
        ->assertStatus(403);

    $this->postJson('/api/leaves', p08aEmployeePayload($this->exitedEmployee->id))
        ->assertStatus(403);

    expect(LeaveRequest::count())->toBe(0);
});

// T7 — siswa arsip/tanpa keanggotaan/tanpa tahun aktif.
it('rejects student subjects without an active membership or active school year', function () {
    Sanctum::actingAs($this->operator);

    $this->postJson('/api/leaves', p08aStudentPayload($this->archivedStudent->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['student_id']);

    $this->postJson('/api/leaves', p08aStudentPayload($this->studentWithoutMembership->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['student_id']);

    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id, [
        'start_date' => '2027-07-05',
        'end_date' => '2027-07-06',
    ]))->assertStatus(422)
        ->assertJsonValidationErrors(['start_date']);

    AcademicSetting::find(AcademicSetting::SINGLETON_ID)
        ->update(['active_school_year_id' => null]);

    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['school_year']);

    expect(LeaveRequest::count())->toBe(0);
});

// T8 — pegawai keluar/arsip.
it('rejects employees who left before the leave start date', function () {
    Sanctum::actingAs($this->operator);

    $this->postJson('/api/leaves', p08aEmployeePayload($this->exitedEmployee->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['employee_id']);

    $this->otherEmployee->delete();

    $this->postJson('/api/leaves', p08aEmployeePayload($this->otherEmployee->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['employee_id']);

    expect(LeaveRequest::count())->toBe(0);
});

// T9 — jenis harus cocok dengan subject_type.
it('rejects leave types that do not belong to the subject type', function () {
    Sanctum::actingAs($this->operator);

    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id, [
        'type' => 'annual',
    ]))->assertStatus(422)->assertJsonValidationErrors(['type']);

    $this->postJson('/api/leaves', p08aEmployeePayload($this->employee->id, [
        'type' => 'permission',
    ]))->assertStatus(422)->assertJsonValidationErrors(['type']);

    expect(LeaveRequest::count())->toBe(0);
});

// T10 — aturan tanggal.
it('enforces date order, range limits, and student school year bounds', function () {
    Sanctum::actingAs($this->operator);

    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id, [
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-10',
    ]))->assertStatus(422)->assertJsonValidationErrors(['end_date']);

    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id, [
        'start_date' => '2026-11-01',
        'end_date' => '2026-12-05',
    ]))->assertStatus(422)->assertJsonValidationErrors(['end_date']);

    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id, [
        'start_date' => '2026-06-30',
        'end_date' => '2026-07-01',
    ]))->assertStatus(422)->assertJsonValidationErrors(['start_date']);

    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id, [
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-10',
    ]))->assertOk();

    expect(LeaveRequest::count())->toBe(1);
});

// T11 — alasan.
it('enforces the reason length boundaries', function () {
    Sanctum::actingAs($this->operator);

    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id, [
        'reason' => 'Sick',
    ]))->assertStatus(422)->assertJsonValidationErrors(['reason']);

    $this->postJson('/api/leaves', p08aStudentPayload($this->student->id, [
        'reason' => str_repeat('a', 1001),
    ]))->assertStatus(422)->assertJsonValidationErrors(['reason']);

    expect(LeaveRequest::count())->toBe(0);
});

// T12 — edit draft dan versi.
it('updates drafts with optimistic locking and rejects edits after submission', function () {
    $leave = p08aCreateStudent($this, [
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-10',
    ]);

    Sanctum::actingAs($this->operator);

    $this->putJson("/api/leaves/{$leave->id}", p08aStudentPayload($this->student->id, [
        'end_date' => '2026-11-11',
        'reason' => 'Updated synthetic medical leave reason.',
        'expected_version' => 1,
    ]))->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.end_date', '2026-11-11');

    $this->putJson("/api/leaves/{$leave->id}", p08aStudentPayload($this->student->id, [
        'end_date' => '2026-11-12',
        'reason' => 'Stale synthetic medical leave reason.',
        'expected_version' => 1,
    ]))->assertStatus(409);

    $leave = p08aSubmit($this, $leave->fresh());

    $this->putJson("/api/leaves/{$leave->id}", p08aStudentPayload($this->student->id, [
        'reason' => 'Edit after submit should fail.',
        'expected_version' => $leave->version,
    ]))->assertStatus(409);

    expect($leave->fresh()->status)->toBe(LeaveRequest::STATUS_SUBMITTED);
});

// T13 — submit valid dan validasi ulang.
it('submits valid drafts and re-validates stored eligibility', function () {
    $leave = p08aCreateStudent($this, [
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-12',
    ]);

    Sanctum::actingAs($this->operator);

    $this->postJson("/api/leaves/{$leave->id}/submit", [
        'expected_version' => 99,
    ])->assertStatus(409);

    $leave = p08aSubmit($this, $leave);

    expect($leave->status)->toBe(LeaveRequest::STATUS_SUBMITTED)
        ->and($leave->submitted_at)->not->toBeNull()
        ->and($leave->submitted_by)->toBe($this->operator->id)
        ->and($leave->version)->toBe(2);

    $this->postJson("/api/leaves/{$leave->id}/submit", [
        'expected_version' => $leave->version,
    ])->assertStatus(409);

    expect(LeaveRequest::where('status', LeaveRequest::STATUS_SUBMITTED)->count())->toBe(1);
});

// T14 — overlap.
it('blocks overlapping submitted or approved leaves but allows adjacent dates and drafts', function () {
    $submitted = p08aSubmit($this, p08aCreateStudent($this, [
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-12',
    ]));

    Sanctum::actingAs($this->operator);

    $overlap = p08aCreateStudent($this, [
        'start_date' => '2026-11-12',
        'end_date' => '2026-11-13',
    ]);
    $this->postJson("/api/leaves/{$overlap->id}/submit", ['expected_version' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['overlap']);

    $adjacent = p08aCreateStudent($this, [
        'start_date' => '2026-11-13',
        'end_date' => '2026-11-14',
    ]);
    $this->postJson("/api/leaves/{$adjacent->id}/submit", ['expected_version' => 1])
        ->assertOk();

    $draftOverlap = p08aCreateStudent($this, [
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-11',
    ]);
    expect($draftOverlap->status)->toBe(LeaveRequest::STATUS_DRAFT);

    $cancelled = p08aCreateStudent($this, [
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-21',
    ]);
    Sanctum::actingAs($this->operator);
    $this->postJson("/api/leaves/{$cancelled->id}/cancel", [
        'expected_version' => 1,
    ])->assertOk();

    $afterCancelled = p08aCreateStudent($this, [
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-21',
    ]);
    $this->postJson("/api/leaves/{$afterCancelled->id}/submit", ['expected_version' => 1])
        ->assertOk();

    expect($submitted->fresh()->status)->toBe(LeaveRequest::STATUS_SUBMITTED);
});

// T15 — submit kedua dengan versi basi.
it('prevents a second concurrent submit through version and status checks', function () {
    $leave = p08aCreateStudent($this, [
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-10',
    ]);

    Sanctum::actingAs($this->operator);

    $this->postJson("/api/leaves/{$leave->id}/submit", ['expected_version' => 1])->assertOk();
    $this->postJson("/api/leaves/{$leave->id}/submit", ['expected_version' => 1])->assertStatus(409);

    expect(LeaveRequest::where('status', LeaveRequest::STATUS_SUBMITTED)->count())->toBe(1);
});

// T16 — pembatalan.
it('cancels drafts and submitted leaves with the correct reason rules and permissions', function () {
    $draft = p08aCreateStudent($this);
    Sanctum::actingAs($this->operator);

    $this->postJson("/api/leaves/{$draft->id}/cancel", ['expected_version' => 1])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $submitted = p08aSubmit($this, p08aCreateStudent($this, [
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-21',
    ]));

    $this->postJson("/api/leaves/{$submitted->id}/cancel", [
        'expected_version' => $submitted->version,
    ])->assertStatus(422)->assertJsonValidationErrors(['reason']);

    $this->postJson("/api/leaves/{$submitted->id}/cancel", [
        'reason' => 'Synthetic cancellation reason.',
        'expected_version' => $submitted->version,
    ])->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $other = p08aCreateStudent($this, [
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-01',
    ]);

    Sanctum::actingAs($this->wakil);
    $this->postJson("/api/leaves/{$other->id}/cancel", [
        'reason' => 'Unauthorized cancellation attempt.',
        'expected_version' => 1,
    ])->assertStatus(403);

    expect(LeaveDecision::where('leave_request_id', $submitted->id)->where('action', 'cancelled')->count())->toBe(1);
});

// T21 — riwayat keputusan immutable.
it('keeps leave decision history immutable', function () {
    $leave = p08aCreateStudent($this);
    Sanctum::actingAs($this->operator);
    $this->postJson("/api/leaves/{$leave->id}/cancel", ['expected_version' => 1])->assertOk();

    $decision = LeaveDecision::where('leave_request_id', $leave->id)->firstOrFail();

    expect(fn () => $decision->update(['reason' => 'Changed']))
        ->toThrow(DomainException::class);

    expect(fn () => $decision->delete())
        ->toThrow(DomainException::class);
});

// T22/T23/T24 — lampiran.
it('validates, stores, downloads, and soft deletes private leave attachments', function () {
    $leave = p08aCreateStudent($this);
    Sanctum::actingAs($this->operator);

    $this->post('/api/leaves/'.$leave->id.'/attachments', [
        'file' => UploadedFile::fake()->createWithContent('fake.png', '%PDF-1.4 fake'),
    ], ['Accept' => 'application/json'])->assertStatus(422);

    $this->post('/api/leaves/'.$leave->id.'/attachments', [
        'file' => UploadedFile::fake()->create('large.pdf', 6000),
    ], ['Accept' => 'application/json'])->assertStatus(422);

    $response = $this->post('/api/leaves/'.$leave->id.'/attachments', [
        'file' => p08aPdf(),
    ], ['Accept' => 'application/json'])->assertOk();

    $attachment = LeaveAttachment::where('leave_request_id', $leave->id)->firstOrFail();
    expect($attachment->path)->toStartWith('attachments/leaves/'.$leave->id.'/')
        ->and(Storage::disk('local')->exists($attachment->path))->toBeTrue()
        ->and($attachment->checksum_sha256)->toHaveLength(64);

    $this->get("/api/leaves/{$leave->id}/attachments/{$attachment->id}/download")
        ->assertOk()
        ->assertHeader('X-Document-Filename')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeaderMissing('Content-Disposition');

    Sanctum::actingAs($this->principal);
    $this->getJson("/api/leaves/{$leave->id}")->assertOk();
    $this->get("/api/leaves/{$leave->id}/attachments/{$attachment->id}/download")->assertOk();

    Sanctum::actingAs($this->teacher);
    $this->getJson("/api/leaves/{$leave->id}")->assertStatus(404);
    $this->get("/api/leaves/{$leave->id}/attachments/{$attachment->id}/download")->assertStatus(404);

    Sanctum::actingAs($this->operator);
    $this->deleteJson("/api/leaves/{$leave->id}/attachments/{$attachment->id}")->assertOk();

    expect($attachment->fresh()->trashed())->toBeTrue()
        ->and(Storage::disk('local')->exists($attachment->path))->toBeTrue();

    $cancelled = p08aCreateStudent($this);
    Sanctum::actingAs($this->operator);
    $this->postJson("/api/leaves/{$cancelled->id}/cancel", ['expected_version' => 1])->assertOk();
    $this->post('/api/leaves/'.$cancelled->id.'/attachments', [
        'file' => p08aPdf(),
    ], ['Accept' => 'application/json'])->assertStatus(409);
});

it('limits attachment count to three per request', function () {
    $leave = p08aCreateStudent($this);
    Sanctum::actingAs($this->operator);

    foreach (['one.pdf', 'two.pdf', 'three.pdf'] as $name) {
        $this->post('/api/leaves/'.$leave->id.'/attachments', [
            'file' => p08aPdf($name),
        ], ['Accept' => 'application/json'])->assertOk();
    }

    $this->post('/api/leaves/'.$leave->id.'/attachments', [
        'file' => p08aPdf('four.pdf'),
    ], ['Accept' => 'application/json'])->assertStatus(422);

    expect(LeaveAttachment::where('leave_request_id', $leave->id)->count())->toBe(3);
});

// T25 — audit allowlist.
it('writes audit records without leave reasons, attachment names, paths, or hashes', function () {
    $leave = p08aCreateStudent($this, [
        'reason' => 'Secret synthetic medical reason.',
    ]);

    Sanctum::actingAs($this->operator);
    $this->post('/api/leaves/'.$leave->id.'/attachments', [
        'file' => p08aPdf('private-name.pdf'),
    ], ['Accept' => 'application/json'])->assertOk();

    $activity = Activity::where('table_name', 'leave_requests')
        ->where('record_id', $leave->id)
        ->where('information', 'Created')
        ->firstOrFail();

    $payload = json_encode($activity->new);

    expect($payload)->not->toContain('Secret synthetic medical reason.')
        ->and($payload)->not->toContain('private-name.pdf')
        ->and($payload)->not->toContain('attachments/leaves')
        ->and($payload)->toContain('"status":"draft"');

    $attachmentActivity = Activity::where('table_name', 'leave_attachments')->firstOrFail();
    $attachmentPayload = json_encode($attachmentActivity->new);

    expect($attachmentPayload)->not->toContain('private-name.pdf')
        ->and($attachmentPayload)->not->toContain('attachments/leaves')
        ->and($attachmentPayload)->not->toContain(hash('sha256', 'x'));
});

// T27 — tidak ada efek samping domain lain.
it('does not create attendance or balance side effects', function () {
    p08aSubmit($this, p08aCreateStudent($this));

    expect(LeaveRequest::count())->toBe(1)
        ->and(LeaveDecision::count())->toBe(0)
        ->and(LeaveAttachment::count())->toBe(0)
        ->and(Activity::where('table_name', 'attendance')->count())->toBe(0);
});

// T29 — route statis dan IDOR.
it('registers static leave routes before the id route and hides invisible objects', function () {
    Sanctum::actingAs($this->teacher);

    $this->getJson('/api/leaves/options')->assertOk();
    $this->getJson('/api/leaves/mine')->assertOk();
    $this->getJson('/api/leaves/subjects/students')->assertStatus(403);

    $leave = p08aCreateStudent($this, [
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-10',
    ]);

    Sanctum::actingAs($this->teacher);
    $this->getJson("/api/leaves/{$leave->id}")->assertStatus(404);
    $this->putJson("/api/leaves/{$leave->id}", p08aStudentPayload($this->student->id, [
        'reason' => 'Unauthorized synthetic edit attempt.',
        'expected_version' => 1,
    ]))->assertStatus(404);

    expect($leave->fresh()->reason)->toBe('Synthetic medical leave for the operator test.');
});
