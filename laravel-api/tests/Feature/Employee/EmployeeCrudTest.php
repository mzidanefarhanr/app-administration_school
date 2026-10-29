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

    $this->status = EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap Yayasan'], ['abbreviation' => 'PTY']);
    $this->typeStaff = EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
    $this->typeTeacher = EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $this->employment = Employment::firstOrCreate(['name' => 'Tetap Yayasan'], ['description' => 'Tetap']);
    $this->subject = Subject::firstOrCreate(['name' => 'Matematika'], ['description' => 'Matematika']);

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->plainUser = $this->makeUserWithRole();
});

function employeeCrudPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Pegawai Sintetis',
        'nik' => '3201010101010001',
        'employee_type_id' => test()->typeStaff->id,
        'employee_status_id' => test()->status->id,
        'employment_id' => test()->employment->id,
        'religion_id' => test()->religion->id,
        'district_id' => test()->district->id,
        'marital_status_id' => test()->maritalStatus->id,
        'gender_id' => test()->gender->id,
        'tmt_employee' => '2026-01-01',
    ], $overrides);
}

function makeEmployeeCrudRow(array $overrides = []): Employee
{
    return Employee::create(employeeCrudPayload($overrides));
}

test('guest cannot list employees', function () {
    $this->getJson('/api/employees')->assertStatus(401);
});

test('account without employees.view cannot list or show employees', function () {
    $employee = makeEmployeeCrudRow();

    Sanctum::actingAs($this->plainUser);

    $this->getJson('/api/employees')->assertStatus(403);
    $this->getJson('/api/employees/'.$employee->id)->assertStatus(403);
});

test('account without employees.manage cannot create, update, archive, or restore', function () {
    $employee = makeEmployeeCrudRow();
    Sanctum::actingAs($this->principal);

    $this->postJson('/api/employees', employeeCrudPayload())->assertStatus(403);
    $this->putJson('/api/employees/'.$employee->id, employeeCrudPayload(['version' => 1]))->assertStatus(403);
    $this->deleteJson('/api/employees/'.$employee->id, ['version' => 1, 'archive_reason' => 'x'])->assertStatus(403);
    $this->postJson('/api/employees/'.$employee->id.'/restore', ['version' => 1])->assertStatus(403);
});

