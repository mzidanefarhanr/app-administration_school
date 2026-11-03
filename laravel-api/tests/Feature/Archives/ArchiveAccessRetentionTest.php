<?php

use App\Models\Ability;
use App\Models\Activity;
use App\Models\ArchiveAccessLog;
use App\Models\ArchiveDocument;
use App\Models\ArchiveFileVersion;
use App\Models\Role;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 10, 9, 0, 0, 'Asia/Jakarta'));

    Storage::fake('local');

    $this->seedAccessReferenceData();

    $this->admin = $this->makeUserWithRole('admin-sistem');
    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');

    $this->student = Student::factory()->create([
        'name' => 'Retention Archive Student',
        'nis' => '0920001',
        'nisn' => '0092000001',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function b09Pdf(string $name = 'retention.pdf', ?string $content = null): UploadedFile
{
    $content = $content ?? "%PDF-1.4\n09b synthetic body\n%%EOF\n";

    return UploadedFile::fake()->createWithContent($name, $content);
}

/**
 * @return array<string, mixed>
 */
function b09Payload(array $overrides = []): array
{
    return array_merge([
        'category' => 'school_document',
        'title' => 'Retention Lifecycle Document',
        'document_number' => 'ARS/09B/001',
        'document_date' => '2026-11-05',
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function b09Create(\Tests\TestCase $test, array $overrides = [], ?UploadedFile $file = null, $actor = null): array
{
    Sanctum::actingAs($actor ?? $test->operator);

    $payload = b09Payload($overrides);
    $payload['file'] = $file ?? b09Pdf();

    $response = $test->post('/api/archives', $payload, ['Accept' => 'application/json']);
    $response->assertOk();

    return $response->json('data');
}

function b09Role(string $slug, array $abilities): Role
{
    $role = Role::create([
        'name' => ucwords(str_replace('-', ' ', $slug)),
        'slug' => $slug,
        'description' => 'Synthetic 09b test role.',
        'is_protected' => false,
    ]);

    $role->abilities()->sync(Ability::whereIn('name', $abilities)->pluck('id')->all());

    return $role;
}

// T14 — inline preview keeps the safe header shape and logs the access.
it('previews an archive version inline without exposing it publicly', function () {
    $content = "%PDF-1.4\nPreview body\n%%EOF\n";

    $document = b09Create($this, ['title' => 'Preview Document'], b09Pdf('preview-report.pdf', $content));

    Sanctum::actingAs($this->operator);

    $response = $this->get("/api/archives/{$document['id']}/versions/1/preview", ['Accept' => 'application/json'])
        ->assertOk()
        ->assertStreamedContent($content);

    $response->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Document-Filename', 'preview-report.pdf')
        ->assertHeader('Access-Control-Expose-Headers', 'X-Document-Filename')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', 'sandbox');

    expect($response->headers->get('Content-Disposition'))->toBeNull()
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Content-Length'))->toBe((string) strlen($content));

    $after = ArchiveDocument::findOrFail($document['id']);

    expect((int) $after->version)->toBe(1)
        ->and((int) $after->current_version)->toBe(1);

    $log = ArchiveAccessLog::where('archive_document_id', $after->id)->sole();

    expect($log->action)->toBe(ArchiveAccessLog::ACTION_PREVIEW)
        ->and((int) $log->archive_file_version_id)->toBe($after->latestVersion()->firstOrFail()->id)
        ->and((int) $log->user_id)->toBe($this->operator->id);

    $detail = $this->getJson("/api/archives/{$document['id']}")->assertOk()->json('data');

    expect($detail['access_logs_count'])->toBe(1)
        ->and($detail['can']['preview'])->toBeTrue()
        ->and($detail['status'])->toBe('active');
});

// T15 — download access audit for two actors, minimized payload.
it('records download and preview access events without private metadata', function () {
    $document = b09Create($this, ['title' => 'Access Audit Document']);

    Sanctum::actingAs($this->operator);
    $this->get("/api/archives/{$document['id']}/versions/1/download", [
        'Accept' => 'application/json',
        'User-Agent' => str_repeat('O', 300),
    ])->assertOk();

    Sanctum::actingAs($this->admin);
    $this->get("/api/archives/{$document['id']}/versions/1/preview", ['Accept' => 'application/json'])
        ->assertOk();

    expect(ArchiveAccessLog::count())->toBe(2);

    $logs = ArchiveAccessLog::orderBy('id')->get();

    expect($logs[0]->action)->toBe('download')
        ->and($logs[0]->ip_address)->not->toBeNull()
        ->and(strlen((string) $logs[0]->user_agent))->toBe(255)
        ->and($logs[1]->action)->toBe('preview')
        ->and($logs[1]->user_id)->toBe($this->admin->id)
        ->and($logs[0]->user_id)->toBe($this->operator->id);

    $serialized = json_encode($logs->map(fn (ArchiveAccessLog $log) => [
        'action' => $log->action,
        'ip' => $log->ip_address,
        'agent' => $log->user_agent,
    ])->all());

    expect($serialized)->not->toContain('Access Audit Document')
        ->and($serialized)->not->toContain('attachments/archives')
        ->and($serialized)->not->toContain('checksum');
});

// T16 — access history requires audit.view and stays inside the domain.
it('gates the access history on audit view and domain visibility', function () {
    $general = b09Create($this, ['title' => 'General Access History']);
    $linked = b09Create($this, [
        'title' => 'Student Access History',
        'subject_type' => 'student',
        'subject_id' => $this->student->id,
    ]);

    Sanctum::actingAs($this->operator);
    $this->get("/api/archives/{$general['id']}/versions/1/download", ['Accept' => 'application/json'])->assertOk();

    b09Role('test-archive-archivist', ['archives.view']);
    $archivist = $this->makeUserWithRole('test-archive-archivist');

    Sanctum::actingAs($this->operator);
    $this->getJson("/api/archives/{$general['id']}/access-logs")->assertStatus(403);

    Sanctum::actingAs($this->principal);
    $history = $this->getJson("/api/archives/{$general['id']}/access-logs")
        ->assertOk()
        ->json('data');

    expect($history['items'])->toHaveCount(1)
        ->and(array_keys($history['items'][0]))->toBe(['id', 'action', 'action_label', 'version_number', 'user', 'ip_address', 'created_at'])
        ->and($history['items'][0]['action'])->toBe('download')
        ->and($history['items'][0]['version_number'])->toBe(1)
        ->and($history['items'][0]['user']['name'])->toBe($this->operator->name)
        ->and($history['meta']['total'])->toBe(1);

    $payload = json_encode($history);
    expect($payload)->not->toContain('attachments/archives')
        ->and($payload)->not->toContain('checksum');

    // Tanpa audit.view, dokumen umum tetap 403; dokumen di luar domain 404.
    Sanctum::actingAs($archivist);
    $this->getJson("/api/archives/{$general['id']}/access-logs")->assertStatus(403);
    $this->getJson("/api/archives/{$linked['id']}/access-logs")->assertStatus(404);

    // audit.view tanpa domain students.view tetap terkunci di luar domain siswa.
    b09Role('test-audit-only', ['archives.view', 'audit.view']);
    Sanctum::actingAs($this->makeUserWithRole('test-audit-only'));
    $this->getJson("/api/archives/{$general['id']}/access-logs")->assertOk();
    $this->getJson("/api/archives/{$linked['id']}/access-logs")->assertStatus(404);

    // Principal memegang students.view + audit.view, jadi riwayat domain siswa terbuka.
    Sanctum::actingAs($this->principal);
    $linkedHistory = $this->getJson("/api/archives/{$linked['id']}/access-logs")
        ->assertOk()
        ->json('data');

    expect($linkedHistory['items'])->toBeEmpty()
        ->and($linkedHistory['meta']['total'])->toBe(0);

    $this->getJson("/api/archives/{$general['id']}/access-logs?per_page=7")->assertStatus(422);
    $this->getJson("/api/archives/{$general['id']}/access-logs?page=0")->assertStatus(422);
});

// T17 — the access audit is fail-closed: no bytes when the log cannot be written.
it('refuses to stream when the access event cannot be recorded', function () {
    $document = b09Create($this, ['title' => 'Fail Closed Document']);

    Sanctum::actingAs($this->operator);

    Log::spy();
    Schema::dropIfExists('archive_access_logs');

    $this->get("/api/archives/{$document['id']}/versions/1/download", ['Accept' => 'application/json'])
        ->assertStatus(500)
        ->assertJsonPath('message', 'The access event could not be recorded.')
        ->assertDontSee('PDF');

    $this->get("/api/archives/{$document['id']}/versions/1/preview", ['Accept' => 'application/json'])
        ->assertStatus(500);
});

// T18 — stored files are verified on read; tampered bytes are withheld.
it('withholds bytes when the stored file fails its checksum', function () {
    $content = "%PDF-1.4\nIntegrity body\n%%EOF\n";

    $document = b09Create($this, ['title' => 'Integrity Document'], b09Pdf('integrity.pdf', $content));
    $version = ArchiveFileVersion::where('archive_document_id', $document['id'])->firstOrFail();

    Sanctum::actingAs($this->operator);

    $this->get("/api/archives/{$document['id']}/versions/1/download", ['Accept' => 'application/json'])
        ->assertOk()
        ->assertStreamedContent($content);

    Storage::disk('local')->put($version->path, "%PDF-1.4\nTampered body\n%%EOF\n");

    $this->get("/api/archives/{$document['id']}/versions/1/download", ['Accept' => 'application/json'])
        ->assertStatus(500)
        ->assertJsonPath('message', 'The stored file failed its integrity check.')
        ->assertDontSee('Tampered body')
        ->assertDontSee($version->path);

    $this->get("/api/archives/{$document['id']}/versions/1/preview", ['Accept' => 'application/json'])
        ->assertStatus(500)
        ->assertJsonPath('message', 'The stored file failed its integrity check.');

    expect(ArchiveAccessLog::where('archive_document_id', $document['id'])->count())->toBe(1);

    Storage::disk('local')->delete($version->path);

    $this->get("/api/archives/{$document['id']}/versions/1/download", ['Accept' => 'application/json'])
        ->assertStatus(404);
    $this->get("/api/archives/{$document['id']}/versions/1/preview", ['Accept' => 'application/json'])
        ->assertStatus(404);

    expect(ArchiveAccessLog::where('archive_document_id', $document['id'])->count())->toBe(1);

    $this->getJson('/storage/'.$version->path)->assertStatus(404);
    $this->get('/attachments/archives/'.$document['id'].'/x.pdf')->assertStatus(404);
});

// T19 — archive lifecycle: reason, optimistic locking, and read-only state.
it('archives a document with a reason and makes it read-only', function () {
    $document = b09Create($this, ['title' => 'Lifecycle Document']);
    $sibling = b09Create($this, ['title' => 'Still Active Document']);

    Sanctum::actingAs($this->operator);

    $this->postJson("/api/archives/{$document['id']}/archive", ['expected_version' => $document['version']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['archive_reason']);

    $this->postJson("/api/archives/{$document['id']}/archive", [
        'archive_reason' => 'short',
        'expected_version' => $document['version'],
    ])->assertStatus(422)->assertJsonValidationErrors(['archive_reason']);

    $archived = $this->postJson("/api/archives/{$document['id']}/archive", [
        'archive_reason' => 'Retained after the 2026 review.',
        'expected_version' => $document['version'],
    ])->assertOk()->json('data');

    expect($archived['status'])->toBe('archived')
        ->and($archived['archived_at'])->not->toBeNull()
        ->and($archived['archive_reason'])->toBe('Retained after the 2026 review.')
        ->and($archived['archived_by']['id'])->toBe($this->operator->id)
        ->and($archived['version'])->toBe(2)
        ->and($archived['current_version'])->toBe(1)
        ->and($archived['can']['update'])->toBeFalse()
        ->and($archived['can']['add_version'])->toBeFalse()
        ->and($archived['can']['archive'])->toBeFalse()
        ->and($archived['can']['restore'])->toBeTrue()
        ->and($archived['can']['download'])->toBeTrue()
        ->and($archived['can']['preview'])->toBeTrue();

    $this->postJson("/api/archives/{$document['id']}/archive", [
        'archive_reason' => 'Archived twice by accident.',
        'expected_version' => $archived['version'],
    ])->assertStatus(422)->assertJsonValidationErrors(['archived_at']);

    $this->putJson("/api/archives/{$document['id']}", [
        'title' => 'Late edit attempt',
        'expected_version' => $archived['version'],
    ])->assertStatus(422)->assertJsonValidationErrors(['archived_at']);

    $this->post("/api/archives/{$document['id']}/versions", [
        'file' => b09Pdf('late-version.pdf'),
        'expected_version' => $archived['version'],
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['archived_at']);

    $this->get("/api/archives/{$document['id']}/versions/1/download", ['Accept' => 'application/json'])->assertOk();
    $this->get("/api/archives/{$document['id']}/versions/1/preview", ['Accept' => 'application/json'])->assertOk();
    $this->getJson("/api/archives/{$document['id']}/versions")->assertOk();

    expect(ArchiveFileVersion::where('archive_document_id', $document['id'])->count())->toBe(1);

    $activeIds = array_map(fn (array $item) => $item['id'], $this->getJson('/api/archives')->assertOk()->json('data.items'));
    $archivedIds = array_map(fn (array $item) => $item['id'], $this->getJson('/api/archives?status=archived')->assertOk()->json('data.items'));
    $allItems = $this->getJson('/api/archives?status=all')->assertOk()->json('data.items');
    $allIds = array_column($allItems, 'id');

    expect($activeIds)->not->toContain($document['id'])
        ->and($activeIds)->toContain($sibling['id'])
        ->and($archivedIds)->toBe([$document['id']])
        ->and($allIds)->toContain($document['id'])
        ->and(in_array('archived', array_column($allItems, 'status'), true))->toBeTrue()
        ->and(in_array('active', array_column($allItems, 'status'), true))->toBeTrue();

    $this->getJson('/api/archives?status=purge')->assertStatus(422)->assertJsonValidationErrors(['status']);
});

// T20 — restore returns the document to the active state.
it('restores an archived document without touching versions or links', function () {
    $document = b09Create($this, [
        'title' => 'Restorable Document',
        'subject_type' => 'student',
        'subject_id' => $this->student->id,
    ]);

    Sanctum::actingAs($this->operator);

    $this->postJson("/api/archives/{$document['id']}/restore", ['expected_version' => $document['version']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['archived_at']);

    $archived = $this->postJson("/api/archives/{$document['id']}/archive", [
        'archive_reason' => 'Reason for the synthetic archival.',
        'expected_version' => $document['version'],
    ])->assertOk()->json('data');

    $this->postJson("/api/archives/{$document['id']}/restore", ['expected_version' => 99])
        ->assertStatus(409)
        ->assertJsonPath('latest.version', $archived['version']);

    $this->postJson("/api/archives/{$document['id']}/restore", [
        'expected_version' => $archived['version'],
        'archive_reason' => 'Not editable on restore.',
    ])->assertStatus(422)->assertJsonValidationErrors(['archive_reason']);

    $restored = $this->postJson("/api/archives/{$document['id']}/restore", [
        'expected_version' => $archived['version'],
    ])->assertOk()->json('data');

    expect($restored['status'])->toBe('active')
        ->and($restored['archived_at'])->toBeNull()
        ->and($restored['archive_reason'])->toBeNull()
        ->and($restored['archived_by'])->toBeNull()
        ->and($restored['version'])->toBe(3)
        ->and($restored['current_version'])->toBe(1)
        ->and($restored['subject']['id'])->toBe($this->student->id)
        ->and($restored['can']['update'])->toBeTrue()
        ->and($restored['can']['restore'])->toBeFalse()
        ->and($restored['can']['archive'])->toBeTrue();

    $this->postJson("/api/archives/{$document['id']}/archive", [
        'archive_reason' => 'Stale archive attempt.',
        'expected_version' => 1,
    ])->assertStatus(409)->assertJsonPath('latest.version', 3);

    $model = ArchiveDocument::findOrFail($document['id']);

    expect($model->isArchived())->toBeFalse()
        ->and($model->status())->toBe(ArchiveDocument::STATUS_ACTIVE);
});

// Audit records stay minimized across the lifecycle (DEC-38 D5).
it('audits archive lifecycle changes without the reason or titles', function () {
    $document = b09Create($this, ['title' => 'Audit Lifecycle Document']);

    Sanctum::actingAs($this->operator);

    $this->postJson("/api/archives/{$document['id']}/archive", [
        'archive_reason' => 'Confidential archival rationale.',
        'expected_version' => $document['version'],
    ])->assertOk();

    $this->postJson("/api/archives/{$document['id']}/restore", ['expected_version' => 2])->assertOk();

    $records = Activity::where('table_name', 'archive_documents')
        ->where('record_id', $document['id'])
        ->where('information', 'Updated')
        ->get();

    expect($records)->toHaveCount(2);

    $payload = json_encode($records->map(fn (Activity $activity) => [$activity->before, $activity->after])->all());

    expect($payload)->toContain('archived_at')
        ->and($payload)->not->toContain('Confidential archival rationale.')
        ->and($payload)->not->toContain('Audit Lifecycle Document')
        ->and($payload)->not->toContain('attachments/archives')
        ->and($payload)->not->toContain('checksum');

    expect(ArchiveAccessLog::count())->toBe(0);
});

// Expiry task computed on read (DEC-38 D4).
it('reports expiring and expired archives as a read-only dashboard task', function () {
    Sanctum::actingAs($this->operator);

    $expiringIn15 = b09Create($this, ['title' => 'Expiring In Fifteen Days', 'valid_from' => '2026-01-01', 'valid_until' => '2026-11-25']);
    $expiringToday = b09Create($this, ['title' => 'Expiring Today', 'valid_from' => '2026-01-01', 'valid_until' => '2026-11-10']);
    $expired = b09Create($this, ['title' => 'Already Expired', 'valid_from' => '2025-01-01', 'valid_until' => '2026-11-01']);
    $upcoming = b09Create($this, ['title' => 'Upcoming Validity', 'valid_from' => '2026-12-01', 'valid_until' => '2026-12-20']);
    $stillValid = b09Create($this, ['title' => 'Valid For Years', 'valid_from' => '2026-01-01', 'valid_until' => '2029-01-01']);
    $noExpiry = b09Create($this, ['title' => 'No Expiry At All']);

    $task = $this->getJson('/api/archives/tasks/expiring')->assertOk()->json('data');

    expect($task['threshold_days'])->toBe(30)
        ->and($task['generated_at'])->not->toBeNull()
        ->and($task['expiring_soon']['total'])->toBe(2)
        ->and(array_column($task['expiring_soon']['items'], 'id'))->toBe([$expiringToday['id'], $expiringIn15['id']])
        ->and($task['expiring_soon']['items'][0]['days_remaining'])->toBe(0)
        ->and($task['expiring_soon']['items'][1]['days_remaining'])->toBe(15)
        ->and($task['expired']['total'])->toBe(1)
        ->and(array_column($task['expired']['items'], 'id'))->toBe([$expired['id']])
        ->and($task['expired']['items'][0]['days_remaining'])->toBe(-9)
        ->and($task['expired']['items'][0]['validity_status'])->toBe('expired');

    $listed = array_merge(
        array_column($task['expiring_soon']['items'], 'id'),
        array_column($task['expired']['items'], 'id'),
    );

    expect($listed)->not->toContain($upcoming['id'])
        ->and($listed)->not->toContain($stillValid['id'])
        ->and($listed)->not->toContain($noExpiry['id']);

    expect(array_keys($task['expiring_soon']['items'][0]))->toBe([
        'id', 'category', 'category_label', 'title', 'document_number',
        'valid_from', 'valid_until', 'validity_status', 'days_remaining', 'current_version', 'subject',
    ]);

    $this->getJson('/api/archives/tasks/expiring?limit=0')->assertStatus(422)->assertJsonValidationErrors(['limit']);
    $this->getJson('/api/archives/tasks/expiring?limit=51')->assertStatus(422)->assertJsonValidationErrors(['limit']);

    $limited = $this->getJson('/api/archives/tasks/expiring?limit=1')->assertOk()->json('data');

    expect($limited['expiring_soon']['total'])->toBe(2)
        ->and($limited['expiring_soon']['items'])->toHaveCount(1);

    $stateBefore = [
        'documents' => ArchiveDocument::count(),
        'activities' => Activity::count(),
        'access_logs' => ArchiveAccessLog::count(),
        'versions' => ArchiveFileVersion::count(),
    ];

    $this->getJson('/api/archives/tasks/expiring')->assertOk();
    $this->getJson('/api/archives/tasks/expiring?limit=20')->assertOk();

    expect(ArchiveDocument::count())->toBe($stateBefore['documents'])
        ->and(Activity::count())->toBe($stateBefore['activities'])
        ->and(ArchiveAccessLog::count())->toBe($stateBefore['access_logs'])
        ->and(ArchiveFileVersion::count())->toBe($stateBefore['versions']);

    $this->postJson("/api/archives/{$expired['id']}/archive", [
        'archive_reason' => 'Expired and filed away.',
        'expected_version' => $expired['version'],
    ])->assertOk();

    $afterArchive = $this->getJson('/api/archives/tasks/expiring')->assertOk()->json('data');

    expect($afterArchive['expired']['total'])->toBe(0)
        ->and($afterArchive['expired']['items'])->toBeEmpty();
});

// Task visibility follows the 09a domain filter.
it('limits expiry tasks to the domains the actor may see', function () {
    b09Create($this, [
        'title' => 'General Expiring Item',
        'valid_from' => '2026-01-01',
        'valid_until' => '2026-11-15',
    ]);

    $linked = b09Create($this, [
        'title' => 'Student Expiring Item',
        'subject_type' => 'student',
        'subject_id' => $this->student->id,
        'valid_from' => '2026-01-01',
        'valid_until' => '2026-11-15',
    ]);

    b09Role('test-task-archivist', ['archives.view']);
    $archivist = $this->makeUserWithRole('test-task-archivist');

    Sanctum::actingAs($archivist);

    $task = $this->getJson('/api/archives/tasks/expiring')->assertOk()->json('data');
    $generalId = (int) ArchiveDocument::where('title', 'General Expiring Item')->value('id');

    expect($task['expiring_soon']['total'])->toBe(1)
        ->and(array_column($task['expiring_soon']['items'], 'id'))->toBe([$generalId])
        ->and($task['expiring_soon']['items'][0]['subject'])->toBeNull();

    Sanctum::actingAs($this->operator);

    $operatorTask = $this->getJson('/api/archives/tasks/expiring')->assertOk()->json('data');

    expect($operatorTask['expiring_soon']['total'])->toBe(2)
        ->and(array_column($operatorTask['expiring_soon']['items'], 'id'))->toContain((int) $linked['id'])
        ->and($operatorTask['expiring_soon']['items'][0]['subject']['type'])->toBe('student')
        ->and($operatorTask['expiring_soon']['items'][0]['days_remaining'])->toBe(5);

    Sanctum::actingAs($this->makeUserWithRole('guru'));
    $this->getJson('/api/archives/tasks/expiring')->assertStatus(403);
});

// Retention report is read-only and gated on archives.manage (DEC-38 D6 Option A).
it('reports archive retention without deleting anything', function () {
    b09Create($this, ['title' => 'Old Letter Record', 'category' => 'letter', 'document_date' => '2015-03-01']);
    b09Create($this, ['title' => 'Recent Letter Record', 'category' => 'letter', 'document_date' => '2026-03-01']);
    b09Create($this, ['title' => 'Permanent Student Record', 'category' => 'student_record', 'document_date' => '2005-01-01']);

    Sanctum::actingAs($this->operator);
    $this->get('/api/archives/'.ArchiveDocument::first()->id.'/versions/1/download', ['Accept' => 'application/json'])->assertOk();

    $documentsBefore = ArchiveDocument::count();
    $versionsBefore = ArchiveFileVersion::count();
    $logsBefore = ArchiveAccessLog::count();
    $filesBefore = count(Storage::disk('local')->allFiles());
    $activitiesBefore = Activity::whereIn('table_name', ['archive_documents', 'archive_file_versions'])->count();

    Sanctum::actingAs($this->principal);
    $this->getJson('/api/archives/retention/report')->assertStatus(403);

    b09Role('test-retention-archivist', ['archives.view']);
    Sanctum::actingAs($this->makeUserWithRole('test-retention-archivist'));
    $this->getJson('/api/archives/retention/report')->assertStatus(403);

    Sanctum::actingAs($this->admin);

    $report = $this->getJson('/api/archives/retention/report')->assertOk()->json('data');

    $byCategory = collect($report['categories'])->keyBy('category');

    expect($report['policy']['purge_enabled'])->toBeFalse()
        ->and($report['policy']['purge_ability'])->toBe('archives.purge')
        ->and($report['policy']['purge_assigned_roles'])->toBe([])
        ->and($byCategory['letter']['retention_years'])->toBe(5)
        ->and($byCategory['letter']['cutoff_date'])->toBe('2021-11-10')
        ->and($byCategory['letter']['documents_total'])->toBe(2)
        ->and($byCategory['letter']['eligible_total'])->toBe(1)
        ->and($byCategory['student_record']['retention_years'])->toBeNull()
        ->and($byCategory['student_record']['retention_label'])->toBe('Permanent')
        ->and($byCategory['student_record']['eligible_total'])->toBe(0)
        ->and($byCategory['school_document']['retention_years'])->toBe(10)
        ->and($byCategory['leave']['retention_years'])->toBe(2)
        ->and($report['totals']['documents_total'])->toBe(3)
        ->and($report['totals']['eligible_total'])->toBe(1)
        ->and($report['access_logs']['total'])->toBe($logsBefore)
        ->and($report['access_logs']['retention_years'])->toBe(5);

    expect(ArchiveDocument::count())->toBe($documentsBefore)
        ->and(ArchiveFileVersion::count())->toBe($versionsBefore)
        ->and(ArchiveAccessLog::count())->toBe($logsBefore)
        ->and(count(Storage::disk('local')->allFiles()))->toBe($filesBefore)
        // Membuat peran/pengguna uji menulis audit sendiri; guard dibatasi ke tabel arsip.
        ->and(Activity::whereIn('table_name', ['archive_documents', 'archive_file_versions'])->count())->toBe($activitiesBefore);
});

// Retention console command is dry-run only.
it('refuses to run retention outside dry-run and writes nothing', function () {
    b09Create($this, ['title' => 'Command Retention Document', 'category' => 'letter', 'document_date' => '2010-01-01']);

    $documentsBefore = ArchiveDocument::count();
    $logsBefore = ArchiveAccessLog::count();
    $filesBefore = count(Storage::disk('local')->allFiles());

    $this->artisan('app:archives-retention')
        ->expectsOutput('Purge execution is not authorized (DEC-38 D6 Option A). Re-run with --dry-run for a read-only report.')
        ->assertExitCode(1);

    $this->artisan('app:archives-retention', ['--dry-run' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->assertExitCode(0);

    expect(ArchiveDocument::count())->toBe($documentsBefore)
        ->and(ArchiveAccessLog::count())->toBe($logsBefore)
        ->and(count(Storage::disk('local')->allFiles()))->toBe($filesBefore)
        ->and(ArchiveDocument::first()->version)->toBe(1);
});

// archives.purge exists but nobody holds it, and no route consumes it.
it('defines the purge ability without assigning it to any role', function () {
    $ability = Ability::where('name', 'archives.purge')->firstOrFail();

    expect($ability->is_sensitive)->toBeTrue()
        ->and($ability->roles()->count())->toBe(0);

    foreach (Role::all() as $role) {
        expect($role->abilities()->where('abilities.name', 'archives.purge')->exists())->toBeFalse();
    }

    $this->seedAccessReferenceData();

    expect($this->admin->hasAbility('archives.purge'))->toBeFalse()
        ->and($this->operator->hasAbility('archives.purge'))->toBeFalse()
        ->and($this->principal->hasAbility('archives.purge'))->toBeFalse();
});

// Access log rows are append-only like Activity.
it('rejects updates and deletes on archive access logs', function () {
    $document = b09Create($this, ['title' => 'Immutable Access Log']);

    Sanctum::actingAs($this->operator);
    $this->get("/api/archives/{$document['id']}/versions/1/preview", ['Accept' => 'application/json'])->assertOk();

    $log = ArchiveAccessLog::where('archive_document_id', $document['id'])->firstOrFail();

    $updateRejected = false;

    try {
        $log->action = 'download';
        $log->save();
    } catch (DomainException) {
        $updateRejected = true;
    }

    $deleteRejected = false;

    try {
        $log->delete();
    } catch (DomainException) {
        $deleteRejected = true;
    }

    expect($updateRejected)->toBeTrue()
        ->and($deleteRejected)->toBeTrue()
        ->and(ArchiveAccessLog::where('archive_document_id', $document['id'])->value('action'))->toBe('preview');
});

// Options expose the new 09b surface without leaking internals.
it('exposes lifecycle and retention options to the archive client', function () {
    Sanctum::actingAs($this->principal);

    $options = $this->getJson('/api/archives/options')->assertOk()->json('data');

    expect(array_column($options['statuses'], 'value'))->toBe(['active', 'archived'])
        ->and(array_column($options['access_log_actions'], 'value'))->toBe(['download', 'preview'])
        ->and($options['expiring_task_limit'])->toBe(['default' => 10, 'max' => 50])
        ->and($options['archive_reason'])->toBe(['min' => 10, 'max' => 255])
        ->and($options['retention']['purge_enabled'])->toBeFalse()
        ->and($options['retention']['anchor'])->toBe('document_date')
        ->and(collect($options['retention']['periods'])->firstWhere('category', 'letter')['years'])->toBe(5)
        ->and($options['can']['manage'])->toBeFalse()
        ->and($options['can']['view_access_log'])->toBeTrue()
        ->and($options['can']['view_retention_report'])->toBeFalse()
        ->and(json_encode($options))->not->toContain('checksum');

    Sanctum::actingAs($this->operator);

    $operatorOptions = $this->getJson('/api/archives/options')->assertOk()->json('data');

    expect($operatorOptions['can']['manage'])->toBeTrue()
        ->and($operatorOptions['can']['view_retention_report'])->toBeTrue()
        ->and($operatorOptions['can']['view_access_log'])->toBeFalse();
});
