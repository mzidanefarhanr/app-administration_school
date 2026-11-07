<?php

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
});

function makeAccountEmployee(array $overrides = []): Employee
{
    return Employee::create(array_merge([
        'name' => 'Pegawai Akun',
        'nik' => '3201020202020001',
        'employee_type_id' => test()->typeStaff->id,
        'employee_status_id' => test()->status->id,
        'employment_id' => test()->employment->id,
        'religion_id' => test()->religion->id,
        'district_id' => test()->district->id,
        'marital_status_id' => test()->maritalStatus->id,
        'gender_id' => test()->gender->id,
        'tmt_employee' => '2026-01-01',
    ], $overrides));
}

test('guest cannot use account or export endpoints', function () {
    $employee = makeAccountEmployee();

    $this->getJson('/api/employees/account-candidates')->assertStatus(401);
    $this->postJson('/api/employees/'.$employee->id.'/link-account', ['user_id' => 1, 'version' => 1])->assertStatus(401);
    $this->postJson('/api/employees/'.$employee->id.'/unlink-account', ['version' => 1])->assertStatus(401);
    $this->getJson('/api/employees/export')->assertStatus(401);
});

test('account endpoints require employees.manage and export requires employees.export', function () {
    $employee = makeAccountEmployee();
    Sanctum::actingAs($this->principal);

    $this->getJson('/api/employees/account-candidates')->assertStatus(403);
    $this->postJson('/api/employees/'.$employee->id.'/link-account', ['user_id' => 1, 'version' => 1])->assertStatus(403);
    $this->postJson('/api/employees/'.$employee->id.'/unlink-account', ['version' => 1])->assertStatus(403);

    Sanctum::actingAs($this->makeUserWithRole('wakil-kesiswaan'));

    // wakil-kesiswaan has employees.view but not employees.export
    $this->getJson('/api/employees/export')->assertStatus(403);
});

test('account candidates exclude inactive, deleted, and already linked accounts', function () {
    $candidate = User::factory()->create(['name' => 'Kandidat Akun', 'status_active' => true]);
    $inactive = User::factory()->create(['name' => 'Akun Nonaktif', 'status_active' => false]);
    $deleted = User::factory()->create(['name' => 'Akun Terhapus']);
    $deleted->delete();

    $linkedAccount = User::factory()->create(['name' => 'Akun Tertaut']);
    makeAccountEmployee(['nik' => '3201020202020002', 'user_id' => $linkedAccount->id]);

    $archivedAccount = User::factory()->create(['name' => 'Akun Arsip']);
    $archivedEmployee = makeAccountEmployee(['nik' => '3201020202020003', 'user_id' => $archivedAccount->id]);
    $archivedEmployee->delete();

    Sanctum::actingAs($this->operator);

    $ids = collect($this->getJson('/api/employees/account-candidates')->assertStatus(200)->json('data'))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($candidate->id)
        ->and($ids)->not->toContain($inactive->id)
        ->and($ids)->not->toContain($deleted->id)
        ->and($ids)->not->toContain($linkedAccount->id)
        ->and($ids)->not->toContain($archivedAccount->id);

    $searched = $this->getJson('/api/employees/account-candidates?search='.$candidate->username)
        ->assertStatus(200)
        ->json('data');

    expect($searched)->toHaveCount(1)
        ->and($searched[0]['id'])->toBe($candidate->id)
        ->and($searched[0])->toHaveKeys(['id', 'name', 'username', 'email']);
});

