<?php

use App\Models\Activity;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentProgressionBatch;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    $this->seedAccessReferenceData();

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');

    StudentStatus::insertOrIgnore([
        ['id' => 1, 'name' => 'Aktif', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 6, 'name' => 'Lulus', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->sourceYear = SchoolYear::create([
        'name' => '2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $this->targetYear = SchoolYear::create([
        'name' => '2027/2028',
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);

    $this->levelTen = SchoolLevel::create(['name' => 'Grade 10']);
    $this->levelEleven = SchoolLevel::create(['name' => 'Grade 11']);

    $this->sourceClass = SchoolRombel::create([
        'school_year_id' => $this->sourceYear->id,
        'school_level_id' => $this->levelTen->id,
        'name' => '10-A',
    ]);
    $this->targetClass = SchoolRombel::create([
        'school_year_id' => $this->targetYear->id,
        'school_level_id' => $this->levelEleven->id,
        'name' => '11-A',
    ]);
});

function phase06bStudent(string $name, int $rombelId, int $statusId = 1): array
{
    static $sequence = 0;
    $sequence++;

    $student = Student::create([
        'name' => $name,
        'nik' => '8200000000'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
        'version' => 1,
    ]);

    $membership = StudentRombel::create([
        'student_id' => $student->id,
        'student_nik' => $student->nik,
        'school_rombel' => $rombelId,
        'student_status_id' => $statusId,
        'latest_student_entry_date' => '2026-07-15',
        'version' => 1,
    ]);

    return [$student, $membership];
}

function phase06bDecision(Student $student, StudentRombel $membership, string $decision, ?int $targetRombelId = null): array
{
    return [
        'student_id' => $student->id,
        'membership_id' => $membership->id,
        'expected_student_version' => (int) $student->version,
        'expected_membership_version' => (int) $membership->version,
        'decision' => $decision,
        'target_rombel_id' => $targetRombelId,
    ];
}

function phase06bPayload(array $decisions, array $overrides = []): array
{
    return array_merge([
        'source_school_year_id' => test()->sourceYear->id,
        'target_school_year_id' => test()->targetYear->id,
        'graduation_date' => '2027-06-30',
        'decisions' => $decisions,
    ], $overrides);
}

test('guest and view-only accounts cannot preview or commit progression', function () {
    $this->getJson('/api/academic/progression/options?source_school_year_id='.$this->sourceYear->id.'&target_school_year_id='.$this->targetYear->id)
        ->assertStatus(401);
    $this->postJson('/api/academic/progression/preview', [])->assertStatus(401);
    $this->postJson('/api/academic/progression/commit', ['token' => str_repeat('a', 64)])->assertStatus(401);

    Sanctum::actingAs($this->principal);

    $this->getJson('/api/academic/progression/options?source_school_year_id='.$this->sourceYear->id.'&target_school_year_id='.$this->targetYear->id)
        ->assertStatus(200)
        ->assertJsonPath('data.max_batch_size', 500);

    $this->getJson('/api/academic/progression/roster?source_school_year_id='.$this->sourceYear->id)
        ->assertStatus(200);

    $this->postJson('/api/academic/progression/preview', phase06bPayload([]))->assertStatus(403);
    $this->postJson('/api/academic/progression/commit', ['token' => str_repeat('a', 64)])->assertStatus(403);
});

test('options and roster expose source classes, active students, and eligibility', function () {
    [$activeStudent, $activeMembership] = phase06bStudent('Roster Active', $this->sourceClass->id);
    [$targetStudent] = phase06bStudent('Roster Target Exists', $this->sourceClass->id);
    [$archivedStudent, $archivedMembership] = phase06bStudent('Roster Archived', $this->sourceClass->id);
    $archivedStudent->delete();

    StudentRombel::create([
        'student_id' => $targetStudent->id,
        'student_nik' => $targetStudent->nik,
        'school_rombel' => $this->targetClass->id,
        'student_status_id' => 1,
        'latest_student_entry_date' => '2027-07-15',
        'version' => 1,
    ]);

    $legacyStudent = Student::create([
        'name' => 'Roster Legacy',
        'nik' => '8200000099999999',
        'version' => 1,
    ]);
    DB::table('student_rombels')->insert([
        'version' => 1,
        'student_id' => null,
        'student_nik' => $legacyStudent->nik,
        'school_rombel' => $this->sourceClass->id,
        'student_status_id' => 1,
        'latest_student_entry_date' => '2026-07-15',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $legacyMembershipId = (int) DB::table('student_rombels')
        ->where('student_nik', $legacyStudent->nik)
        ->value('id');

    $otherClass = SchoolRombel::create([
        'school_year_id' => $this->sourceYear->id,
        'school_level_id' => $this->levelTen->id,
        'name' => '10-B',
    ]);
    phase06bStudent('Roster Other Class', $otherClass->id);

    Sanctum::actingAs($this->operator);

    $this->getJson('/api/academic/progression/options?source_school_year_id='.$this->sourceYear->id.'&target_school_year_id='.$this->targetYear->id)
        ->assertStatus(200)
        ->assertJsonPath('data.source_school_year.id', $this->sourceYear->id)
        ->assertJsonPath('data.source_school_year.is_locked', false)
        ->assertJsonPath('data.target_school_year.start_date', '2027-07-01')
        ->assertJsonCount(2, 'data.source_classes')
        ->assertJsonCount(1, 'data.target_classes');

    $roster = $this->getJson('/api/academic/progression/roster?source_school_year_id='.$this->sourceYear->id.'&target_school_year_id='.$this->targetYear->id.'&rombel_ids[]='.$this->sourceClass->id)
        ->assertStatus(200)
        ->assertJsonPath('data.truncated', false)
        ->json('data.items');

    $byId = collect($roster)->keyBy('student_id');

    expect($byId->has($archivedStudent->id))->toBeFalse()
        ->and($byId->has($activeStudent->id))->toBeTrue()
        ->and($byId->get($activeStudent->id)['eligible'])->toBeTrue()
        ->and($byId->get($activeStudent->id)['membership_id'])->toBe($activeMembership->id)
        ->and($byId->get($targetStudent->id)['eligible'])->toBeFalse()
        ->and($byId->get($targetStudent->id)['ineligible_reason'])->toBe('target_membership_exists')
        ->and($byId->get($legacyStudent->id)['eligible'])->toBeTrue()
        ->and($byId->get($legacyStudent->id)['membership_id'])->toBe($legacyMembershipId)
        ->and($byId->has($archivedMembership->student_id))->toBeFalse();

    $search = $this->getJson('/api/academic/progression/roster?source_school_year_id='.$this->sourceYear->id.'&search=Roster+Other')
        ->assertStatus(200)
        ->json('data.items');

    expect($search)->toHaveCount(1)
        ->and($search[0]['name'])->toBe('Roster Other Class');
});

test('preview with mixed decisions creates a token batch without mutating domain data', function () {
    [$promoteStudent, $promoteMembership] = phase06bStudent('Preview Promote', $this->sourceClass->id);
    [$retainStudent, $retainMembership] = phase06bStudent('Preview Retain', $this->sourceClass->id);
    [$graduateStudent, $graduateMembership] = phase06bStudent('Preview Graduate', $this->sourceClass->id);
    [$skipStudent, $skipMembership] = phase06bStudent('Preview Skip', $this->sourceClass->id);

    Sanctum::actingAs($this->operator);

    $membershipCountBefore = StudentRombel::withTrashed()->count();

    $response = $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($promoteStudent, $promoteMembership, 'promote', $this->targetClass->id),
        phase06bDecision($retainStudent, $retainMembership, 'retain'),
        phase06bDecision($graduateStudent, $graduateMembership, 'graduate'),
        phase06bDecision($skipStudent, $skipMembership, 'skip'),
    ]));

    $response->assertStatus(200)
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.summary.total', 4)
        ->assertJsonPath('data.summary.by_decision.promote', 1)
        ->assertJsonPath('data.summary.by_decision.retain', 1)
        ->assertJsonPath('data.summary.by_decision.graduate', 1)
        ->assertJsonPath('data.summary.by_decision.skip', 1)
        ->assertJsonPath('data.summary.conflicts', 0);

    $token = $response->json('data.token');
    expect($token)->toHaveLength(64)
        ->and($response->json('data.batch_id'))->toBeInt()
        ->and(StudentRombel::withTrashed()->count())->toBe($membershipCountBefore)
        ->and((int) $promoteMembership->fresh()->student_status_id)->toBe(1)
        ->and($promoteStudent->fresh()->version)->toBe(1)
        ->and($graduateMembership->fresh()->latest_student_exit_date)->toBeNull();

    $batch = StudentProgressionBatch::findOrFail($response->json('data.batch_id'));
    expect($batch->status)->toBe(StudentProgressionBatch::STATUS_PREVIEW)
        ->and($batch->token_hash)->toBe(hash('sha256', $token))
        ->and($batch->token_hash)->not->toBe($token)
        ->and($batch->expires_at->greaterThan(Carbon::now()->addMinutes(29)))->toBeTrue();

    $audit = Activity::where('table_name', 'student_progression_batches')
        ->where('record_id', $batch->id)
        ->where('information', 'Created')
        ->first();

    expect($audit)->not->toBeNull()
        ->and(array_key_exists('token_hash', $audit->new))->toBeFalse()
        ->and(array_key_exists('decisions', $audit->new))->toBeFalse();
});

test('preview reports conflicts without creating a batch', function () {
    [$student, $membership] = phase06bStudent('Preview Conflict Target', $this->sourceClass->id);

    StudentRombel::create([
        'student_id' => $student->id,
        'student_nik' => $student->nik,
        'school_rombel' => $this->targetClass->id,
        'student_status_id' => 1,
        'latest_student_entry_date' => '2027-07-15',
        'version' => 1,
    ]);

    Sanctum::actingAs($this->operator);

    $batchCount = StudentProgressionBatch::count();

    $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($student, $membership, 'promote', $this->targetClass->id),
    ]))
        ->assertStatus(422)
        ->assertJsonPath('data.valid', false)
        ->assertJsonPath('data.items.0.ready', false)
        ->assertJsonPath('data.items.0.conflicts.0.code', 'target_membership_exists');

    expect(StudentProgressionBatch::count())->toBe($batchCount);

    $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($student, $membership, 'promote', $this->targetClass->id),
    ], ['target_school_year_id' => $this->sourceYear->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('target_school_year_id');

    [$graduateStudent, $graduateMembership] = phase06bStudent('Preview Conflict Graduate', $this->sourceClass->id);

    $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($graduateStudent, $graduateMembership, 'graduate'),
    ], ['graduation_date' => null]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('graduation_date');

    $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($graduateStudent, $graduateMembership, 'promote'),
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('decisions.0.target_rombel_id');

    $tooMany = [];
    for ($i = 0; $i < 501; $i++) {
        $tooMany[] = [
            'student_id' => $i + 1,
            'membership_id' => $i + 1,
            'expected_student_version' => 1,
            'expected_membership_version' => 1,
            'decision' => 'skip',
        ];
    }

    $this->postJson('/api/academic/progression/preview', phase06bPayload($tooMany))
        ->assertStatus(422)
        ->assertJsonValidationErrors('decisions');
});

