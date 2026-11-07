<?php

use App\Models\Activity;
use App\Models\District;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\Employment;
use App\Models\Gender;
use App\Models\ImportBatch;
use App\Models\MaritalStatus;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Religion;
use App\Models\Subject;
use App\Services\EmployeeImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

/**
 * Fase 05b (DEC-26): impor pegawai CSV.
 *
 * Dataset seluruhnya sintetis. Mode update mencocokkan NIK dan tidak pernah
 * mengubah NIK; sel kosong berarti tidak berubah. District selalu diidentifikasi
 * bersama Regency. Storage memakai disk fake `local`.
 */
beforeEach(function () {
    $this->seedAccessReferenceData();

    $province = Province::firstOrCreate(['name' => 'DKI Jakarta']);
    $this->regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Utara']);
    $this->district = District::firstOrCreate(['regency_id' => $this->regency->id, 'name' => 'Koja']);

    $this->gender = Gender::firstOrCreate(['name' => 'Laki-Laki']);
    Gender::firstOrCreate(['name' => 'Perempuan']);
    $this->religion = Religion::firstOrCreate(['name' => 'Islam']);
    $this->maritalStatus = MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);
    MaritalStatus::firstOrCreate(['name' => 'Menikah']);

    $this->status = EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap Yayasan'], ['abbreviation' => 'PTY']);
    EmployeeStatus::firstOrCreate(['name' => 'Guru Tetap Yayasan'], ['abbreviation' => 'GTY']);
    $this->typeStaff = EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
    $this->typeTeacher = EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $this->employment = Employment::firstOrCreate(['name' => 'Tetap Yayasan'], ['description' => 'Tetap']);
    Employment::firstOrCreate(['name' => 'Guru Mata Pelajaran'], ['description' => 'Guru']);
    Employment::firstOrCreate(['name' => 'Tenaga Administrasi Sekolah'], ['description' => 'Staff']);
    $this->subject = Subject::firstOrCreate(['name' => 'Matematika'], ['description' => 'Matematika']);

    Storage::fake('local');
    config(['imports.disk' => 'local']);

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->plainUser = $this->makeUserWithRole();

    $this->upload = function (string $csv, string $mode = 'insert', string $name = 'employees.csv') {
        Sanctum::actingAs($this->operator);

        return $this->post('/api/imports/employees', [
            'file' => UploadedFile::fake()->createWithContent($name, $csv),
            'mode' => $mode,
        ], ['Accept' => 'application/json']);
    };
});

function e05bRow(array $overrides = []): array
{
    return array_merge([
        'Name' => 'Synthetic Employee',
        'NIK' => '3201020202020001',
        'NUPTK' => '1122334455667788',
        'Employee Type' => 'Tenaga Kependidikan',
        'Employee Status' => 'Pegawai Tetap Yayasan',
        'Employment' => 'Tetap Yayasan',
        'Subject' => '',
        'Gender' => 'Laki-Laki',
        'Religion' => 'Islam',
        'Marital Status' => 'Belum Menikah',
        'Regency' => 'Jakarta Utara',
        'District' => 'Koja',
        'TMT' => '2026-01-01',
        'Exit Date' => '',
        'Place of Birth' => 'Synthetic City',
        'Date of Birth' => '1990-01-05',
        'Address' => 'Synthetic Street 1',
        'RT' => '001',
        'RW' => '002',
        'Zip Code' => '12345',
        'Phone' => '081200000001',
        'Email' => 'synthetic.employee@example.test',
    ], $overrides);
}

/**
 * @param  array<int, array<string, string|null>>  $rows
 * @param  array<int, string>|null  $headers
 */
function e05bCsv(array $rows, ?array $headers = null): string
{
    $headers ??= EmployeeImportService::templateHeaders();

    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, $headers, ',', '"', '\\');

    foreach ($rows as $row) {
        fputcsv($stream, array_map(fn (string $header) => $row[$header] ?? '', $headers), ',', '"', '\\');
    }

    rewind($stream);
    $csv = (string) stream_get_contents($stream);
    fclose($stream);

    return $csv;
}

function e05bEmployee(array $overrides = []): Employee
{
    return Employee::create(array_merge([
        'name' => 'Pegawai Sintetis',
        'nik' => '3201020202029001',
        'nuptk' => '9988776655443322',
        'employee_type_id' => test()->typeStaff->id,
        'employee_status_id' => test()->status->id,
        'employment_id' => test()->employment->id,
        'religion_id' => test()->religion->id,
        'district_id' => test()->district->id,
        'marital_status_id' => test()->maritalStatus->id,
        'gender_id' => test()->gender->id,
        'place_of_birth' => 'Synthetic City',
        'address' => 'Synthetic Street 9',
        'wa_number' => '081200000009',
        'tmt_employee' => '2025-01-01',
    ], $overrides));
}