test('link account stores the relation with optimistic concurrency', function () {
    $employee = makeAccountEmployee();
    $account = User::factory()->create();

    Sanctum::actingAs($this->operator);

    $this->postJson('/api/employees/'.$employee->id.'/link-account', [
        'user_id' => $account->id,
        'version' => 9,
    ])->assertStatus(409)
        ->assertJsonPath('current_version', 1);

    $this->postJson('/api/employees/'.$employee->id.'/link-account', [
        'user_id' => $account->id,
        'version' => 1,
    ])->assertStatus(200)
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.account.linked', true)
        ->assertJsonPath('data.account.user_id', $account->id);

    $linked = $employee->fresh();
    expect($linked->user_id)->toBe($account->id)
        ->and($linked->user_id_resolved_at)->not->toBeNull()
        ->and($linked->account_unlinked_at)->toBeNull();

    $this->postJson('/api/employees/'.$employee->id.'/link-account', [
        'user_id' => User::factory()->create()->id,
        'version' => 2,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('user_id');
});

test('link account rejects inactive accounts and accounts linked elsewhere', function () {
    $employee = makeAccountEmployee(['nik' => '3201020202020004']);
    $other = makeAccountEmployee(['nik' => '3201020202020005']);

    $inactive = User::factory()->create(['status_active' => false]);
    $taken = User::factory()->create();
    $other->user_id = $taken->id;
    $other->save();

    Sanctum::actingAs($this->operator);

    $this->postJson('/api/employees/'.$employee->id.'/link-account', [
        'user_id' => $inactive->id,
        'version' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('user_id');

    $this->postJson('/api/employees/'.$employee->id.'/link-account', [
        'user_id' => $taken->id,
        'version' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('user_id');

    expect($employee->fresh()->user_id)->toBeNull();
});

test('unlink account clears the relation and disables the legacy NIK fallback', function () {
    $account = User::factory()->create();
    $employee = makeAccountEmployee([
        'user_id' => $account->id,
        'user_id_resolved_at' => now(),
        'legacy_profile_at' => now(),
        'user_nik' => $account->nik,
        'version' => 2,
    ]);

    expect($employee->resolveUser()?->id)->toBe($account->id);

    Sanctum::actingAs($this->operator);

    $this->postJson('/api/employees/'.$employee->id.'/unlink-account', ['version' => 1])
        ->assertStatus(409)
        ->assertJsonPath('current_version', 2);

    $this->postJson('/api/employees/'.$employee->id.'/unlink-account', ['version' => 2])
        ->assertStatus(200)
        ->assertJsonPath('data.version', 3)
        ->assertJsonPath('data.account.linked', false)
        ->assertJsonPath('data.account.user_id', null);

    $unlinked = $employee->fresh();
    expect($unlinked->user_id)->toBeNull()
        ->and($unlinked->account_unlinked_at)->not->toBeNull()
        ->and($unlinked->resolveUser())->toBeNull();

    $this->postJson('/api/employees/'.$employee->id.'/unlink-account', ['version' => 3])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');
});

test('export streams explicit operational columns without PII or archived rows', function () {
    makeAccountEmployee([
        'name' => 'Pegawai Ekspor',
        'nik' => '3201020202020006',
        'npwp' => '987654321012345',
        'nuptk' => '1122334455667788',
        'exit_date' => '2026-07-31',
    ]);

    $archived = makeAccountEmployee(['name' => 'Pegawai Arsip', 'nik' => '3201020202020007']);
    $archived->delete();

    Sanctum::actingAs($this->principal);

    $response = $this->get('/api/employees/export')->assertStatus(200);

    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');

    $csv = $response->streamedContent();
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();

    $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
    $header = str_getcsv($lines[0]);

    expect($header)->toBe([
        'Name', 'Employee Type', 'Employee Status', 'Employment',
        'Subject', 'NUPTK', 'Gender', 'Employment Start Date (TMT)', 'Exit Date', 'Archived At',
    ]);

    $row = str_getcsv($lines[1]);
    expect($row)->toHaveCount(10)
        ->and($row[1])->toBe('Non-teaching Employee')
        ->and($row[2])->toBe('Permanent Foundation Employee')
        ->and($row[3])->toBe('Tetap Yayasan')
        ->and($row[4])->toBe('')
        ->and($row[6])->toBe('Male');

    expect($csv)->toContain('Pegawai Ekspor')
        ->and($csv)->toContain('1122334455667788')
        ->and($csv)->not->toContain('Pegawai Arsip')
        ->and($csv)->not->toContain('3201020202020006')
        ->and($csv)->not->toContain('987654321012345')
        ->and($csv)->not->toContain('NIK')
        ->and($csv)->not->toContain('NPWP');
});

test('export applies directory filters', function () {
    $employment = Employment::firstOrCreate(['name' => 'Guru Mata Pelajaran'], ['description' => 'None']);
    makeAccountEmployee([
        'name' => 'Guru Ekspor',
        'nik' => '3201020202020008',
        'employee_type_id' => $this->typeTeacher->id,
        'employment_id' => $employment->id,
        'subject_id' => $this->subject->id,
    ]);
    makeAccountEmployee(['name' => 'Staf Ekspor', 'nik' => '3201020202020009']);

    Sanctum::actingAs($this->principal);

    $csv = $this->get('/api/employees/export?employee_type_id='.$this->typeTeacher->id)
        ->assertStatus(200)
        ->streamedContent();

    $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
    $row = str_getcsv($lines[1]);
    expect($row)->toHaveCount(10)
        ->and($row[1])->toBe('Teacher')
        ->and($row[3])->toBe('Subject Teacher')
        ->and($row[4])->toBe('Mathematics');

    expect($csv)->toContain('Guru Ekspor')
        ->and($csv)->not->toContain('Staf Ekspor');

    $this->getJson('/api/employees/export?employee_type_id=999999')->assertStatus(422);
});

test('export rejects a result set above the configured limit', function () {
    config(['employees.export_max_rows' => 1]);

    makeAccountEmployee(['nik' => '3201020202020010']);
    makeAccountEmployee(['name' => 'Pegawai Kedua', 'nik' => '3201020202020011']);

    Sanctum::actingAs($this->principal);

    $this->getJson('/api/employees/export')
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'limit of 1'));
});

test('export neutralises spreadsheet formula cells', function () {
    makeAccountEmployee(['name' => '=SUM(1,2)', 'nik' => '3201020202020012']);

    Sanctum::actingAs($this->principal);

    $csv = $this->get('/api/employees/export')->assertStatus(200)->streamedContent();

    expect($csv)->toContain("'=SUM(1,2)")
        ->and($csv)->not->toContain(',=SUM(1,2)');
});

test('generateAccount creates a new User with proper role, unique username, and links it to employee', function () {
    $employee = makeAccountEmployee([
        'name' => 'Budi Santoso',
        'nik' => '3201020202020088',
        'employee_type_id' => $this->typeTeacher->id,
    ]);

    Sanctum::actingAs($this->operator);

    $response = $this->postJson('/api/employees/'.$employee->id.'/generate-account')
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Budi Santoso');

    $generatedUserId = $response->json('data.user_id');
    $initialPassword = $response->json('data.initial_password');

    expect($initialPassword)->not->toBeEmpty();
    expect(strlen($initialPassword))->toBeGreaterThanOrEqual(12);

    $linked = $employee->fresh();
    expect($linked->user_id)->toBe($generatedUserId)
        ->and($linked->user_id_resolved_at)->not->toBeNull();

    $user = User::find($generatedUserId);
    expect($user)->not->toBeNull()
        ->and($user->hasRole('guru'))->toBeTrue();

    // Re-generate on same employee must fail
    $this->postJson('/api/employees/'.$employee->id.'/generate-account')
        ->assertStatus(422);
});

test('deleteAccount unlinks and deletes user for non-admin accounts', function () {
    $employee = makeAccountEmployee(['nik' => '3201020202020089']);
    $user = User::factory()->create(['type_user_id' => 2]);
    $employee->user_id = $user->id;
    $employee->save();

    Sanctum::actingAs($this->operator);

    $this->deleteJson('/api/employees/'.$employee->id.'/delete-account')
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $linked = $employee->fresh();
    expect($linked->user_id)->toBeNull()
        ->and($linked->account_unlinked_at)->not->toBeNull();

    expect(User::find($user->id))->toBeNull();
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

test('deleteAccount refuses to delete administrator account', function () {
    $employee = makeAccountEmployee(['nik' => '3201020202020090']);
    $adminUser = $this->makeUserWithRole('admin-sistem');
    $employee->user_id = $adminUser->id;
    $employee->save();

    Sanctum::actingAs($this->operator);

    $this->deleteJson('/api/employees/'.$employee->id.'/delete-account')
        ->assertStatus(403);

    $linked = $employee->fresh();
    expect($linked->user_id)->toBe($adminUser->id);
    expect(User::find($adminUser->id))->not->toBeNull();
});

test('self profile endpoint /api/user loads employee relation', function () {
    $employee = makeAccountEmployee(['nik' => '3201020202020091']);
    $user = User::factory()->create(['type_user_id' => 2]);
    $employee->user_id = $user->id;
    $employee->save();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/user')
        ->assertStatus(200);

    expect($response->json('employee.id'))->toBe($employee->id);
});