test('commit applies promote, retain, graduate, and skip atomically and replays the token', function () {
    [$promoteStudent, $promoteMembership] = phase06bStudent('Commit Promote', $this->sourceClass->id);
    [$retainStudent, $retainMembership] = phase06bStudent('Commit Retain', $this->sourceClass->id);
    [$graduateStudent, $graduateMembership] = phase06bStudent('Commit Graduate', $this->sourceClass->id);
    [$skipStudent, $skipMembership] = phase06bStudent('Commit Skip', $this->sourceClass->id);

    Sanctum::actingAs($this->operator);

    $preview = $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($promoteStudent, $promoteMembership, 'promote', $this->targetClass->id),
        phase06bDecision($retainStudent, $retainMembership, 'retain'),
        phase06bDecision($graduateStudent, $graduateMembership, 'graduate'),
        phase06bDecision($skipStudent, $skipMembership, 'skip'),
    ]))->assertStatus(200);

    $token = $preview->json('data.token');
    $batchId = $preview->json('data.batch_id');

    $this->postJson('/api/academic/progression/commit', ['token' => $token])
        ->assertStatus(200)
        ->assertJsonPath('data.batch_id', $batchId)
        ->assertJsonPath('data.status', 'committed')
        ->assertJsonPath('data.replayed', false)
        ->assertJsonPath('data.items.0.decision', 'promote');

    $promotedMembership = StudentRombel::where('student_id', $promoteStudent->id)
        ->whereHas('schoolRombel', fn ($query) => $query->where('school_year_id', $this->targetYear->id))
        ->first();

    expect($promotedMembership)->not->toBeNull()
        ->and((int) $promotedMembership->school_rombel)->toBe($this->targetClass->id)
        ->and((int) $promotedMembership->student_status_id)->toBe(1)
        ->and($promotedMembership->student_entry_id)->toBeNull()
        ->and(Carbon::parse($promotedMembership->latest_student_entry_date)->toDateString())->toBe('2027-07-01')
        ->and($promoteMembership->fresh()->student_status_id)->toBe(1)
        ->and($promoteStudent->fresh()->version)->toBe(2);

    $retainMembership->refresh();
    expect((int) $retainMembership->student_status_id)->toBe(1)
        ->and($retainMembership->latest_student_exit_date)->toBeNull()
        ->and($retainStudent->fresh()->version)->toBe(1)
        ->and(StudentRombel::where('student_id', $retainStudent->id)->count())->toBe(1);

    $graduateMembership->refresh();
    expect((int) $graduateMembership->student_status_id)->toBe(6)
        ->and(Carbon::parse($graduateMembership->latest_student_exit_date)->toDateString())->toBe('2027-06-30')
        ->and(Carbon::parse($graduateMembership->latest_student_graduation_date)->toDateString())->toBe('2027-06-30')
        ->and($graduateMembership->latest_information)->toBe('Progression batch #'.$batchId)
        ->and($graduateStudent->fresh()->version)->toBe(2)
        ->and(StudentRombel::where('student_id', $graduateStudent->id)->count())->toBe(1);

    $skipMembership->refresh();
    expect((int) $skipMembership->student_status_id)->toBe(1)
        ->and($skipStudent->fresh()->version)->toBe(1)
        ->and(StudentRombel::where('student_id', $skipStudent->id)->count())->toBe(1);

    expect(StudentProgressionBatch::findOrFail($batchId)->status)->toBe(StudentProgressionBatch::STATUS_COMMITTED);

    $membershipAudits = Activity::where('table_name', 'student_rombels')->get();
    expect($membershipAudits)->not->toBeEmpty();

    foreach ($membershipAudits as $membershipAudit) {
        $audited = array_merge(
            $membershipAudit->new ?? [],
            $membershipAudit->before ?? [],
            $membershipAudit->after ?? []
        );
        expect(array_key_exists('student_nik', $audited))->toBeFalse();
    }

    $membershipCount = StudentRombel::withTrashed()->count();

    $this->postJson('/api/academic/progression/commit', ['token' => $token])
        ->assertStatus(200)
        ->assertJsonPath('data.replayed', true)
        ->assertJsonPath('data.status', 'committed');

    expect(StudentRombel::withTrashed()->count())->toBe($membershipCount);
});

