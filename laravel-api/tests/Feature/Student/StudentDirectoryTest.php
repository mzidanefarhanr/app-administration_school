<?php

use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

/**
 * Fixture sintetis 03c: daftar/rekap/ekspor server-side.
 */
beforeEach(function () {
    $this->level = SchoolLevel::create(['name' => 'Grade UAT']);
    $this->year = SchoolYear::create(['name' => '2030/2031']);
    $this->otherYear = SchoolYear::create(['name' => '2029/2030']);

    $this->rombel = SchoolRombel::create([
        'school_level_id' => $this->level->id,
        'school_year_id' => $this->year->id,
        'name' => 'UAT-10A',
    ]);
    $this->rombel2 = SchoolRombel::create([
        'school_level_id' => $this->level->id,
        'school_year_id' => $this->year->id,
        'name' => 'UAT-10B',
    ]);
    $this->otherRombel = SchoolRombel::create([
        'school_level_id' => $this->level->id,
        'school_year_id' => $this->otherYear->id,
        'name' => 'UAT-OLD',
    ]);

    $this->male = \App\Models\Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $this->female = \App\Models\Gender::firstOrCreate(['name' => 'Perempuan']);
    $this->activeStatus = StudentStatus::firstOrCreate(['id' => 1], ['name' => 'Aktif']);
    $this->inactiveStatus = StudentStatus::firstOrCreate(['id' => 2], ['name' => 'Mutasi Keluar']);

    $this->makeStudent = function (array $attrs = []) {
        static $seq = 0;
        $seq++;

        return Student::create(array_merge([
            'name' => 'UAT Student '.$seq,
            'nik' => '0'.str_pad((string) $seq, 15, '0', STR_PAD_LEFT),
            'nis' => '00'.$seq,
            'nisn' => '000'.$seq,
            'gender_id' => $this->male->id,
        ], $attrs));
    };

    $this->place = function (Student $student, $rombel, array $attrs = []) {
        return StudentRombel::create(array_merge([
            'student_id' => $student->id,
            'student_nik' => $student->nik,
            'school_rombel' => $rombel->id,
            'student_status_id' => $this->activeStatus->id,
        ], $attrs));
    };

    $this->viewer = $this->makeUserWithRole('kepala-sekolah');
    $this->manager = $this->makeUserWithRole('operator-sekolah');
});

function sdList($test, array $params = [])
{
    $query = http_build_query(array_merge([
        'school_year_id' => $test->year->id,
    ], $params));

    return $test->getJson('/api/students?'.$query);
}

// ─────────────────────────────────────────────────────────────────────────────
// Autorisasi
// ─────────────────────────────────────────────────────────────────────────────

test('guest is denied the student list with 401', function () {
    sdList($this)->assertStatus(401);
});

test('user without students.view is denied with 403', function () {
    Sanctum::actingAs(User::factory()->create(['status_active' => true]));

    sdList($this)->assertStatus(403);
});