// ─────────────────────────────────────────────────────────────────────────────
// Akses
// ─────────────────────────────────────────────────────────────────────────────

test('guest cannot use employee import endpoints', function () {
    $this->getJson('/api/imports/employees/template')->assertStatus(401);
    $this->postJson('/api/imports/employees', ['mode' => 'insert'])->assertStatus(401);
    $this->getJson('/api/imports/employees/any-token')->assertStatus(401);
    $this->postJson('/api/imports/employees/any-token/commit')->assertStatus(401);
    $this->getJson('/api/imports/employees/any-token/errors')->assertStatus(401);
});

test('employee import requires employees.import, template only employees.view', function () {
    $csv = e05bCsv([e05bRow()]);

    Sanctum::actingAs($this->principal);
    $this->post('/api/imports/employees', [
        'file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
        'mode' => 'insert',
    ], ['Accept' => 'application/json'])->assertStatus(403);

    $this->get('/api/imports/employees/template')->assertStatus(200);

    Sanctum::actingAs($this->plainUser);
    $this->post('/api/imports/employees', [
        'file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
        'mode' => 'insert',
    ], ['Accept' => 'application/json'])->assertStatus(403);

    expect(ImportBatch::count())->toBe(0);
});

test('operator and system administrator may start an employee import', function () {
    ($this->upload)(e05bCsv([e05bRow()]))->assertCreated();

    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);
    $this->post('/api/imports/employees', [
        'file' => UploadedFile::fake()->createWithContent(
            'employees.csv',
            e05bCsv([e05bRow(['NIK' => '3201020202020002'])])
        ),
        'mode' => 'insert',
    ], ['Accept' => 'application/json'])->assertCreated();

    expect(ImportBatch::count())->toBe(2);
});