test('commit rejects stale versions and leaves all domain data untouched', function () {
    [$student, $membership] = phase06bStudent('Commit Stale', $this->sourceClass->id);

    Sanctum::actingAs($this->operator);

    $preview = $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($student, $membership, 'promote', $this->targetClass->id),
    ]))->assertStatus(200);

    $token = $preview->json('data.token');
    $batchId = $preview->json('data.batch_id');

    $student->update(['name' => 'Commit Stale Renamed', 'version' => 2]);

    $membershipCount = StudentRombel::withTrashed()->count();

    $this->postJson('/api/academic/progression/commit', ['token' => $token])
        ->assertStatus(409)
        ->assertJsonPath('data.items.0.conflicts.0.code', 'stale_student_version');

    expect(StudentRombel::withTrashed()->count())->toBe($membershipCount)
        ->and(StudentProgressionBatch::findOrFail($batchId)->status)->toBe(StudentProgressionBatch::STATUS_PREVIEW);
});

test('commit rejects expired tokens and tokens owned by another account', function () {
    [$student, $membership] = phase06bStudent('Commit Token Rules', $this->sourceClass->id);

    Sanctum::actingAs($this->operator);

    $preview = $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($student, $membership, 'retain'),
    ]))->assertStatus(200);

    $token = $preview->json('data.token');
    $batchId = $preview->json('data.batch_id');

    $otherOperator = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($otherOperator);

    $this->postJson('/api/academic/progression/commit', ['token' => $token])
        ->assertStatus(403);

    Sanctum::actingAs($this->operator);

    StudentProgressionBatch::whereKey($batchId)->update(['expires_at' => Carbon::now()->subMinute()]);

    $this->postJson('/api/academic/progression/commit', ['token' => $token])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The progression token has expired. Create a new preview.');

    expect(StudentProgressionBatch::findOrFail($batchId)->status)->toBe(StudentProgressionBatch::STATUS_PREVIEW);
});

