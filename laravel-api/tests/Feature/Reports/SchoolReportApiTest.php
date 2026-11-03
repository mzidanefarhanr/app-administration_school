<?php

use App\Models\Ability;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\Gender;
use App\Models\Religion;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

beforeEach(function () {
    $this->seedAccessReferenceData();
});

// Helper untuk membuat struktur tahun, rombel, dan siswa
function createReportTestData(): array
{
    $level = SchoolLevel::firstOrCreate(['name' => 'Level 10']);
    $sy1 = SchoolYear::firstOrCreate(['name' => '2024/2025'], ['start_date' => '2024-07-15', 'end_date' => '2025-06-20']);
    $sy2 = SchoolYear::firstOrCreate(['name' => '2025/2026'], ['start_date' => '2025-07-15', 'end_date' => '2026-06-20']);

    $genderL = Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $genderP = Gender::firstOrCreate(['name' => 'Perempuan']);
    $relIslam = Religion::firstOrCreate(['name' => 'Islam']);
    $relKristen = Religion::firstOrCreate(['name' => 'Kristen']);

    $statusAktif = StudentStatus::firstOrCreate(['name' => 'Aktif']);

    $rombelA = SchoolRombel::firstOrCreate([
        'name' => 'X-A',
        'school_year_id' => $sy2->id,
        'school_level_id' => $level->id,
    ]);

    $rombelB = SchoolRombel::firstOrCreate([
        'name' => 'X-B',
        'school_year_id' => $sy2->id,
        'school_level_id' => $level->id,
    ]);

    // Siswa 1: Lengkap NIK, NISN, KK, Ibu (Laki-laki, Islam, X-A)
    $s1 = Student::create([
        'name' => 'Ahmad Dahlan',
        'nik' => '3201010101010001',
        'nisn' => '0012345678',
        'nis' => '1001',
        'kk' => '3201010101019999',
        'mother_name' => 'Siti Aminah',
        'birthplace' => 'Jakarta',
        'birthdate' => '2008-05-10',
        'gender_id' => $genderL->id,
        'religion_id' => $relIslam->id,
    ]);

    StudentRombel::create([
        'student_id' => $s1->id,
        'student_nik' => $s1->nik,
        'school_rombel' => $rombelA->id,
        'student_status_id' => $statusAktif->id,
    ]);

    // Siswa 2: Kurang KK & NISN pendek (Perempuan, Kristen, X-B)
    $s2 = Student::create([
        'name' => 'Bunga Citra',
        'nik' => '3201010101010002',
        'nisn' => '12345', // tidak 10 digit
        'nis' => '1002',
        'kk' => null,
        'mother_name' => 'Maria',
        'birthplace' => 'Bandung',
        'birthdate' => '2009-08-20',
        'gender_id' => $genderP->id,
        'religion_id' => $relKristen->id,
    ]);

    StudentRombel::create([
        'student_id' => $s2->id,
        'student_nik' => $s2->nik,
        'school_rombel' => $rombelB->id,
        'student_status_id' => $statusAktif->id,
    ]);

    // PTK
    $guruType = EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $staffType = EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);

    Employee::factory()->create([
        'name' => 'Guru Budiman',
        'nik' => '3201010101018888',
        'employee_type_id' => $guruType->id,
    ]);

    Employee::factory()->create([
        'name' => 'Staf Tata Usaha',
        'nik' => '3201010101017777',
        'employee_type_id' => $staffType->id,
    ]);

    return [
        'sy1' => $sy1,
        'sy2' => $sy2,
        's1' => $s1,
        's2' => $s2,
        'rombelA' => $rombelA,
        'rombelB' => $rombelB,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// T1: GET /api/reports/school-statistics returns 200 with structured trends array
// ─────────────────────────────────────────────────────────────────────────────
test('T1: authenticated operator can get school statistics trends', function () {
    $data = createReportTestData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/reports/school-statistics');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'years',
                'charts' => [
                    'labels',
                    'students' => ['male', 'female', 'total'],
                    'mutations' => ['incoming', 'outgoing'],
                    'employees' => ['teachers', 'staff'],
                ],
            ],
        ]);

    expect($response->json('data.years'))->toBeArray();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2: GET /api/reports/school-statistics?limit_years=3 limits returned years to 3
