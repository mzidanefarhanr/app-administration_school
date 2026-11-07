<?php

use App\Models\AcademicSetting;
use App\Models\District;
use App\Models\EducationLevel;
use App\Models\EducationSchool;
use App\Models\Gender;
use App\Models\Province;
use App\Models\Regency;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEntry;
use App\Models\StudentMutation;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 15, 9, 0, 0, 'Asia/Jakarta'));

    $this->seedAccessReferenceData();
    $this->operator = $this->makeUserWithRole('operator-sekolah');

    StudentStatus::insertOrIgnore([
        ['id' => 1, 'name' => 'Aktif', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Mutasi Keluar', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 6, 'name' => 'Lulus', 'created_at' => now(), 'updated_at' => now()],
    ]);

    StudentEntry::insertOrIgnore([
        ['id' => 1, 'name' => 'Siswa Baru', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Mutasi Masuk', 'created_at' => now(), 'updated_at' => now()],
    ]);

    Gender::insertOrIgnore([
        ['id' => 1, 'name' => 'Laki-Laki', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Perempuan', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->year = SchoolYear::create([
        'name' => '2024/2025',
        'start_date' => '2024-07-10',
        'end_date' => '2025-07-10',
    ]);
});

test('report endpoint returns structured data for valid school year', function () {
    Sanctum::actingAs($this->operator);

    $response = $this->getJson("/api/academic/mutations/report?school_year_id={$this->year->id}&month=11&year=2026");

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'school_year',
                'period',
                'school_profile',
                'mutations_in',
                'mutations_out',
                'rombels_start',
                'rombels_end',
                'summary_in',
                'summary_out',
                'totals_start',
                'totals_end',
                'signature',
            ],
        ]);
});

test('export excel endpoint streams xlsx file with correct headers', function () {
    Sanctum::actingAs($this->operator);

    $response = $this->get("/api/academic/mutations/report/excel?school_year_id={$this->year->id}&month=11&year=2026");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
});

test('import template endpoint streams xlsx and csv correctly', function () {
    Sanctum::actingAs($this->operator);

    $responseXlsx = $this->get('/api/academic/mutations/import/template?format=xlsx&lang=id');
    $responseXlsx->assertOk();
    expect($responseXlsx->headers->get('content-type'))->toContain('spreadsheetml');

    $responseCsv = $this->get('/api/academic/mutations/import/template?format=csv&lang=en');
    $responseCsv->assertOk();
    expect($responseCsv->headers->get('content-type'))->toContain('text/csv');
});