test('progression lock rules depend on the decisions in the batch', function () {
    [$promoteStudent, $promoteMembership] = phase06bStudent('Lock Promote', $this->sourceClass->id);
    [$graduateStudent, $graduateMembership] = phase06bStudent('Lock Graduate', $this->sourceClass->id);
    [$retainStudent, $retainMembership] = phase06bStudent('Lock Retain', $this->sourceClass->id);

    Sanctum::actingAs($this->operator);

    $this->targetYear->update(['locked_at' => now()]);

    $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($promoteStudent, $promoteMembership, 'promote', $this->targetClass->id),
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('target_school_year_id');

    $this->targetYear->update(['locked_at' => null]);
    $this->sourceYear->update(['locked_at' => now()]);

    $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($graduateStudent, $graduateMembership, 'graduate'),
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('source_school_year_id');

    $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($retainStudent, $retainMembership, 'retain'),
    ]))
        ->assertStatus(200)
        ->assertJsonPath('data.valid', true);
});

test('audit failure during commit rolls back memberships and keeps the batch in preview', function () {
    [$student, $membership] = phase06bStudent('Audit Rollback', $this->sourceClass->id);

    Sanctum::actingAs($this->operator);

    $preview = $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($student, $membership, 'promote', $this->targetClass->id),
    ]))->assertStatus(200);

    $token = $preview->json('data.token');
    $batchId = $preview->json('data.batch_id');

    Activity::$testCreatingHook = function ($activity) {
        if ($activity->table_name === 'student_rombels') {
            throw new \RuntimeException('Simulated audit failure during progression commit');
        }
    };

    try {
        $this->postJson('/api/academic/progression/commit', ['token' => $token])
            ->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    $targetMemberships = StudentRombel::where('student_id', $student->id)
        ->whereHas('schoolRombel', fn ($query) => $query->where('school_year_id', $this->targetYear->id))
        ->count();

    expect($targetMemberships)->toBe(0)
        ->and($membership->fresh()->student_status_id)->toBe(1)
        ->and($student->fresh()->version)->toBe(1)
        ->and(StudentProgressionBatch::findOrFail($batchId)->status)->toBe(StudentProgressionBatch::STATUS_PREVIEW);
});

