<?php

use App\Models\Student;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

// ─────────────────────────────────────────────────────────────────────────────
// Autentikasi & izin
// ─────────────────────────────────────────────────────────────────────────────

test('guest cannot export students', function () {
    $data = $this->seedStudentData();

    $this->getJson("/api/students/export?school_year_id={$data['school_year_id']}")
        ->assertStatus(401);
});

test('inactive account cannot export students', function () {
    $this->seedStudentData();
    $actor = $this->makeUserWithRole('operator-sekolah', ['status_active' => false]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/students/export?school_year_id=1')->assertStatus(403);
});

test('user without students.view cannot export students', function () {
    $data = $this->seedStudentData();
    // wakil-kesiswaan punya students.view; buat peran tanpa students.view.
    $role = $this->role('operator-sekolah');
    $role->abilities()->detach(
        \App\Models\Ability::where('name', 'students.view')->pluck('id')->all()
    );
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $this->getJson("/api/students/export?school_year_id={$data['school_year_id']}")
        ->assertStatus(403);
});

test('user with students.view but without students.export is denied', function () {
    $data = $this->seedStudentData();
    $role = $this->role('operator-sekolah');
    $role->abilities()->detach(
        \App\Models\Ability::where('name', 'students.export')->pluck('id')->all()
    );
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    // Bisa melihat daftar?
    // (route students.index butuh students.view saja)
    $this->getJson("/api/students/export?school_year_id={$data['school_year_id']}")
        ->assertStatus(403);
});

test('user with both abilities can export students csv', function () {
    $data = $this->seedStudentData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $response = $this->get("/api/students/export?school_year_id={$data['school_year_id']}");

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    // Header kolom eksplisit.
    expect($csv)->toContain('Name')
        ->toContain('Gender')
        ->toContain('NIK')
        ->toContain('NIS')
        ->toContain('NISN')
        ->toContain('Class')
        ->toContain('Student Entry')
        ->toContain('Student Status');

    $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
    expect(str_getcsv($lines[0]))->toBe([
        'Name', 'Gender', 'Birthplace', 'Birthdate', 'NIK', 'NIS', 'NISN',
        'Class', 'Student Entry', 'Student Status',
    ]);
    $row = str_getcsv($lines[1]);
    expect($row)->toHaveCount(10)
        ->and($row[1])->toBe('Male')
        ->and($row[7])->toBe('Rombel UAT 1')
        ->and($row[8])->toBe('New Student')
        ->and($row[9])->toBe('Active');

    // Data siswa tahun ini ada; siswa tahun lain tidak.
    expect($csv)->toContain('Synthetic Student 1')
        ->not->toContain('Synthetic Student Other Year');
});

// ─────────────────────────────────────────────────────────────────────────────
// Leading zero & nilai identitas sebagai teks
// ─────────────────────────────────────────────────────────────────────────────

test('export preserves leading zeros in identity numbers', function () {
    $data = $this->seedStudentData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $csv = $this->get("/api/students/export?school_year_id={$data['school_year_id']}")
        ->streamedContent();

    // NIK sintetis '0' + 15 digit → 16 karakter, leading zero dipertahankan.
    expect($csv)->toContain('0000000000000001');
    // NIS '001' dipertahankan.
    expect($csv)->toContain('001');
});

// ─────────────────────────────────────────────────────────────────────────────
// CSV escaping & formula injection
// ─────────────────────────────────────────────────────────────────────────────

test('export escapes commas, quotes and newlines without data loss', function () {
    $data = $this->seedStudentData(0);
    $level = \App\Models\SchoolLevel::first();
    $year = \App\Models\SchoolYear::where('id', $data['school_year_id'])->first();

    $student = Student::create([
        'name' => 'Name, with "quotes" and'.PHP_EOL.'newline',
        'nik' => '0888888888888888',
    ]);
    \App\Models\StudentRombel::create([
        'student_nik' => $student->nik,
        'school_rombel' => \App\Models\SchoolRombel::where('school_year_id', $year->id)->first()->id,
    ]);

    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $csv = $this->get("/api/students/export?school_year_id={$data['school_year_id']}")
        ->streamedContent();

    // Kutip ganda di-escape (RFC 4180: dikalikan) dan baris tetap utuh.
    expect($csv)->toContain('"Name, with ""quotes"" and');
});

test('export neutralizes csv formula injection', function () {
    $data = $this->seedStudentData(0);
    $year = \App\Models\SchoolYear::where('id', $data['school_year_id'])->first();

    $student = Student::create([
        'name' => '=HYPERLINK("http://evil.example")',
        'nik' => '0777777777777777',
    ]);
    \App\Models\StudentRombel::create([
        'student_nik' => $student->nik,
        'school_rombel' => \App\Models\SchoolRombel::where('school_year_id', $year->id)->first()->id,
    ]);

    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $csv = $this->get("/api/students/export?school_year_id={$data['school_year_id']}")
        ->streamedContent();

    // Sel yang diawali '=' diberi prefix apostrof agar tidak dieksekusi spreadsheet.
    expect($csv)->toContain("'=HYPERLINK");
});

// ─────────────────────────────────────────────────────────────────────────────
// Filter: hanya tahun ajaran terpilih + search
// ─────────────────────────────────────────────────────────────────────────────

test('export is scoped to the requested school year', function () {
    $data = $this->seedStudentData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $csv = $this->get("/api/students/export?school_year_id={$data['other_school_year_id']}")
        ->streamedContent();

    expect($csv)->toContain('Synthetic Student Other Year')
        ->not->toContain('Synthetic Student 1');
});

test('export requires a valid school year id', function () {
    $this->seedStudentData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $this->getJson('/api/students/export')->assertStatus(422);
    $this->getJson('/api/students/export?school_year_id=999999')->assertStatus(422);
});

// ─────────────────────────────────────────────────────────────────────────────
// Batas baris: ditolak bila melebihi, bukan dipotong diam-diam
// ─────────────────────────────────────────────────────────────────────────────

test('export is rejected when the filtered result exceeds the row limit', function () {
    // Turunkan batas agar mudah diuji.
    config(['students.export_max_rows' => 2]);

    $data = $this->seedStudentData(3); // 3 siswa pada tahun ajaran terpilih
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/students/export?school_year_id={$data['school_year_id']}");

    $response->assertStatus(422);
    $message = $response->json('message');
    expect($message)->toContain('exceeds the limit')
        ->toContain('2')
        ->toContain('narrow your filters');
});

test('export succeeds when the filtered result is within the row limit', function () {
    config(['students.export_max_rows' => 3]);

    $data = $this->seedStudentData(3);
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $response = $this->get("/api/students/export?school_year_id={$data['school_year_id']}");

    $response->assertStatus(200);

    // Semua baris hasil filter ada (tidak dipotong).
    $csv = $response->streamedContent();
    expect($csv)->toContain('Synthetic Student 1')
        ->toContain('Synthetic Student 2')
        ->toContain('Synthetic Student 3');
});

// ─────────────────────────────────────────────────────────────────────────────
// Penetapan peran manual & pemetaan legacy
// ─────────────────────────────────────────────────────────────────────────────

test('export ability revoke applies on the next request in the same session', function () {
    $data = $this->seedStudentData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $this->get("/api/students/export?school_year_id={$data['school_year_id']}")
        ->assertStatus(200);

    // Admin mencabut students.export.
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);
    $role = $this->role('operator-sekolah');
    $this->patchJson("/api/roles/{$role->id}", [
        'abilities' => $role->abilities()->pluck('name')->reject(fn ($n) => $n === 'students.export')->values()->all(),
    ])->assertStatus(200);

    // Sesi actor yang sama: request berikutnya ditolak.
    $fresh = \App\Models\User::find($actor->id);
    Sanctum::actingAs($fresh);
    $this->getJson("/api/students/export?school_year_id={$data['school_year_id']}")
        ->assertStatus(403);
});