test('user with students.view can read list and recap', function () {
    $student = ($this->makeStudent)();
    ($this->place)($student, $this->rombel);

    Sanctum::actingAs($this->viewer);

    $response = sdList($this)->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('recap.total_matches'))->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// Validasi parameter (422 English)
// ─────────────────────────────────────────────────────────────────────────────

test('list requires a valid school year', function () {
    Sanctum::actingAs($this->viewer);

    $this->getJson('/api/students')->assertStatus(422)
        ->assertJsonValidationErrors('school_year_id');
    $this->getJson('/api/students?school_year_id=999999')->assertStatus(422);
});

test('list rejects invalid pagination, sort, direction, placement and rombel', function () {
    Sanctum::actingAs($this->viewer);
    $base = '/api/students?school_year_id='.$this->year->id;

    $this->getJson($base.'&per_page=101')->assertStatus(422)->assertJsonValidationErrors('per_page');
    $this->getJson($base.'&per_page=25')->assertStatus(422);
    $this->getJson($base.'&sort=nik')->assertStatus(422)->assertJsonValidationErrors('sort');
    $this->getJson($base.'&direction=sideways')->assertStatus(422)->assertJsonValidationErrors('direction');
    $this->getJson($base.'&placement=bogus')->assertStatus(422)->assertJsonValidationErrors('placement');
    $this->getJson($base.'&rombel='.$this->otherRombel->id)->assertStatus(422)->assertJsonValidationErrors('rombel');
    $this->getJson($base.'&plane=1')->assertOk();
});

test('unplaced combined with rombel is rejected 422', function () {
    Sanctum::actingAs($this->viewer);

    $this->getJson('/api/students?school_year_id='.$this->year->id
        .'&placement=unplaced&rombel='.$this->rombel->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors('placement');
});

// ─────────────────────────────────────────────────────────────────────────────
// Paginasi, sorting, pencarian
// ─────────────────────────────────────────────────────────────────────────────

test('pagination defaults to 20 and accepts 10/20/50/100', function () {
    for ($i = 0; $i < 25; $i++) {
        ($this->place)(($this->makeStudent)(), $this->rombel);
    }

    Sanctum::actingAs($this->viewer);

    $default = sdList($this)->assertOk()->json();
    expect($default['meta']['per_page'])->toBe(20)
        ->and($default['data'])->toHaveCount(20)
        ->and($default['meta']['total'])->toBe(25)
        ->and($default['meta']['last_page'])->toBe(2);

    $second = sdList($this, ['page' => 2])->assertOk()->json();
    expect($second['data'])->toHaveCount(5);

    foreach ([10, 20, 50, 100] as $perPage) {
        $json = sdList($this, ['per_page' => $perPage])->assertOk()->json();
        expect($json['meta']['per_page'])->toBe($perPage);
    }
});

test('sorting is allowlisted and stable with id as tiebreaker', function () {
    $a = ($this->makeStudent)(['name' => 'Same Name', 'nis' => '001']);
    $b = ($this->makeStudent)(['name' => 'Same Name', 'nis' => '002']);
    $c = ($this->makeStudent)(['name' => 'Same Name', 'nis' => '003']);
    foreach ([$a, $b, $c] as $student) {
        ($this->place)($student, $this->rombel);
    }

    Sanctum::actingAs($this->viewer);

    $ids = collect(sdList($this, ['sort' => 'name', 'direction' => 'desc'])->assertOk()->json('data'))
        ->pluck('id')->all();

    expect($ids)->toBe([$a->id, $b->id, $c->id]);

    $byNis = collect(sdList($this, ['sort' => 'nis', 'direction' => 'asc'])->assertOk()->json('data'))
        ->pluck('nis')->all();
    expect($byNis)->toBe(['001', '002', '003']);
});

test('search matches name, nis and nisn without exposing nik', function () {
    $target = ($this->makeStudent)(['name' => 'Zebra Unique', 'nis' => '0777', 'nisn' => '0888']);
    ($this->place)($target, $this->rombel);
    ($this->place)(($this->makeStudent)(['name' => 'Other Student']), $this->rombel);

    Sanctum::actingAs($this->viewer);

    expect(sdList($this, ['search' => 'zebra'])->json('data'))->toHaveCount(1)
        ->and(sdList($this, ['search' => '0777'])->json('data'))->toHaveCount(1)
        ->and(sdList($this, ['search' => '0888'])->json('data'))->toHaveCount(1)
        ->and(sdList($this, ['search' => 'nomatch'])->json('data'))->toHaveCount(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Definisi All / Placed / Unplaced & anti double-count
// ─────────────────────────────────────────────────────────────────────────────

test('all includes new students without rombel while placed and unplaced split them', function () {
    $placed = ($this->makeStudent)(['name' => 'Placed Student']);
    ($this->place)($placed, $this->rombel);
    $newcomer = ($this->makeStudent)(['name' => 'Newcomer No Rombel']);

    Sanctum::actingAs($this->viewer);

    $all = sdList($this, ['placement' => 'all'])->assertOk()->json();
    expect(collect($all['data'])->pluck('name')->all())
        ->toContain('Placed Student')
        ->toContain('Newcomer No Rombel')
        ->and($all['recap']['placed_count'])->toBe(1)
        ->and($all['recap']['unplaced_count'])->toBe(1);

    $placedOnly = sdList($this, ['placement' => 'placed'])->json();
    expect(collect($placedOnly['data'])->pluck('id')->all())->toBe([$placed->id])
        ->and($placedOnly['recap']['total_matches'])->toBe(1);

    $unplacedOnly = sdList($this, ['placement' => 'unplaced'])->json();
    expect(collect($unplacedOnly['data'])->pluck('id')->all())->toBe([$newcomer->id])
        ->and($unplacedOnly['recap']['total_matches'])->toBe(1)
        ->and($unplacedOnly['recap']['by_rombel'])->toBe([]);
});

test('cross-year history, inactive membership and legacy rows do not double count', function () {
    $student = ($this->makeStudent)(['name' => 'History Student']);

    // Legacy aktif tahun terpilih (student_id null, cocok via NIK).
    StudentRombel::create([
        'student_nik' => $student->nik,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->activeStatus->id,
    ]);
    // Riwayat nonaktif pada tahun terpilih (tidak dihitung aktif).
    ($this->place)($student, $this->rombel2, [
        'student_status_id' => $this->inactiveStatus->id,
        'latest_student_exit_date' => '2029-06-30',
    ]);
    // Riwayat tahun lain.
    StudentRombel::create([
        'student_id' => $student->id,
        'student_nik' => $student->nik,
        'school_rombel' => $this->otherRombel->id,
        'student_status_id' => $this->activeStatus->id,
    ]);

    Sanctum::actingAs($this->viewer);

    $json = sdList($this)->assertOk()->json();

    expect($json['data'])->toHaveCount(1)
        ->and($json['data'][0]['rombel']['name'])->toBe('UAT-10A')
        ->and($json['recap']['placed_count'])->toBe(1)
        ->and($json['recap']['unplaced_count'])->toBe(0)
        ->and($json['recap']['by_rombel'])->toHaveCount(1)
        ->and($json['recap']['by_rombel'][0]['total'])->toBe(1);
});

test('student with only an inactive membership this year is unplaced', function () {
    $student = ($this->makeStudent)();
    ($this->place)($student, $this->rombel, [
        'student_status_id' => $this->inactiveStatus->id,
        'latest_student_exit_date' => '2029-06-30',
    ]);

    Sanctum::actingAs($this->viewer);

    $unplaced = sdList($this, ['placement' => 'unplaced'])->assertOk()->json();
    expect(collect($unplaced['data'])->pluck('id')->all())->toBe([$student->id])
        ->and($unplaced['data'][0]['rombel'])->toBeNull();
});

test('archived students never appear in list or recap', function () {
    $kept = ($this->makeStudent)();
    ($this->place)($kept, $this->rombel);
    $archived = ($this->makeStudent)();
    ($this->place)($archived, $this->rombel);
    $archived->delete();

    Sanctum::actingAs($this->viewer);

    $json = sdList($this)->assertOk()->json();
    expect(collect($json['data'])->pluck('id')->all())->toBe([$kept->id])
        ->and($json['recap']['total_matches'])->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// Serialisasi minimum & rekap lintas halaman
// ─────────────────────────────────────────────────────────────────────────────

test('list exposes only the agreed attributes and no sensitive fields', function () {
    $student = ($this->makeStudent)(['name' => 'Privacy Student', 'history_illness' => 'Synthetic illness']);
    ($this->place)($student, $this->rombel);

    Sanctum::actingAs($this->viewer);

    $response = sdList($this)->assertOk();
    $item = $response->json('data.0');

    expect(array_keys($item))->toEqualCanonicalizing(
        ['id', 'version', 'name', 'nis', 'nisn', 'gender', 'rombel', 'placement']
    );

    $raw = $response->getContent();
    foreach (['nik', 'birthdate', 'birthplace', 'address', 'hp_num', 'history_illness', 'income', 'archive_reason'] as $sensitive) {
        expect($raw)->not->toContain('"'.$sensitive.'"');
    }
});

test('recap is computed from the whole filter and does not change across pages', function () {
    for ($i = 0; $i < 12; $i++) {
        $student = ($this->makeStudent)(['name' => 'Placed '.str_pad((string) $i, 2, '0')]);
        ($this->place)($student, $i % 2 === 0 ? $this->rombel : $this->rombel2);
    }
    for ($i = 0; $i < 8; $i++) {
        ($this->makeStudent)(['name' => 'Unplaced '.str_pad((string) $i, 2, '0')]);
    }

    Sanctum::actingAs($this->viewer);

    $page1 = sdList($this, ['per_page' => 10, 'page' => 1])->assertOk()->json();
    $page2 = sdList($this, ['per_page' => 10, 'page' => 2])->assertOk()->json();

    expect($page1['recap'])->toBe($page2['recap'])
        ->and($page1['recap']['total_matches'])->toBe(20)
        ->and($page1['recap']['placed_count'])->toBe(12)
        ->and($page1['recap']['unplaced_count'])->toBe(8)
        ->and($page1['recap']['by_gender'][0]['total'])->toBe(20)
        ->and(collect($page1['recap']['by_rombel'])->sum('total'))->toBe(12);
});

// ─────────────────────────────────────────────────────────────────────────────
// Ekspor: filter sama, seluruh hasil, unplaced, izin, batas
// ─────────────────────────────────────────────────────────────────────────────

test('export follows search, rombel and placement across all pages', function () {
    for ($i = 0; $i < 15; $i++) {
        $student = ($this->makeStudent)(['name' => 'Export Placed '.str_pad((string) $i, 2, '0')]);
        ($this->place)($student, $i < 10 ? $this->rombel : $this->rombel2);
    }
    $unplaced = ($this->makeStudent)(['name' => 'Export Unplaced']);
    ($this->place)(($this->makeStudent)(['name' => 'Export Other']), $this->rombel);

    Sanctum::actingAs($this->manager);

    $csv = $this->get('/api/students/export?school_year_id='.$this->year->id.'&placement=all')
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Export Placed 00')
        ->toContain('Export Placed 14')
        ->toContain('Export Unplaced')
        ->toContain('Export Other');

    $rombelFiltered = $this->get('/api/students/export?school_year_id='.$this->year->id
        .'&placement=all&rombel='.$this->rombel->id)
        ->assertOk()
        ->streamedContent();
    expect($rombelFiltered)->toContain('Export Placed 00')
        ->not->toContain('Export Placed 14')
        ->not->toContain('Export Unplaced');

    $unplacedCsv = $this->get('/api/students/export?school_year_id='.$this->year->id.'&placement=unplaced')
        ->assertOk()
        ->streamedContent();
    expect($unplacedCsv)->toContain('Export Unplaced')
        ->not->toContain('Export Placed 00');
    // Baris unplaced tidak mengarang rombel.
    expect($unplacedCsv)->not->toContain('UAT-10A');
});

test('export requires students.export and rejects results over the configured limit', function () {
    ($this->place)(($this->makeStudent)(), $this->rombel);
    ($this->place)(($this->makeStudent)(), $this->rombel);

    // Viewer dengan students.view tetapi tanpa students.export.
    $this->role('kepala-sekolah')->abilities()->detach(
        \App\Models\Ability::where('name', 'students.export')->pluck('id')->all()
    );
    Sanctum::actingAs($this->makeUserWithRole('kepala-sekolah'));
    $this->getJson('/api/students/export?school_year_id='.$this->year->id)->assertStatus(403);

    Sanctum::actingAs($this->manager);
    config(['students.export_max_rows' => 1]);
    $this->getJson('/api/students/export?school_year_id='.$this->year->id)
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'exceeds the limit'));
});

test('export defaults to placed and stays scoped to the selected year', function () {
    $current = ($this->makeStudent)(['name' => 'Current Year Student']);
    ($this->place)($current, $this->rombel);
    $past = ($this->makeStudent)(['name' => 'Past Year Student']);
    ($this->place)($past, $this->otherRombel);

    Sanctum::actingAs($this->manager);

    $csv = $this->get('/api/students/export?school_year_id='.$this->year->id)
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Current Year Student')
        ->not->toContain('Past Year Student');
});

// ─────────────────────────────────────────────────────────────────────────────
// Kompatibilitas 03b: version dari daftar → update/archive/restore
// ─────────────────────────────────────────────────────────────────────────────

test('archived list is limited to students.manage and hidden from view-only users', function () {
    $student = ($this->makeStudent)();
    $student->delete();

    Sanctum::actingAs($this->viewer);
    $this->getJson('/api/students?only_archived=1')->assertStatus(403);

    Sanctum::actingAs($this->manager);
    $archived = $this->getJson('/api/students?only_archived=1')->assertOk()->json('data');
    expect(collect($archived)->pluck('id')->all())->toBe([$student->id]);
});

test('version from the list drives update, archive and restore (03b compatibility)', function () {
    $student = ($this->makeStudent)(['name' => 'Compat Student']);
    ($this->place)($student, $this->rombel);

    Sanctum::actingAs($this->manager);

    $row = sdList($this)->assertOk()->json('data.0');
    expect($row['version'])->toBe(1);

    $this->putJson('/api/students/'.$row['id'], [
        'name' => 'Compat Student Updated',
        'version' => $row['version'],
    ])->assertOk();

    $row = sdList($this)->assertOk()->json('data.0');
    expect($row['name'])->toBe('Compat Student Updated')
        ->and($row['version'])->toBe(2);

    $this->deleteJson('/api/students/'.$row['id'], [
        'version' => $row['version'],
        'archive_reason' => 'UAT synthetic archive',
    ])->assertOk();

    expect(sdList($this)->assertOk()->json('data'))->toHaveCount(0);

    $archived = $this->getJson('/api/students?only_archived=1')->assertOk()->json('data');
    expect(collect($archived)->pluck('id')->all())->toBe([$row['id']]);

    $this->postJson('/api/students/'.$row['id'].'/restore', ['version' => $row['version'] + 1])
        ->assertOk();

    $restored = sdList($this)->assertOk()->json('data.0');
    expect($restored['id'])->toBe($row['id'])
        ->and($restored['placement'])->toBe('unplaced');
});