// ─────────────────────────────────────────────────────────────────────────────
test('T2: limit_years parameter controls number of years returned', function () {
    createReportTestData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/reports/school-statistics?limit_years=1');

    $response->assertStatus(200);
    $years = $response->json('data.years');
    expect(count($years))->toBeLessThanOrEqual(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// T3: GET /api/reports/demographics returns 200 with demographics breakdown
// ─────────────────────────────────────────────────────────────────────────────
test('T3: get demographics breakdown by gender, religion, level, and age', function () {
    $data = createReportTestData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/reports/demographics?school_year_id={$data['sy2']->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'school_year' => ['id', 'name'],
                'gender' => ['male', 'female', 'total', 'male_percent', 'female_percent'],
                'religion',
                'levels',
                'rombels',
                'age_groups',
            ],
        ]);

    $gender = $response->json('data.gender');
    expect($gender['male'])->toBe(1)
        ->and($gender['female'])->toBe(1)
        ->and($gender['total'])->toBe(2);
});

// ─────────────────────────────────────────────────────────────────────────────
// T4: GET /api/reports/dapodik-readiness returns readiness audit metrics
// ─────────────────────────────────────────────────────────────────────────────
test('T4: get dapodik readiness audit metrics', function () {
    $data = createReportTestData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/reports/dapodik-readiness?school_year_id={$data['sy2']->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'school_year' => ['id', 'name'],
                'students' => [
                    'total',
                    'readiness_percent',
                    'valid_nik',
                    'valid_nisn',
                    'has_kk',
                    'has_mother_name',
                    'has_birth_data',
                ],
                'employees' => [
                    'total',
                    'readiness_percent',
                    'valid_nik',
                    'teachers_with_nuptk',
                    'teachers_with_subject',
                ],
            ],
        ]);

    $students = $response->json('data.students');
    expect($students['total'])->toBe(2)
        ->and($students['valid_nik'])->toBe(2)
        ->and($students['valid_nisn'])->toBe(1); // s1 has valid 10-digit NISN, s2 has 5 digits
});

// ─────────────────────────────────────────────────────────────────────────────
// T5: GET /api/reports/school-statistics/export streams CSV with UTF-8 BOM
// ─────────────────────────────────────────────────────────────────────────────
test('T5: export school statistics streams CSV with UTF-8 BOM', function () {
    createReportTestData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $response = $this->get('/api/reports/school-statistics/export?limit_years=3');

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $content = $response->streamedContent();

    // Pastikan BOM UTF-8 (\xEF\xBB\xBF) ada di awal
    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF");
    expect($content)->toContain('Tahun Ajaran');
    expect($content)->toContain('Total Siswa');
});