test('employee list is paginated, filterable, searchable, and sorted by allowlist', function () {
    makeEmployeeCrudRow(['name' => 'Zed Teacher', 'employee_type_id' => $this->typeTeacher->id, 'subject_id' => $this->subject->id]);
    makeEmployeeCrudRow(['name' => 'Amy Staff', 'employee_type_id' => $this->typeStaff->id, 'nik' => '3201010101010002', 'nuptk' => '9988776655443322']);

    Sanctum::actingAs($this->operator);

    $this->getJson('/api/employees?per_page=10')
        ->assertStatus(200)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonCount(2, 'data');

    $this->getJson('/api/employees?per_page=7')->assertStatus(422);
    $this->getJson('/api/employees?sort=nik')->assertStatus(422);

    $this->getJson('/api/employees?search=998877&sort=name&direction=asc')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Amy Staff');

    $this->getJson('/api/employees?employee_type_id='.$this->typeTeacher->id)
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Zed Teacher');

    $this->getJson('/api/employees?sort=tmt_employee&direction=desc')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('employee list payload never contains sensitive personal fields', function () {
    makeEmployeeCrudRow([
        'nik' => '3201010101010003',
        'npwp' => '123456789012345',
        'no_kk' => '3201010101010004',
        'biological_mothers_name' => 'Ibu Sensitif',
        'partners_name' => 'Pasangan Sensitif',
        'bachelor_passing_grade' => 3.75,
    ]);

    Sanctum::actingAs($this->principal);

    $row = $this->getJson('/api/employees')->assertStatus(200)->json('data.0');

    expect($row)->not->toHaveKeys([
        'nik', 'npwp', 'no_kk', 'biological_mothers_name', 'partners_name', 'education',
    ])->and($row)->toHaveKeys(['id', 'version', 'name', 'employee_type', 'employee_status', 'account'])
        ->and($row['account'])->toHaveKeys(['linked', 'user_id']);
});

test('employee detail hides sensitive data from view-only and shows it to managers', function () {
    $employee = makeEmployeeCrudRow([
        'nik' => '3201010101010005',
        'npwp' => '123456789012345',
        'biological_mothers_name' => 'Ibu Sensitif',
    ]);

    Sanctum::actingAs($this->principal);
    $viewOnly = $this->getJson('/api/employees/'.$employee->id)->assertStatus(200)->json('data');
    expect($viewOnly)->not->toHaveKeys(['nik', 'npwp', 'biological_mothers_name', 'education']);

    Sanctum::actingAs($this->operator);
    $manager = $this->getJson('/api/employees/'.$employee->id)->assertStatus(200)->json('data');
    expect($manager)->toHaveKeys(['nik', 'npwp', 'biological_mothers_name', 'education'])
        ->and($manager['nik'])->toBe('3201010101010005');
});

test('missing employee detail returns a safe 404', function () {
    Sanctum::actingAs($this->operator);

    $this->getJson('/api/employees/999999')->assertStatus(404);
});

test('teaching staff requires a subject and non-teaching staff must not have one', function () {
    Sanctum::actingAs($this->operator);

    $this->postJson('/api/employees', employeeCrudPayload([
        'employee_type_id' => $this->typeTeacher->id,
        'subject_id' => null,
    ]))->assertStatus(422)->assertJsonValidationErrors('subject_id');

    $this->postJson('/api/employees', employeeCrudPayload([
        'employee_type_id' => $this->typeStaff->id,
        'subject_id' => $this->subject->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('subject_id');
});

test('create stores the profile with version 1 and ignores server-controlled fields', function () {
    Sanctum::actingAs($this->operator);
    $decoy = User::factory()->create();

    $response = $this->postJson('/api/employees', employeeCrudPayload([
        'name' => 'Pegawai Baru',
        'employee_type_id' => $this->typeTeacher->id,
        'subject_id' => $this->subject->id,
        'user_id' => $decoy->id,
        'version' => 99,
        'legacy_profile_at' => '2020-01-01 00:00:00',
    ]))->assertStatus(201);

    $response->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.name', 'Pegawai Baru')
        ->assertJsonPath('data.account.linked', false);

    $employee = Employee::where('name', 'Pegawai Baru')->firstOrFail();
    expect($employee->version)->toBe(1)
        ->and($employee->user_id)->toBeNull()
        ->and($employee->legacy_profile_at)->toBeNull();
});

test('create rejects a duplicate NIK even when the other row is archived', function () {
    $archived = makeEmployeeCrudRow(['nik' => '3201010101010006']);
    $archived->delete();

    Sanctum::actingAs($this->operator);

    $this->postJson('/api/employees', employeeCrudPayload(['nik' => '3201010101010006']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('nik');
});

test('update increments the version and rejects a stale version with a 409', function () {
    $employee = makeEmployeeCrudRow();

    Sanctum::actingAs($this->operator);

    $this->putJson('/api/employees/'.$employee->id, employeeCrudPayload([
        'name' => 'Pegawai Diperbarui',
        'version' => 1,
    ]))->assertStatus(200)
        ->assertJsonPath('data.name', 'Pegawai Diperbarui')
        ->assertJsonPath('data.version', 2);

    $this->putJson('/api/employees/'.$employee->id, employeeCrudPayload([
        'name' => 'Pegawai Bentrok',
        'version' => 1,
    ]))->assertStatus(409)
        ->assertJsonPath('current_version', 2);

    expect($employee->fresh()->name)->toBe('Pegawai Diperbarui')
        ->and($employee->fresh()->version)->toBe(2);
});

test('an archived employee cannot be updated', function () {
    $employee = makeEmployeeCrudRow();
    $employee->delete();

    Sanctum::actingAs($this->operator);

    $this->putJson('/api/employees/'.$employee->id, employeeCrudPayload(['version' => 1]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('employee');
});

test('archive requires a reason and preserves the exit date', function () {
    $employee = makeEmployeeCrudRow(['exit_date' => '2026-06-30']);

    Sanctum::actingAs($this->operator);

    $this->deleteJson('/api/employees/'.$employee->id, ['version' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('archive_reason');

    $this->deleteJson('/api/employees/'.$employee->id, [
        'version' => 1,
        'archive_reason' => 'Mengundurkan diri',
    ])->assertStatus(200);

    $archived = Employee::withTrashed()->findOrFail($employee->id);
    expect($archived->trashed())->toBeTrue()
        ->and($archived->archive_reason)->toBe('Mengundurkan diri')
        ->and((string) $archived->exit_date)->toBe('2026-06-30')
        ->and($archived->version)->toBe(2);
});

test('archive rejects a stale version with a 409', function () {
    $employee = makeEmployeeCrudRow();

    Sanctum::actingAs($this->operator);

    $this->deleteJson('/api/employees/'.$employee->id, [
        'version' => 9,
        'archive_reason' => 'Kesalahan data',
    ])->assertStatus(409)
        ->assertJsonPath('current_version', 1);

    expect($employee->fresh()->trashed())->toBeFalse();
});

test('archived employees are only listed for managers on request', function () {
    $active = makeEmployeeCrudRow(['name' => 'Pegawai Aktif', 'nik' => '3201010101010007']);
    $archived = makeEmployeeCrudRow(['name' => 'Pegawai Arsip', 'nik' => '3201010101010008']);
    $archived->delete();

    Sanctum::actingAs($this->operator);

    $default = $this->getJson('/api/employees')->assertStatus(200)->json('data');
    expect(collect($default)->pluck('id')->all())->toBe([$active->id]);

    $this->getJson('/api/employees?only_archived=1')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $archived->id)
        ->assertJsonPath('data.0.archived_at', fn ($value) => $value !== null);

    Sanctum::actingAs($this->principal);
    $this->getJson('/api/employees?only_archived=1')->assertStatus(403);
});

test('restore clears the archive reason and increments the version', function () {
    $employee = makeEmployeeCrudRow();
    $employee->archive_reason = 'ARSIP-2026';
    $employee->version = 2;
    $employee->save();
    $employee->delete();

    Sanctum::actingAs($this->operator);

    $this->postJson('/api/employees/'.$employee->id.'/restore', ['version' => 1])
        ->assertStatus(409)
        ->assertJsonPath('current_version', 2);

    $this->postJson('/api/employees/'.$employee->id.'/restore', ['version' => 2])
        ->assertStatus(200)
        ->assertJsonPath('data.version', 3)
        ->assertJsonPath('data.archived_at', null);

    $restored = Employee::findOrFail($employee->id);
    expect($restored->trashed())->toBeFalse()
        ->and($restored->archive_reason)->toBeNull()
        ->and($restored->version)->toBe(3);
});

test('employee audit records never contain name or NIK', function () {
    Sanctum::actingAs($this->operator);

    $employee = makeEmployeeCrudRow(['nik' => '3201010101010009', 'npwp' => '123456789012345']);

    $created = Activity::where('table_name', 'employees')
        ->where('record_id', $employee->id)
        ->where('information', 'Created')
        ->first();

    expect($created)->not->toBeNull()
        ->and($created->new)->toHaveKey('employee_status_id')
        ->and($created->new)->not->toHaveKeys(['name', 'nik', 'npwp']);

    $this->putJson('/api/employees/'.$employee->id, employeeCrudPayload([
        'name' => 'Nama Baru Sensitif',
        'nik' => '3201010101010010',
        'version' => 1,
    ]))->assertStatus(200);

    $updated = Activity::where('table_name', 'employees')
        ->where('record_id', $employee->id)
        ->where('information', 'Updated')
        ->latest('id')
        ->first();

    expect($updated)->not->toBeNull()
        ->and($updated->after)->toHaveKey('version')
        ->and($updated->after)->not->toHaveKeys(['name', 'nik'])
        ->and($updated->before)->not->toHaveKeys(['name', 'nik']);
});
