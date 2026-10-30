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
use App\Support\EmployeeExportColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

/**
 * Fase 05b (DEC-26 D1/D2): pilihan kolom ekspor pegawai.
 *
 * Default tetap 10 kolom operasional 04b. Kolom PII hanya untuk pemegang
 * `employees.manage`; permintaan kolom PII tanpa izin ditolak 403.
 */
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
    $this->vicePrincipal = $this->makeUserWithRole('wakil-kesiswaan');
});

function e05bExportEmployee(array $overrides = []): Employee
{
    return Employee::create(array_merge([
        'name' => 'Pegawai Ekspor',
        'nik' => '3201020202020001',
        'nuptk' => '1122334455667788',
        'npwp' => '987654321012345',
        'no_kk' => '3201020202020002',
        'place_of_birth' => 'Jakarta',
        'date_of_birth' => '1990-01-05',
        'address' => 'Synthetic Street 1',
        'rt' => '001',
        'rw' => '002',
        'zip_code' => '12345',
        'wa_number' => '081200000001',
        'email' => 'synthetic.employee@example.test',
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

/**
 * @return array{0: array<int, string>, 1: array<int, string>}
 */
function e05bExportCsv($response): array
{
    $csv = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));

    return [str_getcsv($lines[0]), array_map('str_getcsv', array_slice($lines, 1))];
}

// ─────────────────────────────────────────────────────────────────────────────
// Opsi kolom
// ─────────────────────────────────────────────────────────────────────────────

test('guest cannot read export options', function () {
    $this->getJson('/api/employees/export/options')->assertStatus(401);
});

test('export options hide sensitive columns from users without employees.manage', function () {
    Sanctum::actingAs($this->principal);

    $response = $this->getJson('/api/employees/export/options')->assertStatus(200);

    $response->assertJsonPath('data.default', EmployeeExportColumns::defaultKeys())
        ->assertJsonPath('data.sensitive_allowed', false)
        ->assertJsonCount(10, 'data.columns');

    $keys = collect($response->json('data.columns'))->pluck('key')->all();
    expect($keys)->toBe(EmployeeExportColumns::defaultKeys())
        ->and($keys)->not->toContain('nik')
        ->and(collect($response->json('data.columns'))->every(fn ($column) => $column['sensitive'] === false))->toBeTrue();
});

test('export options expose all columns to managers', function () {
    Sanctum::actingAs($this->operator);

    $response = $this->getJson('/api/employees/export/options')->assertStatus(200);

    $response->assertJsonPath('data.sensitive_allowed', true)
        ->assertJsonCount(25, 'data.columns');

    $keys = collect($response->json('data.columns'))->pluck('key')->all();
    expect($keys)->toBe(EmployeeExportColumns::allKeys())
        ->and($keys)->toContain('nik')
        ->and($keys)->toContain('regency')
        ->and($keys)->toContain('marital_status');
});

test('export options require employees.export', function () {
    Sanctum::actingAs($this->vicePrincipal);

    $this->getJson('/api/employees/export/options')->assertStatus(403);
});

// ─────────────────────────────────────────────────────────────────────────────
// Default dan subset
// ─────────────────────────────────────────────────────────────────────────────

test('default export remains the 10 operational columns', function () {
    e05bExportEmployee();

    Sanctum::actingAs($this->principal);

    $response = $this->get('/api/employees/export')->assertStatus(200);
    [$header, $rows] = e05bExportCsv($response);

    expect($header)->toBe(EmployeeExportColumns::headers(EmployeeExportColumns::defaultKeys()))
        ->and($header)->toHaveCount(10)
        ->and($rows)->toHaveCount(1)
        ->and($rows[0])->not->toContain('3201020202020001')
        ->and($rows[0])->not->toContain('987654321012345')
        ->and($rows[0])->not->toContain('synthetic.employee@example.test');
});

test('a requested subset keeps registry order and exports only those columns', function () {
    e05bExportEmployee();

    Sanctum::actingAs($this->principal);

    $response = $this->get('/api/employees/export?columns=nuptk,name')->assertStatus(200);
    [$header, $rows] = e05bExportCsv($response);

    expect($header)->toBe(['Name', 'NUPTK'])
        ->and($rows[0])->toBe(['Pegawai Ekspor', '1122334455667788']);

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    expect(str_starts_with($response->streamedContent(), "\xEF\xBB\xBF"))->toBeTrue();
});

test('an empty columns parameter falls back to the default set', function () {
    e05bExportEmployee();

    Sanctum::actingAs($this->principal);

    [$header] = e05bExportCsv($this->get('/api/employees/export?columns=')->assertStatus(200));

    expect($header)->toBe(EmployeeExportColumns::headers(EmployeeExportColumns::defaultKeys()));
});

test('unknown columns are rejected with a validation error', function () {
    Sanctum::actingAs($this->principal);

    $this->getJson('/api/employees/export?columns=name,password')
        ->assertStatus(422)
        ->assertJsonValidationErrors('columns');

    expect($this->getJson('/api/employees/export?columns=name,password')->json('errors.columns.0'))
        ->toContain('Unknown export column(s): password.');
});

// ─────────────────────────────────────────────────────────────────────────────
// Gating PII
// ─────────────────────────────────────────────────────────────────────────────

test('sensitive columns are forbidden without employees.manage', function () {
    e05bExportEmployee();

    Sanctum::actingAs($this->principal);

    $this->getJson('/api/employees/export?columns=name,nik')->assertStatus(403);
    $this->getJson('/api/employees/export?columns=nik')->assertStatus(403);
    $this->getJson('/api/employees/export?columns=address')->assertStatus(403);
});

test('managers can export sensitive columns with registry order', function () {
    e05bExportEmployee();

    Sanctum::actingAs($this->operator);

    $response = $this->get('/api/employees/export?columns=email,district,nik,regency,address')->assertStatus(200);
    [$header, $rows] = e05bExportCsv($response);

    expect($header)->toBe(['NIK', 'Address', 'Email', 'Regency', 'District'])
        ->and($rows[0])->toBe([
            '3201020202020001',
            'Synthetic Street 1',
            'synthetic.employee@example.test',
            'Jakarta Utara',
            'Koja',
        ]);
});

test('sensitive export includes reference labels and personal fields', function () {
    e05bExportEmployee();

    Sanctum::actingAs($this->operator);

    [$header, $rows] = e05bExportCsv(
        $this->get('/api/employees/export?columns=nik,npwp,no_kk,place_of_birth,date_of_birth,rt,rw,zip_code,wa_number,religion,marital_status')
            ->assertStatus(200)
    );

    expect($header)->toBe([
        'NIK', 'NPWP', 'Family Card Number', 'Place of Birth', 'Date of Birth',
        'RT', 'RW', 'Zip Code', 'Phone', 'Religion', 'Marital Status',
    ])->and($rows[0][0])->toBe('3201020202020001')
        ->and($rows[0][1])->toBe('987654321012345')
        ->and($rows[0][2])->toBe('3201020202020002')
        ->and($rows[0][3])->toBe('Jakarta')
        ->and($rows[0][4])->toBe('1990-01-05')
        ->and($rows[0][9])->toBe('Islam');
});

test('sensitive export neutralizes spreadsheet formulas', function () {
    e05bExportEmployee(['address' => '=cmd|\' /C calc\'!A0']);

    Sanctum::actingAs($this->operator);

    $csv = $this->get('/api/employees/export?columns=address')->assertStatus(200)->streamedContent();

    expect($csv)->toContain("'=cmd|")
        ->and($csv)->not->toContain(',=cmd|');
});

// ─────────────────────────────────────────────────────────────────────────────
// Filter, arsip, dan batas
// ─────────────────────────────────────────────────────────────────────────────

test('custom columns still apply directory filters and exclude archived rows', function () {
    e05bExportEmployee(['name' => 'Aktif', 'nik' => '3201020202020001']);
    e05bExportEmployee(['name' => 'Guru', 'nik' => '3201020202020002', 'employee_type_id' => $this->typeTeacher->id, 'subject_id' => $this->subject->id]);
    $archived = e05bExportEmployee(['name' => 'Arsip', 'nik' => '3201020202020003']);
    $archived->delete();

    Sanctum::actingAs($this->principal);

    [$header, $rows] = e05bExportCsv(
        $this->get('/api/employees/export?columns=name,employee_type&employee_type_id='.$this->typeTeacher->id)
            ->assertStatus(200)
    );

    expect($header)->toBe(['Name', 'Employee Type'])
        ->and($rows)->toHaveCount(1)
        ->and($rows[0])->toBe(['Guru', 'Teacher']);
});

test('export rejects a result set above the configured limit', function () {
    config(['employees.export_max_rows' => 1]);

    e05bExportEmployee(['nik' => '3201020202020001']);
    e05bExportEmployee(['name' => 'Pegawai Kedua', 'nik' => '3201020202020002']);

    Sanctum::actingAs($this->operator);

    $this->getJson('/api/employees/export?columns=nik')
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'limit of 1'));
});

test('invalid filters are rejected before any export work', function () {
    Sanctum::actingAs($this->principal);

    $this->getJson('/api/employees/export?employee_type_id=999999')->assertStatus(422);
    $this->getJson('/api/employees/export?subject_id=999999')->assertStatus(422);
});
