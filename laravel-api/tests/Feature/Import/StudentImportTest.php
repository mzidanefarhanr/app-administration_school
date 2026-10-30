<?php

use App\Models\BloodType;
use App\Models\EducationLevel;
use App\Models\FamilyStatus;
use App\Models\Gender;
use App\Models\ImportBatch;
use App\Models\Profession;
use App\Models\Religion;
use App\Models\Student;
use App\Services\StudentImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

/**
 * Fase 05a (DEC-25): impor siswa CSV.
 *
 * Dataset seluruhnya sintetis; tidak ada identitas nyata. Storage memakai
 * disk fake `local` agar tidak menyentuh berkas operasional.
 */
beforeEach(function () {
    $this->seedAccessReferenceData();

    Gender::firstOrCreate(['name' => 'Laki-Laki']);
    Religion::firstOrCreate(['name' => 'Islam']);
    FamilyStatus::firstOrCreate(['name' => 'Lengkap']);
    EducationLevel::firstOrCreate(['name' => 'SD/Sederajat']);
    Profession::firstOrCreate(['name' => 'Swasta']);
    BloodType::firstOrCreate(['name' => 'O']);

    Storage::fake('local');
    config(['imports.disk' => 'local']);

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->plainUser = $this->makeUserWithRole();

    $this->upload = function (string $csv, string $mode = 'insert', string $name = 'students.csv') {
        Sanctum::actingAs($this->operator);

        return $this->post('/api/imports/students', [
            'file' => UploadedFile::fake()->createWithContent($name, $csv),
            'mode' => $mode,
        ], ['Accept' => 'application/json']);
    };
});

function s05aRow(array $overrides = []): array
{
    return array_merge([
        'Name' => 'Synthetic Student',
        'NIK' => '0012345678901234',
        'NIS' => '001001',
        'NISN' => '0012345678',
        'Gender' => 'Male',
        'Birthplace' => 'Synthetic City',
        'Birthdate' => '2010-01-05',
        'Religion' => 'Islam',
        'Family Status' => 'Both Parents Living',
        'Education Level' => 'Primary School or Equivalent',
        'Profession' => 'Private Sector',
        'Blood Type' => 'O',
        'Address' => 'Synthetic Street 1',
        'RT' => '001',
        'RW' => '002',
        'Zip Code' => '12345',
        'Phone' => '081200000001',
    ], $overrides);
}

/**
 * @param  array<int, array<string, string|null>>  $rows
 * @param  array<int, string>|null  $headers
 */
