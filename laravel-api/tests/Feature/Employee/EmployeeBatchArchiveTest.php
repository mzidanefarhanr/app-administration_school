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
    $this->employment = Employment::firstOrCreate(['name' => 'Tetap Yayasan'], ['description' => 'Tetap']);

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->plainUser = $this->makeUserWithRole();
});

function makeTestEmployee(array $overrides = []): Employee
{
    return Employee::create(array_merge([
        'name' => 'Pegawai Uji',
        'nik' => '3201010101010001',
        'employee_type_id' => test()->typeStaff->id,
        'employee_status_id' => test()->status->id,
        'employment_id' => test()->employment->id,
        'religion_id' => test()->religion->id,
        'district_id' => test()->district->id,
        'marital_status_id' => test()->maritalStatus->id,
        'gender_id' => test()->gender->id,
        'tmt_employee' => '2026-01-01',
        'version' => 1,
    ], $overrides));
}

test('batch archive requires employees.manage and authentication', function () {
    $this->postJson('/api/employees/batch-archive', [
        'items' => [['id' => 1, 'version' => 1]],
        'archive_reason' => 'Alasan valid pegawai',
    ])->assertStatus(401);

    Sanctum::actingAs($this->plainUser);
    $this->postJson('/api/employees/batch-archive', [
        'items' => [['id' => 1, 'version' => 1]],
        'archive_reason' => 'Alasan valid pegawai',
    ])->assertStatus(403);
});

test('batch archive successfully archives multiple employees', function () {
    Sanctum::actingAs($this->operator);

    $e1 = makeTestEmployee(['nik' => '3201010101010001', 'name' => 'Pegawai 1']);
    $e2 = makeTestEmployee(['nik' => '3201010101010002', 'name' => 'Pegawai 2']);

    $response = $this->postJson('/api/employees/batch-archive', [
        'items' => [
            ['id' => $e1->id, 'version' => 1],
            ['id' => $e2->id, 'version' => 1],
        ],
        'archive_reason' => 'Pensiun bersama tahun ajaran baru',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Successfully archived 2 employee(s).',
            'count' => 2,
        ]);

    $this->assertSoftDeleted('employees', ['id' => $e1->id]);
    $this->assertSoftDeleted('employees', ['id' => $e2->id]);

    $e1Fresh = Employee::withTrashed()->find($e1->id);
    expect($e1Fresh->version)->toBe(2);
    expect($e1Fresh->archive_reason)->toBe('Pensiun bersama tahun ajaran baru');
});

test('batch archive rolls back entirely when a version conflict occurs', function () {
    Sanctum::actingAs($this->operator);

    $e1 = makeTestEmployee(['nik' => '3201010101010001', 'name' => 'Pegawai 1', 'version' => 1]);
    $e2 = makeTestEmployee(['nik' => '3201010101010002', 'name' => 'Pegawai 2', 'version' => 4]);

    $response = $this->postJson('/api/employees/batch-archive', [
        'items' => [
            ['id' => $e1->id, 'version' => 1],
            ['id' => $e2->id, 'version' => 1], // mismatch
        ],
        'archive_reason' => 'Pensiun',
    ]);

    $response->assertStatus(409);

    $this->assertDatabaseHas('employees', ['id' => $e1->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('employees', ['id' => $e2->id, 'deleted_at' => null]);
});

test('batch restore successfully restores multiple archived employees', function () {
    Sanctum::actingAs($this->operator);

    $e1 = makeTestEmployee(['nik' => '3201010101010001', 'name' => 'Pegawai 1', 'version' => 2, 'archive_reason' => 'Arsip']);
    $e1->delete();

    $e2 = makeTestEmployee(['nik' => '3201010101010002', 'name' => 'Pegawai 2', 'version' => 3, 'archive_reason' => 'Arsip']);
    $e2->delete();

    $response = $this->postJson('/api/employees/batch-restore', [
        'items' => [
            ['id' => $e1->id, 'version' => 2],
            ['id' => $e2->id, 'version' => 3],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Successfully restored 2 employee(s).',
            'count' => 2,
        ]);

    $this->assertDatabaseHas('employees', ['id' => $e1->id, 'deleted_at' => null, 'version' => 3]);
    $this->assertDatabaseHas('employees', ['id' => $e2->id, 'deleted_at' => null, 'version' => 4]);
});
