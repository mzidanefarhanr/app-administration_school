<?php

use App\Models\Activity;
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
use App\Models\User;
use App\Services\EmployeeBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    $this->seedAccessReferenceData();

    $province = Province::firstOrCreate(['name' => 'DKI Jakarta']);
    $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Utara']);
    $this->district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Koja']);

    $this->gender = Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $this->religion = Religion::firstOrCreate(['name' => 'Islam']);
    $this->maritalStatus = MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);

    $this->statusPty = EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap Yayasan'], ['abbreviation' => 'PTY']);
    $this->typeNonTeacher = EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
    $this->typeTeacher = EmployeeType::firstOrCreate(['name' => 'Pendidik']);

    $this->employment = Employment::firstOrCreate(['name' => 'Tetap Yayasan'], ['description' => 'Tetap']);
    $this->subjectMath = Subject::firstOrCreate(['name' => 'Matematika'], ['description' => 'Pelajaran Matematika']);

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->plainUser = $this->makeUserWithRole(); // no roles
});

test('can create employee profile without user_id and without user_nik (profile independent, DEC-04 #1 & #2)', function () {
    $employee = Employee::create([
        'user_id' => null,
        'user_nik' => null,
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'subject_id' => null,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    expect($employee)->not->toBeNull()
        ->and($employee->id)->toBeGreaterThan(0)
        ->and($employee->user_id)->toBeNull()
        ->and($employee->user_nik)->toBeNull()
        ->and($employee->version)->toBe(1);

    expect($employee->user)->toBeNull();
    expect($employee->resolveUser())->toBeNull();
});

test('non-teaching employee (Tenaga Kependidikan) can be created with null subject_id (DEC-04 #4)', function () {
    $staff = Employee::create([
        'user_id' => null,
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'subject_id' => null, // Non-teaching staff has no subject
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    expect($staff->subject_id)->toBeNull();
    expect($staff->subject)->toBeNull();
});

test('version column defaults to 1 on creation (DEC-04 #5)', function () {
    $emp = Employee::create([
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeTeacher->id,
        'employment_id' => $this->employment->id,
        'subject_id' => $this->subjectMath->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    expect($emp->version)->toBe(1);
});

test('multiple employees can have null user_id simultaneously without violating unique constraint (DEC-04 #3)', function () {
    $emp1 = Employee::create([
        'user_id' => null,
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    $emp2 = Employee::create([
        'user_id' => null,
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    expect($emp1->id)->not->toBe($emp2->id);
    expect($emp1->user_id)->toBeNull();
    expect($emp2->user_id)->toBeNull();
});

test('one user account cannot be linked to more than one active employee (DEC-04 #3)', function () {
    $user = User::factory()->create(['nik' => '3201010101010091']);

    Employee::create([
        'user_id' => $user->id,
        'user_nik' => $user->nik,
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeTeacher->id,
        'employment_id' => $this->employment->id,
        'subject_id' => $this->subjectMath->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    // Second employee trying to link same user_id must throw QueryException (unique constraint)
    expect(function () use ($user) {
        Employee::create([
            'user_id' => $user->id,
            'user_nik' => $user->nik,
            'employee_status_id' => $this->statusPty->id,
            'employee_type_id' => $this->typeTeacher->id,
            'employment_id' => $this->employment->id,
            'subject_id' => $this->subjectMath->id,
            'religion_id' => $this->religion->id,
            'district_id' => $this->district->id,
            'marital_status_id' => $this->maritalStatus->id,
            'gender_id' => $this->gender->id,
            'tmt_employee' => '2026-01-01',
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

test('unconditional unique user_id constraint also blocks linking same user to another employee even if first is soft-deleted (DEC-04 #3)', function () {
    $user = User::factory()->create(['nik' => '3201010101010092']);

    $emp1 = Employee::create([
        'user_id' => $user->id,
        'user_nik' => $user->nik,
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeTeacher->id,
        'employment_id' => $this->employment->id,
        'subject_id' => $this->subjectMath->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    $emp1->delete(); // soft delete
    expect($emp1->trashed())->toBeTrue();

    // Reusing the same user_id must be rejected unconditionally by the database unique constraint
    expect(function () use ($user) {
        Employee::create([
            'user_id' => $user->id,
            'user_nik' => $user->nik,
            'employee_status_id' => $this->statusPty->id,
            'employee_type_id' => $this->typeTeacher->id,
            'employment_id' => $this->employment->id,
            'subject_id' => $this->subjectMath->id,
            'religion_id' => $this->religion->id,
            'district_id' => $this->district->id,
            'marital_status_id' => $this->maritalStatus->id,
            'gender_id' => $this->gender->id,
            'tmt_employee' => '2026-01-01',
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

test('primary user_id relationship is never overridden by different legacy user_nik string', function () {
    $userA = User::factory()->create(['nik' => '3201010101010011', 'name' => 'User A Primary']);
    $userB = User::factory()->create(['nik' => '3201010101010012', 'name' => 'User B Decoy']);

    $employee = Employee::create([
        'user_id' => $userA->id,
        'user_nik' => $userB->nik, // mismatched decoy legacy string
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeTeacher->id,
        'employment_id' => $this->employment->id,
        'subject_id' => $this->subjectMath->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    expect($employee->user->id)->toBe($userA->id);
    expect($employee->resolveUser()->id)->toBe($userA->id);
    expect($employee->resolveUser()->name)->toBe('User A Primary');
});

test('resolveUser falls back to unique user_nik only for unmigrated legacy rows', function () {
    $user = User::factory()->create(['nik' => '3201010101010022', 'name' => 'Legacy Matched User']);

    // Pure legacy unmigrated row: user_id is null, markers are null
    $legacyEmp = Employee::create([
        'user_id' => null,
        'user_id_resolved_at' => null,
        'account_unlinked_at' => null,
        'user_nik' => $user->nik,
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeTeacher->id,
        'employment_id' => $this->employment->id,
        'subject_id' => $this->subjectMath->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    expect($legacyEmp->user_id)->toBeNull();
    expect($legacyEmp->user)->toBeNull(); // standard relationship is null

    // resolveUser performs safe fallback only for legacy
    $resolved = $legacyEmp->resolveUser();
    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($user->id)
        ->and($resolved->name)->toBe('Legacy Matched User');
});

test('deliberate account detachment or hard delete does not trigger legacy NIK fallback', function () {
    $user = User::factory()->create(['nik' => '3201010101010033']);

    // Case 1: Deliberately unlinked employee
    $empDetached = Employee::create([
        'user_id' => null,
        'user_nik' => $user->nik,
        'user_id_resolved_at' => now(),
        'account_unlinked_at' => now(), // explicitly unlinked
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    expect($empDetached->resolveUser())->toBeNull('Must not reconnect via user_nik when account was unlinked');

    // Case 2: Employee was resolved under phase 04a, but user_id became null
    $empResolvedNull = Employee::create([
        'user_id' => null,
        'user_nik' => $user->nik,
        'user_id_resolved_at' => now(), // already resolved
        'account_unlinked_at' => null,
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    expect($empResolvedNull->resolveUser())->toBeNull('Must not reconnect via user_nik when row was already resolved');
});

test('soft delete of user account preserves historical relationship on employee without unlinking', function () {
    $user = User::factory()->create(['nik' => '3201010101010044']);

    $employee = Employee::create([
        'user_id' => $user->id,
        'user_nik' => $user->nik,
        'user_id_resolved_at' => now(),
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeTeacher->id,
        'employment_id' => $this->employment->id,
        'subject_id' => $this->subjectMath->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    $user->delete(); // soft delete
    expect($user->trashed())->toBeTrue();

    $employee->refresh();
    expect($employee->user_id)->toBe($user->id, 'Foreign key is preserved across soft delete');
    expect($employee->user)->not->toBeNull();
    expect($employee->user->trashed())->toBeTrue();
    expect($employee->resolveUser()->trashed())->toBeTrue();
});

test('model religion method works and legacy relogion alias remains compatible', function () {
    $employee = Employee::create([
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    expect($employee->religion)->not->toBeNull()
        ->and($employee->religion->id)->toBe($this->religion->id);

    expect($employee->relogion)->not->toBeNull()
        ->and($employee->relogion->id)->toBe($this->religion->id);
});

test('employee backfill resolves matching 16-digit NIK uniquely and is idempotent (DEC-04 #2)', function () {
    $user1 = User::factory()->create(['nik' => '1000000000000001']);
    $user2 = User::factory()->create(['nik' => '1000000000000002']);

    $emp1 = Employee::create([
        'user_id' => null,
        'user_nik' => '1000000000000001',
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeTeacher->id,
        'employment_id' => $this->employment->id,
        'subject_id' => $this->subjectMath->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    $emp2 = Employee::create([
        'user_id' => null,
        'user_nik' => '1000000000000002',
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    $service = new EmployeeBackfillService;

    // 1. Dry run
    $dryStats = $service->backfill(true);
    expect($dryStats['matched'])->toBe(2);
    expect($emp1->fresh()->user_id)->toBeNull();

    // 2. Real run
    $stats = $service->backfill(false);
    expect($stats['matched'])->toBe(2);
    expect($stats['already_resolved'])->toBe(0);

    $emp1->refresh();
    $emp2->refresh();
    expect($emp1->user_id)->toBe($user1->id);
    expect($emp1->user_id_resolved_at)->not->toBeNull();
    expect($emp2->user_id)->toBe($user2->id);

    // 3. Second run is strictly idempotent
    $idemStats = $service->backfill(false);
    expect($idemStats['matched'])->toBe(0);
    expect($idemStats['already_resolved'])->toBe(2);
});

test('employee backfill preserves existing user_id and leaves invalid/unmatched/ambiguous NIK unresolved', function () {
    $existingUser = User::factory()->create(['nik' => '2000000000000001']);
    $alreadyLinkedUser = User::factory()->create(['nik' => '2000000000000002']);

    // 1. Pre-resolved employee
    $preResolved = Employee::create([
        'user_id' => $alreadyLinkedUser->id,
        'user_nik' => $alreadyLinkedUser->nik,
        'user_id_resolved_at' => now(),
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    // 2. Employee with empty NIK
    $empEmpty = Employee::create([
        'user_id' => null,
        'user_nik' => null,
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    // 3. Employee with invalid NIK format (too short, not 16 digits)
    $userInvalid = User::factory()->create(['nik' => '12345']);
    $empInvalid = Employee::create([
        'user_id' => null,
        'user_nik' => '12345',
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    // 4. Employee with unmatched NIK (orphaned legacy string)
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = OFF');
    }
    $empUnmatched = Employee::create([
        'user_id' => null,
        'user_nik' => '9999999999999999',
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = ON');
    }

    // 5. Employee with NIK matching user that is already linked to $preResolved
    $empReused = Employee::create([
        'user_id' => null,
        'user_nik' => $alreadyLinkedUser->nik,
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    $service = new EmployeeBackfillService;
    $stats = $service->backfill(false);

    expect($stats['already_resolved'])->toBe(1);
    expect($stats['unresolved_empty'])->toBe(1);
    expect($stats['unresolved_invalid_format'])->toBe(1);
    expect($stats['unresolved_unmatched'])->toBe(1);
    expect($stats['unresolved_account_already_linked'])->toBe(1);
    expect($stats['matched'])->toBe(0);

    expect($preResolved->fresh()->user_id)->toBe($alreadyLinkedUser->id);
    expect($empEmpty->fresh()->user_id)->toBeNull();
    expect($empInvalid->fresh()->user_id)->toBeNull();
    expect($empUnmatched->fresh()->user_id)->toBeNull();
    expect($empReused->fresh()->user_id)->toBeNull();
});

test('employee audit log strictly enforces allowlist and excludes sensitive PII (DEC-04 #6)', function () {
    Sanctum::actingAs($this->operator);

    $emp = Employee::create([
        'user_id' => null,
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeTeacher->id,
        'employment_id' => $this->employment->id,
        'subject_id' => $this->subjectMath->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'nuptk' => '1234567890123456',
        'npwp' => '098765432109876', // sensitive PII
        'no_kk' => '3201010101010099', // sensitive PII
        'biological_mothers_name' => 'Secret Mother Name', // sensitive PII
        'partners_name' => 'Secret Partner Name', // sensitive PII
        'bachelor_passing_grade' => 3.95, // sensitive PII
        'tmt_employee' => '2026-01-01',
        'version' => 1,
    ]);

    $createAudit = Activity::where('table_name', 'employees')
        ->where('record_id', $emp->id)
        ->where('information', 'Created')
        ->first();

    expect($createAudit)->not->toBeNull();
    $newPayload = $createAudit->new;

    // Auditable allowed fields are recorded
    expect($newPayload)->toHaveKey('employee_status_id')
        ->and($newPayload)->toHaveKey('employee_type_id')
        ->and($newPayload)->toHaveKey('nuptk')
        ->and($newPayload)->toHaveKey('version');

    // Sensitive fields are strictly NOT recorded in audit json
    expect($newPayload)->not->toHaveKey('biological_mothers_name')
        ->and($newPayload)->not->toHaveKey('partners_name')
        ->and($newPayload)->not->toHaveKey('no_kk')
        ->and($newPayload)->not->toHaveKey('npwp')
        ->and($newPayload)->not->toHaveKey('bachelor_passing_grade');

    // Test update
    $emp->update([
        'biological_mothers_name' => 'Changed Mother Name',
        'nuptk' => '9999888877776666',
        'version' => 2,
    ]);

    $updateAudit = Activity::where('table_name', 'employees')
        ->where('record_id', $emp->id)
        ->where('information', 'Updated')
        ->latest('id')
        ->first();

    expect($updateAudit)->not->toBeNull();
    expect($updateAudit->after)->toHaveKey('nuptk')
        ->and($updateAudit->after['nuptk'])->toBe('9999888877776666')
        ->and($updateAudit->after)->toHaveKey('version')
        ->and($updateAudit->after['version'])->toBe(2);

    expect($updateAudit->after)->not->toHaveKey('biological_mothers_name')
        ->and($updateAudit->before)->not->toHaveKey('biological_mothers_name');
});

test('employee policy authorizes employees.view for viewing and employees.manage for mutations', function () {
    $emp = Employee::create([
        'employee_status_id' => $this->statusPty->id,
        'employee_type_id' => $this->typeNonTeacher->id,
        'employment_id' => $this->employment->id,
        'religion_id' => $this->religion->id,
        'district_id' => $this->district->id,
        'marital_status_id' => $this->maritalStatus->id,
        'gender_id' => $this->gender->id,
        'tmt_employee' => '2026-01-01',
    ]);

    // Operator has employees.view + employees.manage
    expect($this->operator->can('viewAny', Employee::class))->toBeTrue();
    expect($this->operator->can('view', $emp))->toBeTrue();
    expect($this->operator->can('create', Employee::class))->toBeTrue();
    expect($this->operator->can('update', $emp))->toBeTrue();
    expect($this->operator->can('delete', $emp))->toBeTrue();
    expect($this->operator->can('restore', $emp))->toBeTrue();
    expect($this->operator->can('forceDelete', $emp))->toBeFalse('Force delete must be forbidden');

    // Principal has employees.view only (view-only)
    expect($this->principal->can('viewAny', Employee::class))->toBeTrue();
    expect($this->principal->can('view', $emp))->toBeTrue();
    expect($this->principal->can('create', Employee::class))->toBeFalse();
    expect($this->principal->can('update', $emp))->toBeFalse();
    expect($this->principal->can('delete', $emp))->toBeFalse();
    expect($this->principal->can('restore', $emp))->toBeFalse();

    // Plain user without roles
    expect($this->plainUser->can('viewAny', Employee::class))->toBeFalse();
    expect($this->plainUser->can('view', $emp))->toBeFalse();
    expect($this->plainUser->can('create', Employee::class))->toBeFalse();
    expect($this->plainUser->can('update', $emp))->toBeFalse();
});