function s05aCsv(array $rows, ?array $headers = null): string
{
    $headers ??= StudentImportService::templateHeaders();

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

// ─────────────────────────────────────────────────────────────────────────────
// Preview tanpa mutasi
// ─────────────────────────────────────────────────────────────────────────────

test('preview validates the file without mutating students', function () {
    $response = ($this->upload)(s05aCsv([s05aRow(), s05aRow(['NIK' => '0012345678901235', 'NIS' => '001002', 'NISN' => '0012345679'])]));

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', ImportBatch::STATUS_READY)
        ->assertJsonPath('data.total_rows', 2)
        ->assertJsonPath('data.valid_rows', 2)
        ->assertJsonPath('data.invalid_rows', 0)
        ->assertJsonCount(2, 'data.preview');

    expect(Student::count())->toBe(0)
        ->and(ImportBatch::count())->toBe(1)
        ->and($response->json('data.preview.0.action'))->toBe('insert');
});

test('template download exposes the English headers', function () {
    Sanctum::actingAs($this->principal);

    $response = $this->get('/api/imports/students/template');

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    $headers = str_getcsv(strtok($csv, "\n"));

    expect($headers)->toBe(StudentImportService::templateHeaders())
        ->and($csv)->toContain('Name')
        ->and($csv)->toContain('NIK')
        ->and($csv)->toContain('Family Status');
});

// ─────────────────────────────────────────────────────────────────────────────
// Identitas tetap string (leading zero)
// ─────────────────────────────────────────────────────────────────────────────

test('commit preserves leading zeros for identity numbers', function () {
    $response = ($this->upload)(s05aCsv([s05aRow(['NIK' => '0012345678901234', 'NIS' => '00123', 'NISN' => '0012345678'])]));
    $response->assertCreated();

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/'.$response->json('data.token').'/commit')
        ->assertStatus(200)
        ->assertJsonPath('data.status', ImportBatch::STATUS_COMMITTED)
        ->assertJsonPath('data.committed_rows', 1);

    $student = Student::firstOrFail();

    expect($student->nik)->toBe('0012345678901234')
        ->and($student->nis)->toBe('00123')
        ->and($student->nisn)->toBe('0012345678')
        ->and($student->gender_id)->not->toBeNull()
        ->and($student->version)->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// Header/format salah ditolak tanpa mutasi
// ─────────────────────────────────────────────────────────────────────────────

test('unknown or missing headers are rejected without creating a batch', function () {
    $response = ($this->upload)(s05aCsv([s05aRow()], ['Nama', 'NIK', 'NIS']));

    $response->assertStatus(422);
    expect($response->json('errors.file'))->not->toBeEmpty()
        ->and(ImportBatch::count())->toBe(0)
        ->and(Student::count())->toBe(0);
});

test('empty files and files without data rows are rejected', function () {
    ($this->upload)('')->assertStatus(422);

    ($this->upload)(s05aCsv([]))->assertStatus(422);

    expect(ImportBatch::count())->toBe(0);
});

test('a non-csv file is rejected by validation', function () {
    Sanctum::actingAs($this->operator);

    $this->post('/api/imports/students', [
        'file' => UploadedFile::fake()->create('students.pdf', 10, 'application/pdf'),
        'mode' => 'insert',
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

// ─────────────────────────────────────────────────────────────────────────────
// All-or-nothing
// ─────────────────────────────────────────────────────────────────────────────

test('mixed valid and invalid rows block the whole commit', function () {
    $response = ($this->upload)(s05aCsv([
        s05aRow(),
        s05aRow(['NIK' => '12345', 'NIS' => '001002', 'NISN' => '0012345679']),
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.status', ImportBatch::STATUS_INVALID)
        ->assertJsonPath('data.valid_rows', 1)
        ->assertJsonPath('data.invalid_rows', 1)
        ->assertJsonPath('data.has_errors', true)
        ->assertJsonCount(1, 'data.preview');

    $token = $response->json('data.token');
    expect(Storage::disk('local')->exists("imports/{$token}/errors.csv"))->toBeTrue();

    Sanctum::actingAs($this->operator);
    $this->postJson("/api/imports/{$token}/commit")
        ->assertStatus(409)
        ->assertJsonPath('success', false);

    expect(Student::count())->toBe(0);
});

test('duplicates inside the file and against the database are reported', function () {
    Student::create(['name' => 'Existing Synthetic', 'nis' => '009999', 'nik' => '0099999999999999']);

    $response = ($this->upload)(s05aCsv([
        s05aRow(['NIS' => '001001', 'NIK' => '0012345678901234', 'NISN' => '0012345678']),
        s05aRow(['NIS' => '001001', 'NIK' => '0012345678901235', 'NISN' => '0012345679']),
        s05aRow(['NIS' => '009999', 'NIK' => '0012345678901236', 'NISN' => '0012345680']),
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.invalid_rows', 3);

    $messages = collect($response->json('data.errors'))->pluck('message')->implode(' ');

    expect($messages)->toContain('Duplicate NIS within the file')
        ->and($messages)->toContain('The NIS has already been registered in this school.');
});

test('commit is idempotent for the same batch token', function () {
    $response = ($this->upload)(s05aCsv([s05aRow()]));
    $token = $response->json('data.token');

    Sanctum::actingAs($this->operator);

    $this->postJson("/api/imports/{$token}/commit")
        ->assertStatus(200)
        ->assertJsonPath('data.committed_rows', 1);

    $this->postJson("/api/imports/{$token}/commit")
        ->assertStatus(200)
        ->assertJsonPath('data.status', ImportBatch::STATUS_COMMITTED)
        ->assertJsonPath('data.committed_rows', 1);

    expect(Student::count())->toBe(1);
});

test('a failure in the middle of a commit rolls back every row', function () {
    $response = ($this->upload)(s05aCsv([
        s05aRow(['NIS' => '001001', 'NIK' => '0012345678901234', 'NISN' => '0012345678', 'Name' => 'Row One']),
        s05aRow(['NIS' => '001002', 'NIK' => '0012345678901235', 'NISN' => '0012345679', 'Name' => 'Row Two']),
    ]));
    $token = $response->json('data.token');

    Student::creating(function (Student $student) {
        if ($student->name === 'Row Two') {
            throw new RuntimeException('Synthetic failure during commit.');
        }
    });

    Sanctum::actingAs($this->operator);
    $this->withoutExceptionHandling();

    $thrown = null;
    try {
        $this->postJson("/api/imports/{$token}/commit");
    } catch (RuntimeException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->not->toBeNull()
        ->and(Student::count())->toBe(0)
        ->and(ImportBatch::where('token', $token)->firstOrFail()->status)->toBe(ImportBatch::STATUS_READY)
        ->and(ImportBatch::where('token', $token)->firstOrFail()->committed_rows)->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Mode update
// ─────────────────────────────────────────────────────────────────────────────

test('update mode changes only matched rows and leaves decoys untouched', function () {
    $target = Student::create([
        'name' => 'Alpha Synthetic',
        'nis' => '001001',
        'nik' => '0012345678901234',
        'version' => 1,
    ]);
    $decoy = Student::create([
        'name' => 'Beta Synthetic',
        'nis' => '002002',
        'nik' => '0022345678901234',
        'version' => 1,
    ]);

    $response = ($this->upload)(
        s05aCsv([s05aRow(['NIS' => '001001', 'NIK' => '0012345678901234', 'Name' => 'Alpha Synthetic Updated'])]),
        'update'
    );

    $response->assertCreated()
        ->assertJsonPath('data.mode', 'update')
        ->assertJsonPath('data.status', ImportBatch::STATUS_READY)
        ->assertJsonPath('data.preview.0.action', 'update');

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/'.$response->json('data.token').'/commit')->assertStatus(200);

    $target->refresh();
    $decoy->refresh();

    expect($target->name)->toBe('Alpha Synthetic Updated')
        ->and($target->version)->toBe(2)
        ->and($decoy->name)->toBe('Beta Synthetic')
        ->and($decoy->version)->toBe(1)
        ->and(Student::count())->toBe(2);
});

test('update mode rejects rows without a match or with an archived match', function () {
    $archived = Student::create(['name' => 'Archived Synthetic', 'nis' => '003003']);
    $archived->delete();

    $response = ($this->upload)(s05aCsv([
        s05aRow(['NIS' => '004004', 'NIK' => '', 'NISN' => '', 'Name' => 'No Match']),
        s05aRow(['NIS' => '003003', 'NIK' => '', 'NISN' => '', 'Name' => 'Archived Match']),
    ]), 'update');

    $response->assertCreated()
        ->assertJsonPath('data.status', ImportBatch::STATUS_INVALID)
        ->assertJsonPath('data.invalid_rows', 2);

    $messages = collect($response->json('data.errors'))->pluck('message')->implode(' ');

    expect($messages)->toContain('No student matches the provided NIS/NIK')
        ->and($messages)->toContain('archived');

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/imports/'.$response->json('data.token').'/commit')->assertStatus(409);

    expect(Student::withTrashed()->count())->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// Izin
// ─────────────────────────────────────────────────────────────────────────────

test('import requires students.import', function () {
    $csv = s05aCsv([s05aRow()]);

    Sanctum::actingAs($this->plainUser);
    $this->post('/api/imports/students', [
        'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        'mode' => 'insert',
    ], ['Accept' => 'application/json'])->assertStatus(403);

    Sanctum::actingAs($this->principal);
    $this->post('/api/imports/students', [
        'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        'mode' => 'insert',
    ], ['Accept' => 'application/json'])->assertStatus(403);

    expect(ImportBatch::count())->toBe(0);
});

test('operator and system administrator may start an import', function () {
    ($this->upload)(s05aCsv([s05aRow()]))->assertCreated();

    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);
    $this->post('/api/imports/students', [
        'file' => UploadedFile::fake()->createWithContent('students.csv', s05aCsv([s05aRow(['NIK' => '0012345678901235', 'NIS' => '001002', 'NISN' => '0012345679'])])),
        'mode' => 'insert',
    ], ['Accept' => 'application/json'])->assertCreated();

    expect(ImportBatch::count())->toBe(2);
});

test('another user cannot inspect or commit someone else batch', function () {
    $response = ($this->upload)(s05aCsv([s05aRow()]));
    $token = $response->json('data.token');

    $other = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($other);

    $this->getJson("/api/imports/{$token}")->assertStatus(404);
    $this->postJson("/api/imports/{$token}/commit")->assertStatus(404);
    $this->get("/api/imports/{$token}/errors")->assertStatus(404);

    expect(Student::count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Batas file dan baris
// ─────────────────────────────────────────────────────────────────────────────

test('row and size limits reject the file instead of truncating it', function () {
    config(['imports.students.max_rows' => 2]);

    ($this->upload)(s05aCsv([
        s05aRow(),
        s05aRow(['NIK' => '0012345678901235', 'NIS' => '001002', 'NISN' => '0012345679']),
        s05aRow(['NIK' => '0012345678901236', 'NIS' => '001003', 'NISN' => '0012345680']),
    ]))->assertStatus(422);

    expect(ImportBatch::count())->toBe(0);

    config(['imports.students.max_rows' => 2000, 'imports.students.max_size_kb' => 1]);

    $large = s05aCsv([s05aRow(['Address' => str_repeat('A', 2048)])]);
    ($this->upload)($large)->assertStatus(422);

    expect(ImportBatch::count())->toBe(0)
        ->and(Student::count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Keamanan laporan error & kedaluwarsa
// ─────────────────────────────────────────────────────────────────────────────

test('error report neutralizes spreadsheet formulas', function () {
    $response = ($this->upload)(s05aCsv([
        s05aRow(['Name' => '', 'NIS' => '=cmd|\' /C calc\'!A0', 'NIK' => '']),
    ]));

    $token = $response->json('data.token');

    Sanctum::actingAs($this->operator);
    $download = $this->get("/api/imports/{$token}/errors");

    $download->assertStatus(200);
    $csv = $download->streamedContent();

    expect($csv)->toContain("'=cmd|");
});

test('expired batches and changed source files are rejected at commit', function () {
    $expired = ($this->upload)(s05aCsv([s05aRow()]))->json('data.token');
    ImportBatch::where('token', $expired)->firstOrFail()
        ->forceFill(['created_at' => now()->subHours(3)])
        ->save();

    Sanctum::actingAs($this->operator);
    $this->postJson("/api/imports/{$expired}/commit")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This import batch has expired. Upload the file again to continue.');

    $tampered = ($this->upload)(s05aCsv([s05aRow(['NIS' => '001002', 'NIK' => '0012345678901235', 'NISN' => '0012345679'])]))->json('data.token');
    Storage::disk('local')->put(
        "imports/{$tampered}/source.csv",
        s05aCsv([s05aRow(['NIS' => '001002', 'NIK' => '0012345678901235', 'NISN' => '0012345679', 'Name' => 'Tampered'])])
    );

    $this->postJson("/api/imports/{$tampered}/commit")
        ->assertStatus(409)
        ->assertJsonPath('message', 'The stored source file no longer matches this batch. Upload the file again.');

    expect(Student::count())->toBe(0);
});
