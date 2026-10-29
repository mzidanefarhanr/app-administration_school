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
use App\Services\EmployeeNameBackfillService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
});

function createProfileFieldEmployee(array $overrides = []): Employee
{
    return Employee::create(array_merge([
        'employee_status_id' => test()->status->id,
        'employee_type_id' => test()->typeStaff->id,
        'employment_id' => test()->employment->id,
        'subject_id' => null,
        'religion_id' => test()->religion->id,
        'district_id' => test()->district->id,
        'marital_status_id' => test()->maritalStatus->id,
        'gender_id' => test()->gender->id,
        'tmt_employee' => '2026-01-01',
    ], $overrides));
}

test('profile fields exist with the expected indexes (DEC-24 D1/D2/D7)', function () {
    expect(Schema::hasColumn('employees', 'name'))->toBeTrue();
    expect(Schema::hasColumn('employees', 'nik'))->toBeTrue();
    expect(Schema::hasColumn('employees', 'archive_reason'))->toBeTrue();

    $indexes = collect(DB::select("PRAGMA index_list('employees')"))->pluck('name');

    expect($indexes)->toContain('employees_nik_unique')
        ->and($indexes)->toContain('employees_name_index')
        ->and($indexes)->toContain('employees_employee_type_id_index')
        ->and($indexes)->toContain('employees_employee_status_id_index');
});

test('name and nik are nullable so unresolved legacy rows stay representable', function () {
    $employee = createProfileFieldEmployee();

    expect($employee->name)->toBeNull()
        ->and($employee->nik)->toBeNull()
        ->and($employee->archive_reason)->toBeNull();
});

test('nik uniqueness is unconditional, including archived rows', function () {
    $nik = '3201010101010001';

    $first = createProfileFieldEmployee(['name' => 'First', 'nik' => $nik]);
    $first->delete();

    expect(fn () => createProfileFieldEmployee(['name' => 'Second', 'nik' => $nik]))
        ->toThrow(QueryException::class);
});

test('name backfill copies the linked account name and is idempotent', function () {
    $user = User::factory()->create(['name' => 'Nama Akun Sintetis']);
    $employee = createProfileFieldEmployee(['user_id' => $user->id]);

    $service = app(EmployeeNameBackfillService::class);

    $first = $service->backfill();
    expect($first['filled_from_user_id'])->toBe(1)
        ->and($employee->fresh()->name)->toBe('Nama Akun Sintetis');

    $second = $service->backfill();
    expect($second['filled_from_user_id'])->toBe(0)
        ->and($second['already_named'])->toBeGreaterThanOrEqual(1);
});

test('name backfill dry-run reports counts without writing or leaking names', function () {
    $user = User::factory()->create(['name' => 'Nama Akun Sensitif']);
    $employee = createProfileFieldEmployee(['user_id' => $user->id]);

    $this->artisan('app:employee-name-backfill', ['--dry-run' => true])
        ->expectsOutputToContain('Filled From Linked Account')
        ->doesntExpectOutputToContain('Nama Akun Sensitif')
        ->doesntExpectOutputToContain($user->email)
        ->assertExitCode(0)
        ->run();

    expect($employee->fresh()->name)->toBeNull();
});

test('name backfill resolves unique legacy NIK only for eligible pre-04a rows', function () {
    $user = User::factory()->create(['name' => 'Nama Legacy']);

    $eligible = createProfileFieldEmployee([
        'user_nik' => $user->nik,
        'legacy_profile_at' => now(),
    ]);
    $unlinked = createProfileFieldEmployee([
        'user_nik' => $user->nik,
        'legacy_profile_at' => now(),
        'account_unlinked_at' => now(),
    ]);

    $stats = app(EmployeeNameBackfillService::class)->backfill();

    expect($stats['filled_from_legacy_nik'])->toBe(1)
        ->and($eligible->fresh()->name)->toBe('Nama Legacy')
        ->and($unlinked->fresh()->name)->toBeNull();
});

test('name backfill leaves rows without a resolvable account untouched', function () {
    $employee = createProfileFieldEmployee(['user_nik' => null]);

    $stats = app(EmployeeNameBackfillService::class)->backfill();

    expect($stats['unresolved_no_account'])->toBe(1)
        ->and($employee->fresh()->name)->toBeNull();
});

test('04b rollback fails closed while new-column data cannot be rebuilt', function () {
    createProfileFieldEmployee(['name' => 'Profil Tanpa Akun', 'nik' => '3201010101010099']);

    $migration = require database_path('migrations/2029_07_26_000001_add_profile_fields_to_employees.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    expect(Schema::hasColumn('employees', 'name'))->toBeTrue()
        ->and(Schema::hasColumn('employees', 'nik'))->toBeTrue()
        ->and(Schema::hasColumn('employees', 'archive_reason'))->toBeTrue();
});