test('another user or another domain cannot inspect or commit the batch', function () {
    $token = ($this->upload)(e05bCsv([e05bRow()]))->json('data.token');

    $other = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($other);

    $this->getJson("/api/imports/employees/{$token}")->assertStatus(404);
    $this->postJson("/api/imports/employees/{$token}/commit")->assertStatus(404);
    $this->get("/api/imports/employees/{$token}/errors")->assertStatus(404);

    Sanctum::actingAs($this->operator);
    $this->getJson("/api/imports/{$token}")->assertStatus(404);
    $this->postJson("/api/imports/{$token}/commit")->assertStatus(404);

    expect(Employee::count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Template & preview
// ─────────────────────────────────────────────────────────────────────────────

test('template download exposes the 57 English employee headers and staff dummy row by default', function () {
    Sanctum::actingAs($this->principal);

    $response = $this->get('/api/imports/employees/template');

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    $lines = explode("\n", trim($csv));
    $headers = str_getcsv($lines[0]);
    $sample = str_getcsv($lines[1]);

    expect($headers)->toBe(EmployeeImportService::templateHeaders('en'))
        ->and($headers)->toHaveCount(57)
        ->and($sample)->toHaveCount(57)
        ->and($headers)->toContain('NIK')
        ->and($headers)->toContain('Regency')
        ->and($headers)->toContain('District')
        ->and($headers)->toContain('Teaching Certificate')
        ->and($headers)->toContain('Family Card Number')
        ->and($headers)->toContain('NPWP')
        ->and($headers)->toContain('Spouse Name')
        ->and($headers)->toContain('Mother Name')
        ->and($headers)->toContain('Elementary School Name')
        ->and($headers)->toContain('Junior High School NISN')
        ->and($headers)->toContain('Senior High School Major')
        ->and($headers)->toContain('Dapodik Status')
        ->and($headers)->toContain('Diploma Verval')
        ->and($headers)->toContain('Certification Status')
        ->and($sample[0])->toBe('Bambang Sudarsono, S.Kom')
        ->and($csv)->toContain('Tenaga Kependidikan')
        ->and($csv)->not->toContain('Password')
        ->and($csv)->not->toContain('Username');
});

test('employee template download supports Indonesian headers when lang=id is requested', function () {
    Sanctum::actingAs($this->principal);

    $response = $this->get('/api/imports/employees/template?lang=id');

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    $lines = explode("\n", trim($csv));
    $headers = str_getcsv($lines[0]);
    $sample = str_getcsv($lines[1]);

    expect($headers)->toBe(EmployeeImportService::templateHeaders('id'))
        ->and($headers)->toHaveCount(57)
        ->and($sample)->toHaveCount(57)
        ->and($headers)->toContain('NIK')
        ->and($headers)->toContain('Kabupaten/Kota')
        ->and($headers)->toContain('Kecamatan')
        ->and($headers)->toContain('SK Mengajar')
        ->and($headers)->toContain('Nomor KK')
        ->and($headers)->toContain('NPWP')
        ->and($headers)->toContain('Nama Suami / Istri')
        ->and($headers)->toContain('Nama Ibu Kandung')
        ->and($headers)->toContain('Satuan Pendidikan SD')
        ->and($headers)->toContain('NIS/NISN SMP')
        ->and($headers)->toContain('Jurusan SMA')
        ->and($headers)->toContain('Dapodik')
        ->and($headers)->toContain('Verval Ijazah')
        ->and($headers)->toContain('Serti')
        ->and($sample[0])->toBe('Bambang Sudarsono, S.Kom');
});

test('teacher template download provides contextual teacher dummy row with subject', function () {
    Sanctum::actingAs($this->principal);

    $response = $this->get('/api/imports/employees/template?type=Pendidik');

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    $lines = explode("\n", trim($csv));
    $headers = str_getcsv($lines[0]);
    $sample = str_getcsv($lines[1]);

    expect($headers)->toBe(EmployeeImportService::templateHeaders())
        ->and($sample)->toHaveCount(57)
        ->and($sample[0])->toBe('Dra. Siti Nurhaliza, M.Pd')
        ->and($csv)->toContain('Pendidik')
        ->and($csv)->toContain('Matematika');

    // Pastikan baris dummy guru valid untuk preview dan commit
    $uploadResponse = ($this->upload)($csv);
    $uploadResponse->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', ImportBatch::STATUS_READY)
        ->assertJsonPath('data.total_rows', 1)
        ->assertJsonPath('data.valid_rows', 1)
        ->assertJsonPath('data.invalid_rows', 0);

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/employees/'.$uploadResponse->json('data.token').'/commit')
        ->assertStatus(200)
        ->assertJsonPath('data.committed_rows', 1);

    expect(Employee::where('nik', '3201020202020001')->exists())->toBeTrue();
});

test('staff template download provides contextual staff dummy row without subject', function () {
    Sanctum::actingAs($this->principal);

    $response = $this->get('/api/imports/employees/template?type=Tenaga+Kependidikan');

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    $lines = explode("\n", trim($csv));
    $sample = str_getcsv($lines[1]);

    expect($sample[0])->toBe('Bambang Sudarsono, S.Kom')
        ->and($csv)->toContain('Tenaga Kependidikan');

    // Pastikan baris dummy staf/karyawan valid untuk preview dan commit
    $uploadResponse = ($this->upload)($csv);
    $uploadResponse->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', ImportBatch::STATUS_READY)
        ->assertJsonPath('data.total_rows', 1)
        ->assertJsonPath('data.valid_rows', 1)
        ->assertJsonPath('data.invalid_rows', 0);

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/employees/'.$uploadResponse->json('data.token').'/commit')
        ->assertStatus(200)
        ->assertJsonPath('data.committed_rows', 1);

    expect(Employee::where('nik', '3201020202020002')->exists())->toBeTrue();
});

test('preview validates the file without mutating employees', function () {
    $response = ($this->upload)(e05bCsv([
        e05bRow(),
        e05bRow(['NIK' => '3201020202020002', 'Name' => 'Synthetic Employee Two']),
    ]));

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.domain', 'employees')
        ->assertJsonPath('data.status', ImportBatch::STATUS_READY)
        ->assertJsonPath('data.total_rows', 2)
        ->assertJsonPath('data.valid_rows', 2)
        ->assertJsonPath('data.invalid_rows', 0)
        ->assertJsonPath('data.has_errors', false)
        ->assertJsonCount(2, 'data.preview')
        ->assertJsonPath('data.preview.0.action', 'insert')
        ->assertJsonPath('data.preview.0.employee_type', 'Non-teaching Employee');

    expect(Employee::count())->toBe(0)
        ->and(ImportBatch::count())->toBe(1)
        ->and($response->json('data.preview.0'))->toHaveKeys(['row', 'action', 'name', 'nik', 'nuptk', 'employee_type']);
});

test('commit preserves leading zeros and maps reference values', function () {
    $response = ($this->upload)(e05bCsv([
        e05bRow([
            'NIK' => '0012345678901234',
            'Date of Birth' => '01/05/2010',
            'TMT' => '02/01/2026',
            'Employee Type' => 'Tenaga Kependidikan',
        ]),
    ]));
    $response->assertCreated();

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/employees/'.$response->json('data.token').'/commit')
        ->assertStatus(200)
        ->assertJsonPath('data.status', ImportBatch::STATUS_COMMITTED)
        ->assertJsonPath('data.committed_rows', 1);

    $employee = Employee::firstOrFail();

    expect($employee->nik)->toBe('0012345678901234')
        ->and($employee->nuptk)->toBe('1122334455667788')
        ->and($employee->employee_type_id)->toBe($this->typeStaff->id)
        ->and($employee->employee_status_id)->toBe($this->status->id)
        ->and($employee->employment_id)->toBe($this->employment->id)
        ->and($employee->gender_id)->toBe($this->gender->id)
        ->and($employee->religion_id)->toBe($this->religion->id)
        ->and($employee->marital_status_id)->toBe($this->maritalStatus->id)
        ->and($employee->district_id)->toBe($this->district->id)
        ->and($employee->subject_id)->toBeNull()
        ->and($employee->date_of_birth)->toBe('2010-01-05')
        ->and($employee->tmt_employee)->toBe('2026-02-01')
        ->and($employee->version)->toBe(1);
});

test('unknown or missing headers are rejected without creating a batch', function () {
    $response = ($this->upload)(e05bCsv([e05bRow()], ['Nama', 'NIK', 'NIS']));

    $response->assertStatus(422);
    expect($response->json('errors.file'))->not->toBeEmpty()
        ->and(ImportBatch::count())->toBe(0)
        ->and(Employee::count())->toBe(0);
});

test('empty files and files without data rows are rejected', function () {
    ($this->upload)('')->assertStatus(422);
    ($this->upload)(e05bCsv([]))->assertStatus(422);

    expect(ImportBatch::count())->toBe(0);
});

test('a non-csv file is rejected by validation', function () {
    Sanctum::actingAs($this->operator);

    $this->post('/api/imports/employees', [
        'file' => UploadedFile::fake()->create('employees.pdf', 10, 'application/pdf'),
        'mode' => 'insert',
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

// ─────────────────────────────────────────────────────────────────────────────
// Validasi baris insert
// ─────────────────────────────────────────────────────────────────────────────

test('insert requires core profile fields and both Regency and District', function () {
    $response = ($this->upload)(e05bCsv([
        e05bRow([
            'Name' => '',
            'TMT' => '',
            'Employee Type' => '',
        ]),
        e05bRow([
            'NIK' => '3201020202020002',
            'Regency' => 'Atlantis',
            'District' => 'Koja',
        ]),
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.status', ImportBatch::STATUS_INVALID)
        ->assertJsonPath('data.invalid_rows', 2);

    $messages = collect($response->json('data.errors'))->pluck('message')->implode(' ');

    expect($messages)->toContain('Employee name is required.')
        ->and($messages)->toContain('The TMT (employment start date) is required.')
        ->and($messages)->toContain('Unknown Regency value: Atlantis.')
        ->and(Employee::count())->toBe(0);
});

test('teacher rows require a subject and staff rows must not have one', function () {
    $response = ($this->upload)(e05bCsv([
        e05bRow(['Name' => 'Guru Tanpa Mapel', 'Employee Type' => 'Pendidik', 'Subject' => '']),
        e05bRow(['NIK' => '3201020202020002', 'Name' => 'Staf Dengan Mapel', 'Subject' => 'Matematika']),
    ]));

    $response->assertCreated()->assertJsonPath('data.status', ImportBatch::STATUS_INVALID);

    $messages = collect($response->json('data.errors'))->pluck('message')->implode(' ');

    expect($messages)->toContain('Teachers must have a subject.')
        ->and($messages)->toContain('Non-teaching employees must not have a subject.');

    $valid = ($this->upload)(e05bCsv([
        e05bRow(['NIK' => '3201020202020003', 'Name' => 'Guru Dengan Mapel', 'Employee Type' => 'Pendidik', 'Subject' => 'Matematika']),
    ]));
    $valid->assertCreated()->assertJsonPath('data.status', ImportBatch::STATUS_READY);

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/employees/'.$valid->json('data.token').'/commit')->assertStatus(200);

    expect(Employee::where('name', 'Guru Dengan Mapel')->firstOrFail()->subject_id)->toBe($this->subject->id);
});

test('duplicate NIK inside the file and against archived records are reported', function () {
    $archived = e05bEmployee(['nik' => '3201020202020099']);
    $archived->delete();

    $response = ($this->upload)(e05bCsv([
        e05bRow(['NIK' => '3201020202020001']),
        e05bRow(['NIK' => '3201020202020001']),
        e05bRow(['NIK' => '3201020202020099', 'Name' => 'Pegawai Duplikat Arsip']),
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.status', ImportBatch::STATUS_INVALID)
        ->assertJsonPath('data.invalid_rows', 3);

    $messages = collect($response->json('data.errors'))->pluck('message')->implode(' ');

    expect($messages)->toContain('Duplicate NIK within the file')
        ->and($messages)->toContain('The NIK has already been used by another employee.');

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/employees/'.$response->json('data.token').'/commit')->assertStatus(409);

    expect(Employee::withTrashed()->count())->toBe(1)
        ->and(Employee::count())->toBe(0);
});

test('ambiguous and archived subjects are rejected', function () {
    $duplicate = Subject::create(['name' => 'Matematika', 'description' => 'Duplikat']);
    $deleted = Subject::create(['name' => 'Fisika', 'description' => 'Terhapus']);
    $deleted->delete();

    $response = ($this->upload)(e05bCsv([
        e05bRow([
            'NIK' => '3201020202020001',
            'Name' => 'Guru Ambigu',
            'Employee Type' => 'Pendidik',
            'Subject' => 'Matematika',
        ]),
        e05bRow([
            'NIK' => '3201020202020002',
            'Name' => 'Guru Mapel Terhapus',
            'Employee Type' => 'Pendidik',
            'Subject' => 'Fisika',
        ]),
    ]));

    $response->assertCreated()->assertJsonPath('data.invalid_rows', 2);

    $messages = collect($response->json('data.errors'))->pluck('message')->implode(' ');

    expect($messages)->toContain('Subject name is ambiguous: Matematika.')
        ->and($messages)->toContain('Unknown Subject value: Fisika.');

    expect($duplicate->id)->not->toBe($this->subject->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// Geolokasi: Regency + District
// ─────────────────────────────────────────────────────────────────────────────

test('district must belong to the selected regency and be unambiguous', function () {
    $otherRegency = Regency::firstOrCreate(['province_id' => $this->regency->province_id, 'name' => 'Jakarta Pusat']);
    $mismatchRegency = Regency::firstOrCreate(['province_id' => $this->regency->province_id, 'name' => 'Jakarta Barat']);
    District::firstOrCreate(['regency_id' => $otherRegency->id, 'name' => 'Koja']);
    District::create(['regency_id' => $this->regency->id, 'name' => 'Koja']);

    $response = ($this->upload)(e05bCsv([
        e05bRow(['NIK' => '3201020202020001', 'Name' => 'Ambigu', 'District' => 'Koja']),
        e05bRow(['NIK' => '3201020202020002', 'Name' => 'Salah Regency', 'Regency' => 'Jakarta Barat', 'District' => 'Koja']),
        e05bRow(['NIK' => '3201020202020003', 'Name' => 'Tidak Ada', 'District' => 'Penjaringan']),
    ]));

    $response->assertCreated()->assertJsonPath('data.invalid_rows', 3);

    $messages = collect($response->json('data.errors'))->pluck('message')->implode(' ');

    expect($messages)->toContain('District name is ambiguous within the selected Regency: Koja.')
        ->and($messages)->toContain('Unknown District value in the selected Regency: Penjaringan.');

    // Nama district yang sama di regency berbeda tetap unik untuk regency tersebut.
    $valid = ($this->upload)(e05bCsv([
        e05bRow([
            'NIK' => '3201020202020004',
            'Name' => 'Regency Pusat',
            'Regency' => 'Jakarta Pusat',
            'District' => 'Koja',
        ]),
    ]));

    $valid->assertCreated()->assertJsonPath('data.status', ImportBatch::STATUS_READY);

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/employees/'.$valid->json('data.token').'/commit')->assertStatus(200);

    expect(Employee::where('name', 'Regency Pusat')->firstOrFail()->district_id)
        ->toBe(District::where('regency_id', $otherRegency->id)->where('name', 'Koja')->value('id'));
});

// ─────────────────────────────────────────────────────────────────────────────
// Mode update
// ─────────────────────────────────────────────────────────────────────────────

test('update mode changes only filled cells and leaves decoys untouched', function () {
    $target = e05bEmployee([
        'name' => 'Alpha Synthetic',
        'nik' => '0012345678901234',
        'place_of_birth' => 'Kota Lama',
        'wa_number' => '081200000001',
    ]);
    $decoy = e05bEmployee(['name' => 'Beta Synthetic', 'nik' => '0012345678901235', 'version' => 1]);

    $updateRow = array_map(fn () => '', e05bRow());
    $updateRow['NIK'] = '0012345678901234';
    $updateRow['Name'] = 'Alpha Synthetic Updated';

    $response = ($this->upload)(e05bCsv([$updateRow]), 'update');

    $response->assertCreated()
        ->assertJsonPath('data.mode', 'update')
        ->assertJsonPath('data.status', ImportBatch::STATUS_READY)
        ->assertJsonPath('data.preview.0.action', 'update')
        ->assertJsonPath('data.preview.0.name', 'Alpha Synthetic Updated');

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/employees/'.$response->json('data.token').'/commit')->assertStatus(200);

    $target->refresh();
    $decoy->refresh();

    expect($target->name)->toBe('Alpha Synthetic Updated')
        ->and($target->nik)->toBe('0012345678901234')
        ->and($target->place_of_birth)->toBe('Kota Lama')
        ->and($target->wa_number)->toBe('081200000001')
        ->and($target->nuptk)->toBe('9988776655443322')
        ->and($target->version)->toBe(2)
        ->and($decoy->name)->toBe('Beta Synthetic')
        ->and($decoy->version)->toBe(1)
        ->and(Employee::count())->toBe(2);
});

test('update mode requires NIK and rejects unknown or archived matches', function () {
    $archived = e05bEmployee(['name' => 'Arsip', 'nik' => '3201020202020098']);
    $archived->delete();

    $response = ($this->upload)(e05bCsv([
        e05bRow(['NIK' => '', 'Name' => 'Tanpa NIK']),
        e05bRow(['NIK' => '3201020202020977', 'Name' => 'Tidak Ditemukan']),
        e05bRow(['NIK' => '3201020202020098', 'Name' => 'Cocok Arsip']),
    ]), 'update');

    $response->assertCreated()
        ->assertJsonPath('data.status', ImportBatch::STATUS_INVALID)
        ->assertJsonPath('data.invalid_rows', 3);

    $messages = collect($response->json('data.errors'))->pluck('message')->implode(' ');

    expect($messages)->toContain('NIK is required to match an employee in update mode.')
        ->and($messages)->toContain('No employee matches the provided NIK in update mode.')
        ->and($messages)->toContain('The matched employee is archived');

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/employees/'.$response->json('data.token').'/commit')->assertStatus(409);

    expect(Employee::withTrashed()->count())->toBe(1)
        ->and($archived->fresh()->name)->toBe('Arsip');
});

test('update rows without changes and duplicate match keys are rejected', function () {
    e05bEmployee(['nik' => '3201020202020001']);

    $onlyNik = array_map(fn () => '', e05bRow());
    $onlyNik['NIK'] = '3201020202020001';

    $response = ($this->upload)(e05bCsv([
        $onlyNik,
        e05bRow(['NIK' => '3201020202020001', 'Name' => 'Duplikat']),
    ]), 'update');

    $response->assertCreated()->assertJsonPath('data.status', ImportBatch::STATUS_INVALID);

    $messages = collect($response->json('data.errors'))->pluck('message')->implode(' ');

    expect($messages)->toContain('No values to update on this row.')
        ->and($messages)->toContain('Duplicate match key within the file');
});

test('changing the employee type to staff clears the subject', function () {
    $teacher = e05bEmployee([
        'nik' => '3201020202020001',
        'employee_type_id' => $this->typeTeacher->id,
        'subject_id' => $this->subject->id,
    ]);

    $response = ($this->upload)(e05bCsv([
        e05bRow([
            'NIK' => '3201020202020001',
            'Employee Type' => 'Tenaga Kependidikan',
            'Subject' => '',
        ]),
    ]), 'update');

    $response->assertCreated()->assertJsonPath('data.status', ImportBatch::STATUS_READY);

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/employees/'.$response->json('data.token').'/commit')->assertStatus(200);

    $teacher->refresh();

    expect($teacher->employee_type_id)->toBe($this->typeStaff->id)
        ->and($teacher->subject_id)->toBeNull()
        ->and($teacher->version)->toBe(2);
});

test('update validation merges stored values with the changed cells', function () {
    e05bEmployee(['nik' => '3201020202020001', 'employee_type_id' => $this->typeStaff->id, 'subject_id' => null]);

    $response = ($this->upload)(e05bCsv([
        e05bRow([
            'NIK' => '3201020202020001',
            'Employee Type' => 'Pendidik',
            'Subject' => '',
        ]),
    ]), 'update');

    $response->assertCreated()->assertJsonPath('data.status', ImportBatch::STATUS_INVALID);

    expect(collect($response->json('data.errors'))->pluck('message')->implode(' '))
        ->toContain('Teachers must have a subject.');
});

test('update mode requires Regency and District together', function () {
    e05bEmployee(['nik' => '3201020202020001']);

    $response = ($this->upload)(e05bCsv([
        e05bRow(['NIK' => '3201020202020001', 'Regency' => 'Jakarta Utara', 'District' => '']),
    ]), 'update');

    $response->assertCreated()->assertJsonPath('data.status', ImportBatch::STATUS_INVALID);

    expect(collect($response->json('data.errors'))->pluck('message')->implode(' '))
        ->toContain('Provide both Regency and District, or leave both empty.');

    $valid = ($this->upload)(e05bCsv([
        e05bRow(['NIK' => '3201020202020001', 'Regency' => 'Jakarta Utara', 'District' => 'Koja']),
    ]), 'update');

    $valid->assertCreated()->assertJsonPath('data.status', ImportBatch::STATUS_READY);
});

// ─────────────────────────────────────────────────────────────────────────────
// Commit: atomisitas, idempotensi, kedaluwarsa
// ─────────────────────────────────────────────────────────────────────────────

test('a failure in the middle of a commit rolls back every row', function () {
    $response = ($this->upload)(e05bCsv([
        e05bRow(['NIK' => '3201020202020011', 'Name' => 'Row One']),
        e05bRow(['NIK' => '3201020202020012', 'Name' => 'Row Two']),
    ]));
    $token = $response->json('data.token');

    Employee::creating(function (Employee $employee) {
        if ($employee->name === 'Row Two') {
            throw new RuntimeException('Synthetic failure during commit.');
        }
    });

    Sanctum::actingAs($this->operator);
    $this->withoutExceptionHandling();

    $thrown = null;
    try {
        $this->postJson("/api/imports/employees/{$token}/commit");
    } catch (RuntimeException $exception) {
        $thrown = $exception;
    }

    $batch = ImportBatch::where('token', $token)->firstOrFail();

    expect($thrown)->not->toBeNull()
        ->and(Employee::count())->toBe(0)
        ->and(Activity::where('table_name', 'employees')->count())->toBe(0)
        ->and($batch->status)->toBe(ImportBatch::STATUS_READY)
        ->and($batch->committed_rows)->toBe(0);
});

test('commit is idempotent for the same batch token', function () {
    $response = ($this->upload)(e05bCsv([e05bRow()]));
    $token = $response->json('data.token');

    Sanctum::actingAs($this->operator);

    $this->postJson("/api/imports/employees/{$token}/commit")
        ->assertStatus(200)
        ->assertJsonPath('data.committed_rows', 1);

    $this->postJson("/api/imports/employees/{$token}/commit")
        ->assertStatus(200)
        ->assertJsonPath('data.status', ImportBatch::STATUS_COMMITTED)
        ->assertJsonPath('data.committed_rows', 1);

    expect(Employee::count())->toBe(1);
});

test('expired batches and changed source files are rejected at commit', function () {
    $expired = ($this->upload)(e05bCsv([e05bRow()]))->json('data.token');
    ImportBatch::where('token', $expired)->firstOrFail()
        ->forceFill(['created_at' => now()->subHours(3)])
        ->save();

    Sanctum::actingAs($this->operator);
    $this->postJson("/api/imports/employees/{$expired}/commit")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This import batch has expired. Upload the file again to continue.');

    $tampered = ($this->upload)(e05bCsv([e05bRow(['NIK' => '3201020202020002'])]))->json('data.token');
    Storage::disk('local')->put(
        "imports/{$tampered}/source.csv",
        e05bCsv([e05bRow(['NIK' => '3201020202020002', 'Name' => 'Tampered'])])
    );

    $this->postJson("/api/imports/employees/{$tampered}/commit")
        ->assertStatus(409)
        ->assertJsonPath('message', 'The stored source file no longer matches this batch. Upload the file again.');

    expect(Employee::count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Batas file dan baris
// ─────────────────────────────────────────────────────────────────────────────

test('row and size limits reject the file instead of truncating it', function () {
    config(['imports.employees.max_rows' => 2]);

    ($this->upload)(e05bCsv([
        e05bRow(),
        e05bRow(['NIK' => '3201020202020002']),
        e05bRow(['NIK' => '3201020202020003']),
    ]))->assertStatus(422);

    expect(ImportBatch::count())->toBe(0);

    config(['imports.employees.max_rows' => 2000, 'imports.employees.max_size_kb' => 1]);

    ($this->upload)(e05bCsv([e05bRow(['Address' => str_repeat('A', 2048)])]))->assertStatus(422);

    expect(ImportBatch::count())->toBe(0)
        ->and(Employee::count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Laporan error & audit
// ─────────────────────────────────────────────────────────────────────────────

test('error report uses the employee identity headers and neutralizes formulas', function () {
    $response = ($this->upload)(e05bCsv([
        e05bRow([
            'NIK' => '=cmd|\' /C calc\'!A0',
            'NUPTK' => '=1+1',
            'Name' => 'Pegawai Error',
        ]),
    ]));

    $token = $response->json('data.token');
    expect(Storage::disk('local')->exists("imports/{$token}/errors.csv"))->toBeTrue();

    Sanctum::actingAs($this->operator);
    $download = $this->get("/api/imports/employees/{$token}/errors");

    $download->assertStatus(200);
    expect($download->headers->get('content-disposition'))->toContain("employees-import-errors-{$token}.csv");

    $csv = $download->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim($csv))));
    $header = str_getcsv($lines[0]);

    expect($header)->toBe(['Row', 'Field', 'Message', 'NIK', 'NUPTK'])
        ->and($csv)->toContain("'=cmd|")
        ->and($csv)->toContain("'=1+1")
        ->and($csv)->not->toContain(',=cmd|');
});

test('employee import audit records only allowlisted attributes', function () {
    $token = ($this->upload)(e05bCsv([e05bRow()]))->json('data.token');

    Sanctum::actingAs($this->operator);
    $this->postJson("/api/imports/employees/{$token}/commit")->assertStatus(200);

    $activity = Activity::where('table_name', 'employees')->where('information', 'Created')->firstOrFail();
    $logged = array_keys($activity->new ?? []);

    expect($logged)->toContain('employee_type_id')
        ->and($logged)->not->toContain('name')
        ->and($logged)->not->toContain('nik')
        ->and($logged)->not->toContain('nuptk')
        ->and($logged)->not->toContain('address')
        ->and($logged)->not->toContain('place_of_birth');
});

test('employee import accepts a UTF-8 BOM and semicolon delimiter', function () {
    $csv = "\xEF\xBB\xBF".str_replace(',', ';', e05bCsv([e05bRow()]));

    $response = ($this->upload)($csv);
    $response->assertCreated()->assertJsonPath('data.status', ImportBatch::STATUS_READY);

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/employees/'.$response->json('data.token').'/commit')->assertStatus(200);

    expect(Employee::count())->toBe(1);
});

test('employee template can be downloaded as multi-sheet xlsx with reference dropdowns', function () {
    Sanctum::actingAs($this->operator);

    $response = $this->get('/api/imports/employees/template?type=Pendidik&format=xlsx');
    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($response->headers->get('content-disposition'))->toContain('teachers-import-template.xlsx');

    $content = $response->streamedContent();
    expect(strlen($content))->toBeGreaterThan(1000);
});

test('employee import accepts an xlsx file and normalizes marital status, employment, and aliases', function () {
    $service = app(EmployeeImportService::class);
    $xlsxBinary = $service->templateXlsx('Pendidik');

    Sanctum::actingAs($this->operator);

    $file = UploadedFile::fake()->createWithContent('teachers.xlsx', $xlsxBinary);
    $response = $this->post('/api/imports/employees', [
        'file' => $file,
        'mode' => 'insert',
    ], ['Accept' => 'application/json']);

    $response->assertCreated();
    $status = $response->json('data.status');
    expect($status)->toBe(ImportBatch::STATUS_READY);

    $token = $response->json('data.token');
    $commit = $this->postJson("/api/imports/employees/{$token}/commit");
    $commit->assertStatus(200);

    expect(Employee::where('nik', '3201020202020001')->exists())->toBeTrue();
});

test('employee import normalizes common colloquial terms like kawin and singkatan jabatan', function () {
    $response = ($this->upload)(e05bCsv([
        e05bRow([
            'NIK' => '3201020202020010',
            'Name' => 'Guru Aliasing',
            'Marital Status' => 'Kawin',
            'Employment' => 'Guru Mata Pelajaran',
            'Subject' => 'Matematika',
            'Employee Type' => 'Pendidik',
        ]),
    ]));

    $response->assertCreated()->assertJsonPath('data.status', ImportBatch::STATUS_READY);
    $token = $response->json('data.token');

    Sanctum::actingAs($this->operator);
    $this->postJson("/api/imports/employees/{$token}/commit")->assertStatus(200);

    $employee = Employee::where('nik', '3201020202020010')->firstOrFail();
    expect($employee->marital_status_id)->toBe(MaritalStatus::where('name', 'Menikah')->value('id'));
});

test('employee import parses dates in Excel M/D/YYYY format and serial numbers', function () {
    $response = ($this->upload)(e05bCsv([
        e05bRow([
            'NIK' => '3201020202020021',
            'Name' => 'Employee M/D/YYYY Test 1',
            'TMT' => '7/1/2020',
            'Date of Birth' => '4/12/1985',
        ]),
        e05bRow([
            'NIK' => '3201020202020022',
            'Name' => 'Employee M/D/YYYY Test 2 (Day > 12)',
            'TMT' => '1/1/2021',
            'Date of Birth' => '8/20/1990',
        ]),
        e05bRow([
            'NIK' => '3201020202020023',
            'Name' => 'Employee Excel Serial Test',
            'TMT' => '44013',
            'Date of Birth' => '31149',
        ]),
    ]));

    $response->assertCreated()->assertJsonPath('data.status', ImportBatch::STATUS_READY)
        ->assertJsonPath('data.valid_rows', 3)
        ->assertJsonPath('data.invalid_rows', 0);

    $token = $response->json('data.token');
    Sanctum::actingAs($this->operator);
    $this->postJson("/api/imports/employees/{$token}/commit")->assertStatus(200);

    $emp1 = Employee::where('nik', '3201020202020021')->firstOrFail();
    expect($emp1->tmt_employee)->toBe('2020-07-01')
        ->and($emp1->date_of_birth)->toBe('1985-04-12');

    $emp2 = Employee::where('nik', '3201020202020022')->firstOrFail();
    expect($emp2->tmt_employee)->toBe('2021-01-01')
        ->and($emp2->date_of_birth)->toBe('1990-08-20');

    $emp3 = Employee::where('nik', '3201020202020023')->firstOrFail();
    expect($emp3->tmt_employee)->toBe('2020-07-01')
        ->and($emp3->date_of_birth)->toBe('1985-04-12');
});