// ─────────────────────────────────────────────────────────────────────────────
// T6: POST /api/reports/dapodik-reconciliation/compare with valid CSV
// ─────────────────────────────────────────────────────────────────────────────
test('T6: reconcile csv successfully compares local and dapodik data', function () {
    $data = createReportTestData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    // CSV format: Ahmad Dahlan match identical, Bunga Citra matched with diff (nama beda kapital), plus 1 DAPODIK_ONLY
    $csvContent = "nisn,nik,nama,rombel,jenis_kelamin\n"
        ."0012345678,3201010101010001,Ahmad Dahlan,X-A,L\n"
        ."12345,3201010101010002,Bunga Citra Lestari,X-B,P\n" // nama beda -> MATCHED_DIFF
        ."9999999999,3201010101019998,Siswa Dapodik Baru,X-A,L\n"; // hanya di dapodik

    $file = UploadedFile::fake()->createWithContent('dapodik.csv', $csvContent);

    $response = $this->postJson('/api/reports/dapodik-reconciliation/compare', [
        'file' => $file,
        'school_year_id' => $data['sy2']->id,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'summary' => [
                    'total_dapodik_rows',
                    'total_local_students',
                    'total_matched',
                    'total_identical',
                    'total_diff',
                    'total_local_only',
                    'total_dapodik_only',
                ],
                'items',
            ],
        ]);

    $summary = $response->json('data.summary');
    expect($summary['total_identical'])->toBe(1)
        ->and($summary['total_diff'])->toBe(1)
        ->and($summary['total_dapodik_only'])->toBe(1)
        ->and($summary['total_local_only'])->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// T7: POST /api/reports/dapodik-reconciliation/compare validates file
// ─────────────────────────────────────────────────────────────────────────────
test('T7: reconciliation rejects invalid file or missing file', function () {
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/reports/dapodik-reconciliation/compare', []);
    $response->assertStatus(422)->assertJsonValidationErrors(['file']);

    $pngFile = UploadedFile::fake()->image('avatar.png');
    $badResponse = $this->postJson('/api/reports/dapodik-reconciliation/compare', ['file' => $pngFile]);
    $badResponse->assertStatus(422)->assertJsonValidationErrors(['file']);
});

// ─────────────────────────────────────────────────────────────────────────────
// T8: POST /api/reports/dapodik-reconciliation/export returns streamed CSV
// ─────────────────────────────────────────────────────────────────────────────
test('T8: reconciliation export streams CSV with audit differences', function () {
    $data = createReportTestData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $csvContent = "nisn,nik,nama,rombel,jenis_kelamin\n"
        ."0012345678,3201010101010001,Ahmad Dahlan,X-A,L\n";

    $file = UploadedFile::fake()->createWithContent('dapodik.csv', $csvContent);

    $response = $this->post('/api/reports/dapodik-reconciliation/export', [
        'file' => $file,
        'school_year_id' => $data['sy2']->id,
    ]);

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $content = $response->streamedContent();

    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF");
    expect($content)->toContain('Status Rekonsiliasi');
    expect($content)->toContain('Ahmad Dahlan');
});

// ─────────────────────────────────────────────────────────────────────────────
// T9: Reconciliation handles auto-detection of semicolon delimiter
// ─────────────────────────────────────────────────────────────────────────────
test('T9: reconciliation supports semicolon delimited CSV', function () {
    $data = createReportTestData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $csvContent = "nisn;nik;nama;rombel;jenis_kelamin\n"
        ."0012345678;3201010101010001;Ahmad Dahlan;X-A;L\n";

    $file = UploadedFile::fake()->createWithContent('dapodik_semi.csv', $csvContent);

    $response = $this->postJson('/api/reports/dapodik-reconciliation/compare', [
        'file' => $file,
        'school_year_id' => $data['sy2']->id,
    ]);

    $response->assertStatus(200);
    $summary = $response->json('data.summary');
    expect($summary['total_identical'])->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// T10: Reconciliation correctly identifies attribute differences
// ─────────────────────────────────────────────────────────────────────────────
test('T10: reconciliation flags rombel mismatch as difference', function () {
    $data = createReportTestData();
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    // Ahmad Dahlan di lokal rombel X-A, tapi di Dapodik dicatat X-C
    $csvContent = "nisn,nik,nama,rombel,jenis_kelamin\n"
        ."0012345678,3201010101010001,Ahmad Dahlan,X-C,L\n";

    $file = UploadedFile::fake()->createWithContent('dapodik.csv', $csvContent);

    $response = $this->postJson('/api/reports/dapodik-reconciliation/compare', [
        'file' => $file,
        'school_year_id' => $data['sy2']->id,
    ]);

    $response->assertStatus(200);
    $items = $response->json('data.items');
    $firstItem = $items[0];

    expect($firstItem['status'])->toBe('MATCHED_DIFF');
    expect($firstItem['differences'])->not->toBeEmpty();
    expect($firstItem['differences'][0]['field'])->toBe('rombel');
});

// ─────────────────────────────────────────────────────────────────────────────
// T11: Permission guard: students.view required for reports & statistics
// ─────────────────────────────────────────────────────────────────────────────
test('T11: user without students.view receives 403 on statistics endpoints', function () {
    $data = createReportTestData();

    // Guest -> 401
    $this->getJson('/api/reports/school-statistics')->assertStatus(401);

    // Peran tanpa students.view
    $role = $this->role('operator-sekolah');
    $role->abilities()->detach(
        Ability::where('name', 'students.view')->pluck('id')->all()
    );
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $this->getJson('/api/reports/school-statistics')->assertStatus(403);
    $this->getJson('/api/reports/demographics')->assertStatus(403);
    $this->getJson('/api/reports/dapodik-readiness')->assertStatus(403);
    $this->getJson('/api/reports/school-statistics/export')->assertStatus(403);
});

// ─────────────────────────────────────────────────────────────────────────────
// T12: Permission guard: students.import required for reconciliation
// ─────────────────────────────────────────────────────────────────────────────
test('T12: user without students.import receives 403 on reconciliation endpoints', function () {
    $data = createReportTestData();
    $csvContent = "nisn,nik,nama,rombel\n0012345678,3201010101010001,Ahmad,X-A\n";
    $file = UploadedFile::fake()->createWithContent('dapodik.csv', $csvContent);

    // wakil-kesiswaan punya students.view tapi TIDAK punya students.import
    $actor = $this->makeUserWithRole('wakil-kesiswaan');
    Sanctum::actingAs($actor);

    $this->postJson('/api/reports/dapodik-reconciliation/compare', ['file' => $file])
        ->assertStatus(403);

    $this->post('/api/reports/dapodik-reconciliation/export', ['file' => $file])
        ->assertStatus(403);
});