test('batch history exposes committed results and hides previews from other accounts', function () {
    [$student, $membership] = phase06bStudent('History Student', $this->sourceClass->id);

    Sanctum::actingAs($this->operator);

    $preview = $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($student, $membership, 'retain'),
    ]))->assertStatus(200);

    $batchId = $preview->json('data.batch_id');
    $token = $preview->json('data.token');

    $this->getJson('/api/academic/progression/batches')
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', $batchId)
        ->assertJsonMissingPath('data.0.token')
        ->assertJsonMissingPath('data.0.token_hash');

    $this->getJson("/api/academic/progression/batches/{$batchId}")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'preview')
        ->assertJsonPath('data.items.0.student_id', $student->id);

    $this->postJson('/api/academic/progression/commit', ['token' => $token])->assertStatus(200);

    Sanctum::actingAs($this->principal);

    $this->getJson("/api/academic/progression/batches/{$batchId}")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'committed')
        ->assertJsonPath('data.items.0.decision', 'retain');

    Sanctum::actingAs($this->operator);

    $preview2 = $this->postJson('/api/academic/progression/preview', phase06bPayload([
        phase06bDecision($student, $membership, 'skip'),
    ]));
    $preview2->assertStatus(200);
    $preview2Id = $preview2->json('data.batch_id');

    $ownList = collect($this->getJson('/api/academic/progression/batches')->assertStatus(200)->json('data'))->pluck('id');
    expect($ownList->contains($batchId))->toBeTrue()
        ->and($ownList->contains($preview2Id))->toBeTrue();

    $otherOperator = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($otherOperator);

    $otherList = collect($this->getJson('/api/academic/progression/batches')->assertStatus(200)->json('data'))->pluck('id');
    expect($otherList->contains($batchId))->toBeTrue()
        ->and($otherList->contains($preview2Id))->toBeFalse();

    $this->getJson('/api/academic/progression/batches/'.$preview2Id)
        ->assertStatus(403);

    Sanctum::actingAs($this->principal);

    $principalList = collect($this->getJson('/api/academic/progression/batches')->assertStatus(200)->json('data'))->pluck('id');
    expect($principalList->contains($batchId))->toBeTrue()
        ->and($principalList->contains($preview2Id))->toBeFalse();
});

test('school years referenced by a progression batch cannot be deleted', function () {
    $emptyYear = SchoolYear::create(['name' => '2030/2031', 'start_date' => '2030-07-01']);

    StudentProgressionBatch::create([
        'token_hash' => hash('sha256', 'synthetic-guard-token'),
        'source_school_year_id' => $this->sourceYear->id,
        'target_school_year_id' => $emptyYear->id,
        'status' => StudentProgressionBatch::STATUS_PREVIEW,
        'decisions' => [],
        'summary' => ['total' => 0],
        'created_by' => $this->operator->id,
        'expires_at' => Carbon::now()->addMinutes(30),
    ]);

    Sanctum::actingAs($this->operator);

    $this->deleteJson("/api/school-years/{$emptyYear->id}", ['delete' => 'true'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This record is still being referenced by 1 progression batch(es).');

    expect(SchoolYear::whereKey($emptyYear->id)->exists())->toBeTrue();
});
