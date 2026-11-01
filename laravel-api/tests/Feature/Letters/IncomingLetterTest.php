<?php

use App\Models\Activity;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 10, 9, 0, 0, 'Asia/Jakarta'));

    Storage::fake('local');
    Storage::fake('public');

    $this->seedAccessReferenceData();

    $this->admin = $this->makeUserWithRole('admin-sistem');
    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->wakil = $this->makeUserWithRole('wakil-kesiswaan');
});

afterEach(function () {
    Carbon::setTestNow();
    LetterEvent::$testCreatingHook = null;
});

/**
 * @return array<string, mixed>
 */
function p07Payload(array $overrides = []): array
{
    return array_merge([
        'received_date' => '2026-11-05',
        'letter_date' => '2026-11-03',
        'source_number' => '005/123/DISDIK/2026',
        'sender' => 'Dinas Pendidikan Kabupaten',
        'subject' => 'Undangan rapat koordinasi',
        'category' => 'Invitation',
        'notes' => 'Diterima melalui front office.',
    ], $overrides);
}

function p07Letter(array $overrides = []): Letter
{
    return Letter::create(array_merge([
        'direction' => Letter::DIRECTION_INCOMING,
        'version' => 1,
        'received_date' => '2026-11-05',
        'letter_date' => '2026-11-03',
        'source_number' => '005/123/DISDIK/2026',
        'sender' => 'Dinas Pendidikan Kabupaten',
        'subject' => 'Undangan rapat koordinasi',
        'category' => 'Invitation',
        'notes' => 'Diterima melalui front office.',
        'status' => Letter::STATUS_ACTIVE,
        'created_by' => 1,
    ], $overrides));
}

function p07Pdf(string $name = 'document.pdf', ?string $content = null): UploadedFile
{
    if ($content === null) {
        $content = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    return UploadedFile::fake()->createWithContent($name, $content);
}

function p07Upload($test, int $letterId, UploadedFile $file)
{
    return $test->post(
        "/api/letters/incoming/{$letterId}/attachments",
        ['file' => $file],
        ['Accept' => 'application/json'],
    );
}

/**
 * Upload nyata dengan MIME dideteksi dari isi file (fileinfo), bukan dari nama.
 */
function p07RealUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'p07-letter-');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

// T1 — guest dan ability per peran.
it('enforces authentication and letter abilities', function () {
    $letter = p07Letter(['created_by' => $this->operator->id]);

    $this->getJson('/api/letters/incoming')->assertStatus(401);
    $this->getJson("/api/letters/incoming/{$letter->id}")->assertStatus(401);
    $this->getJson("/api/letters/incoming/{$letter->id}/attachments/1/download")->assertStatus(401);

    Sanctum::actingAs($this->principal);
    $this->getJson('/api/letters/incoming')->assertOk();
    $this->postJson('/api/letters/incoming', p07Payload())->assertStatus(403);
    $this->putJson("/api/letters/incoming/{$letter->id}", p07Payload(['expected_version' => 1]))->assertStatus(403);

    Sanctum::actingAs($this->wakil);
    $this->getJson('/api/letters/incoming')->assertOk();
    $this->postJson('/api/letters/incoming', p07Payload())->assertStatus(403);

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/letters/incoming', p07Payload())->assertOk();

    Sanctum::actingAs($this->admin);
    $this->postJson('/api/letters/incoming', p07Payload(['source_number' => 'ADMIN-1']))->assertOk();
});

// T2 — create valid: baris, event, audit, referensi.
it('creates an incoming letter with event and audit', function () {
    Sanctum::actingAs($this->operator);

    $response = $this->postJson('/api/letters/incoming', p07Payload());

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.direction', 'incoming')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.source_number', '005/123/DISDIK/2026')
        ->assertJsonPath('data.attachments_count', 0)
        ->assertJsonPath('data.events.0.event', 'created')
        ->assertJsonPath('data.events.0.actor.id', $this->operator->id);

    expect($response->json('data.reference'))->toBe('#'.str_pad((string) $response->json('data.id'), 6, '0', STR_PAD_LEFT));

    $letter = Letter::firstOrFail();

    expect($letter->created_by)->toBe($this->operator->id)
        ->and($letter->direction)->toBe(Letter::DIRECTION_INCOMING);

    expect(LetterEvent::where('letter_id', $letter->id)->where('event', 'created')->where('actor_id', $this->operator->id)->exists())->toBeTrue();
    expect(Activity::where('table_name', 'letters')->where('record_id', $letter->id)->where('information', 'Created')->where('user_id', $this->operator->id)->exists())->toBeTrue();

    $payload = json_encode($response->json());
    expect($payload)->not->toContain('attachments/letters')
        ->and($payload)->not->toContain('checksum');
});

// T3 — nomor sumber duplikat sah.
it('allows duplicate source numbers', function () {
    Sanctum::actingAs($this->operator);

    $this->postJson('/api/letters/incoming', p07Payload())->assertOk();
    $this->postJson('/api/letters/incoming', p07Payload(['subject' => 'Surat kedua']))->assertOk();

    expect(Letter::where('source_number', '005/123/DISDIK/2026')->count())->toBe(2);

    $this->getJson('/api/letters/incoming')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2);
});

// T4 — nomor sumber opsional.
it('stores a letter without a source number', function () {
    Sanctum::actingAs($this->operator);

    $payload = p07Payload();
    unset($payload['source_number'], $payload['notes']);

    $this->postJson('/api/letters/incoming', $payload)
        ->assertOk()
        ->assertJsonPath('data.source_number', null)
        ->assertJsonPath('data.notes', null);

    expect(Letter::firstOrFail()->source_number)->toBeNull();

    $this->getJson('/api/letters/incoming?search='.urlencode('tanpa'))
        ->assertOk()
        ->assertJsonPath('data.meta.total', 0);
});

// T5 — validasi create.
it('validates create payloads', function () {
    Sanctum::actingAs($this->operator);

    $invalidPayloads = [
        [p07Payload(['received_date' => '2026-11-11']), 'received_date'],
        [p07Payload(['letter_date' => '2026-11-06']), 'letter_date'],
        [p07Payload(['category' => 'Unknown']), 'category'],
        [p07Payload(['sender' => str_repeat('a', 151)]), 'sender'],
        [p07Payload(['subject' => str_repeat('b', 201)]), 'subject'],
        [p07Payload(['source_number' => str_repeat('c', 101)]), 'source_number'],
        [p07Payload(['notes' => str_repeat('d', 2001)]), 'notes'],
    ];

    foreach ($invalidPayloads as [$payload, $field]) {
        $this->postJson('/api/letters/incoming', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);
    }

    $this->postJson('/api/letters/incoming', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['received_date', 'letter_date', 'sender', 'subject', 'category']);

    expect(Letter::count())->toBe(0);
});

// T6 — update valid dan isolasi baris lain.
it('updates a letter with versioning, event, and audit', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/incoming', p07Payload())->json('data');
    $decoy = p07Letter(['created_by' => $this->operator->id, 'subject' => 'Decoy letter']);

    $this->putJson("/api/letters/incoming/{$created['id']}", p07Payload([
        'subject' => 'Undangan rapat koordinasi (revisi)',
        'category' => 'Internal',
        'expected_version' => 1,
    ]))
        ->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.subject', 'Undangan rapat koordinasi (revisi)')
        ->assertJsonPath('data.category', 'Internal');

    $letter = Letter::findOrFail($created['id']);

    expect($letter->version)->toBe(2)
        ->and(Letter::findOrFail($decoy->id)->subject)->toBe('Decoy letter');

    $updated = LetterEvent::where('letter_id', $letter->id)->where('event', 'updated')->firstOrFail();
    expect($updated->metadata['changed_fields'])->toContain('subject')->toContain('category');
    expect(Activity::where('table_name', 'letters')->where('record_id', $letter->id)->where('information', 'Updated')->exists())->toBeTrue();
});

// T7 — versi basi dan surat void.
it('rejects stale versions and updates to void letters', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/incoming', p07Payload())->json('data');

    $this->putJson("/api/letters/incoming/{$created['id']}", p07Payload([
        'expected_version' => 2,
    ]))
        ->assertStatus(409)
        ->assertJsonPath('errors.expected_version.0', fn ($message) => is_string($message))
        ->assertJsonPath('current_versions.letter_version', 1);

    expect(Letter::findOrFail($created['id'])->version)->toBe(1);

    $this->postJson("/api/letters/incoming/{$created['id']}/void", [
        'reason' => 'Duplicate entry; original is registered as #000045.',
        'expected_version' => 1,
    ])->assertOk();

    $this->putJson("/api/letters/incoming/{$created['id']}", p07Payload([
        'expected_version' => 2,
    ]))
        ->assertStatus(409)
        ->assertJsonPath('current_versions.status', 'void');

    $this->getJson('/api/letters/incoming/999999')->assertStatus(404);
});

// T8 — void valid.
it('voids a letter with a reason and event', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/incoming', p07Payload())->json('data');

    $this->postJson("/api/letters/incoming/{$created['id']}/void", [
        'reason' => 'Duplicate entry; the original letter is registered as #000045.',
        'expected_version' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'void')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.voided_by.id', $this->operator->id)
        ->assertJsonPath('data.void_reason', 'Duplicate entry; the original letter is registered as #000045.');

    $letter = Letter::findOrFail($created['id']);
    expect($letter->voided_at)->not->toBeNull()
        ->and($letter->voided_by)->toBe($this->operator->id);

    expect(LetterEvent::where('letter_id', $letter->id)->where('event', 'voided')->exists())->toBeTrue();
    expect(Activity::where('table_name', 'letters')->where('record_id', $letter->id)->where('information', 'Updated')->exists())->toBeTrue();
});

// T9 — void tidak valid.
it('rejects invalid, repeated, or stale voids', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/incoming', p07Payload())->json('data');

    $this->postJson("/api/letters/incoming/{$created['id']}/void", [
        'reason' => 'short',
        'expected_version' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('reason');

    $this->postJson("/api/letters/incoming/{$created['id']}/void", [
        'reason' => 'Duplicate entry; the original letter is registered as #000045.',
        'expected_version' => 9,
    ])->assertStatus(409)->assertJsonPath('current_versions.letter_version', 1);

    $this->postJson("/api/letters/incoming/{$created['id']}/void", [
        'reason' => 'Duplicate entry; the original letter is registered as #000045.',
        'expected_version' => 1,
    ])->assertOk();

    $this->postJson("/api/letters/incoming/{$created['id']}/void", [
        'reason' => 'A second void attempt should be rejected.',
        'expected_version' => 2,
    ])->assertStatus(409)->assertJsonPath('current_versions.status', 'void');

    expect(Letter::findOrFail($created['id'])->version)->toBe(2);
});

// T10 — surat void bersifat read-only untuk semua mutasi.
it('keeps void letters read-only', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/incoming', p07Payload())->json('data');

    p07Upload($this, $created['id'], p07Pdf())->assertOk();
    $attachment = LetterAttachment::firstOrFail();
    $path = $attachment->path;

    $this->postJson("/api/letters/incoming/{$created['id']}/void", [
        'reason' => 'Duplicate entry; the original letter is registered as #000045.',
        'expected_version' => 1,
    ])->assertOk();

    $this->putJson("/api/letters/incoming/{$created['id']}", p07Payload(['expected_version' => 2]))
        ->assertStatus(409);

    p07Upload($this, $created['id'], p07Pdf('second.pdf'))->assertStatus(409);

    $this->deleteJson("/api/letters/incoming/{$created['id']}/attachments/{$attachment->id}")
        ->assertStatus(409);

    expect(Storage::disk('local')->exists($path))->toBeTrue();
    expect(LetterAttachment::whereKey($attachment->id)->whereNull('deleted_at')->exists())->toBeTrue();
    expect(LetterAttachment::count())->toBe(1);
    expect(LetterEvent::where('letter_id', $created['id'])->where('event', 'voided')->exists())->toBeTrue();

    $this->get("/api/letters/incoming/{$created['id']}/attachments/{$attachment->id}/download")
        ->assertOk();
});

// T11 — upload PDF/JPG/PNG.
it('stores PDF, JPG, and PNG attachments privately', function () {
    Sanctum::actingAs($this->operator);
    $letter = p07Letter(['created_by' => $this->operator->id]);

    p07Upload($this, $letter->id, p07Pdf())->assertOk();
    p07Upload($this, $letter->id, UploadedFile::fake()->image('scan.jpg', 24, 24))->assertOk();
    p07Upload($this, $letter->id, UploadedFile::fake()->image('scan.png', 24, 24))->assertOk();

    $attachments = LetterAttachment::orderBy('id')->get();
    expect($attachments)->toHaveCount(3);

    $files = Storage::disk('local')->allFiles("attachments/letters/{$letter->id}");
    expect($files)->toHaveCount(3);

    foreach ($attachments as $attachment) {
        expect($attachment->path)->toStartWith("attachments/letters/{$letter->id}/")
            ->and(basename($attachment->path))->toMatch('/^[0-9a-f-]{36}\.(pdf|jpg|png)$/')
            ->and(in_array($attachment->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true))->toBeTrue()
            ->and($attachment->size_bytes)->toBeGreaterThan(0)
            ->and($attachment->checksum_sha256)->toBe(hash('sha256', Storage::disk('local')->get($attachment->path)));
    }

    $detail = $this->getJson("/api/letters/incoming/{$letter->id}")->assertOk()->json('data');
    expect($detail['attachments_count'])->toBe(3)
        ->and($detail['attachments'][0])->toHaveKeys(['id', 'original_name', 'mime_type', 'size_bytes'])
        ->and($detail['attachments'][0])->not->toHaveKey('path');

    $events = LetterEvent::where('letter_id', $letter->id)->where('event', 'attachment_added')->get();
    expect($events)->toHaveCount(3);

    foreach ($events as $event) {
        expect($event->metadata)->toHaveKeys(['attachment_id', 'mime_type', 'size_bytes']);
        expect($event->metadata)->not->toHaveKey('original_name');
        expect($event->metadata)->not->toHaveKey('path');
    }
});

// T12 — file berbahaya, MIME spoof, oversize, dan file ke-6.
it('rejects dangerous attachments', function () {
    Sanctum::actingAs($this->operator);
    $letter = p07Letter(['created_by' => $this->operator->id]);

    $dangerous = [
        p07RealUpload('shell.php', '<?php echo "x";'),
        p07RealUpload('payload.exe', 'MZ binary'),
        p07RealUpload('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        p07RealUpload('page.html', '<html></html>'),
        p07RealUpload('report.pdf.php', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n"),
        p07RealUpload('spoof.pdf', 'plain text pretending to be a PDF'),
    ];

    foreach ($dangerous as $file) {
        p07Upload($this, $letter->id, $file)
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    expect(LetterAttachment::count())->toBe(0);
    expect(LetterEvent::where('letter_id', $letter->id)->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);

    config(['letters.max_file_kb' => 1]);
    $oversize = "%PDF-1.4\n".str_repeat('A', 2048);
    p07Upload($this, $letter->id, p07RealUpload('oversize.pdf', $oversize))
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');

    config(['letters.max_file_kb' => 10240]);

    $tenMegabytes = "%PDF-1.4\n".str_repeat('A', (10 * 1024 * 1024) + 1024);
    p07Upload($this, $letter->id, p07RealUpload('oversize-real.pdf', $tenMegabytes))
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');

    for ($i = 1; $i <= 5; $i++) {
        p07Upload($this, $letter->id, p07Pdf("valid-{$i}.pdf"))->assertOk();
    }

    p07Upload($this, $letter->id, p07Pdf('sixth.pdf'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');

    expect(LetterAttachment::count())->toBe(5);
    expect(Storage::disk('local')->allFiles("attachments/letters/{$letter->id}"))->toHaveCount(5);
});

// T13 — hapus lampiran (soft delete, file tetap).
it('soft deletes attachments and keeps the file', function () {
    Sanctum::actingAs($this->operator);
    $letter = p07Letter(['created_by' => $this->operator->id]);

    p07Upload($this, $letter->id, p07Pdf())->assertOk();
    $attachment = LetterAttachment::firstOrFail();

    $this->deleteJson("/api/letters/incoming/{$letter->id}/attachments/{$attachment->id}")
        ->assertOk()
        ->assertJsonPath('data.attachments_count', 0);

    expect(LetterAttachment::withTrashed()->whereKey($attachment->id)->whereNotNull('deleted_at')->exists())->toBeTrue();
    expect(Storage::disk('local')->exists($attachment->path))->toBeTrue();
    expect(Letter::findOrFail($letter->id)->version)->toBe(1);

    expect(LetterEvent::where('letter_id', $letter->id)->where('event', 'attachment_removed')->exists())->toBeTrue();
    expect(Activity::where('table_name', 'letter_attachments')->where('record_id', $attachment->id)->where('information', 'Deleted')->exists())->toBeTrue();

    $this->getJson("/api/letters/incoming/{$letter->id}/attachments/{$attachment->id}/download")
        ->assertStatus(404);
});

// T14 — download berizin, header, dan 404.
it('streams authorized downloads with security headers', function () {
    Sanctum::actingAs($this->operator);
    $letter = p07Letter(['created_by' => $this->operator->id]);
    $other = p07Letter(['created_by' => $this->operator->id, 'source_number' => 'OTHER-1']);

    $content = "%PDF-1.4\nunique-content\n%%EOF\n";
    p07Upload($this, $letter->id, p07Pdf('agenda.pdf', $content))->assertOk();
    $attachment = LetterAttachment::firstOrFail();

    $response = $this->get("/api/letters/incoming/{$letter->id}/attachments/{$attachment->id}/download")
        ->assertOk()
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-security-policy', "sandbox; default-src 'none'")
        ->assertHeader('content-disposition', 'attachment; filename=agenda.pdf');

    expect($response->headers->get('cache-control'))->toContain('private')->toContain('no-store');

    expect($this->get("/api/letters/incoming/{$letter->id}/attachments/{$attachment->id}/download")->streamedContent())
        ->toBe($content);

    Sanctum::actingAs($this->principal);
    $this->get("/api/letters/incoming/{$letter->id}/attachments/{$attachment->id}/download")
        ->assertOk();

    Sanctum::actingAs($this->operator);
    $this->get("/api/letters/incoming/{$other->id}/attachments/{$attachment->id}/download")
        ->assertStatus(404);

    Storage::disk('local')->delete($attachment->path);
    $this->get("/api/letters/incoming/{$letter->id}/attachments/{$attachment->id}/download")
        ->assertStatus(404);
});

// T15 — search, filter, dan pagination.
it('filters and paginates the letter list', function () {
    Sanctum::actingAs($this->principal);

    p07Letter([
        'created_by' => $this->operator->id,
        'received_date' => '2026-09-01',
        'letter_date' => '2026-08-28',
        'source_number' => 'A-001',
        'sender' => 'Dinas Pendidikan',
        'subject' => 'Rapat kurikulum',
        'category' => 'Government',
    ]);
    p07Letter([
        'created_by' => $this->operator->id,
        'received_date' => '2026-10-01',
        'letter_date' => '2026-09-29',
        'source_number' => 'B-002',
        'sender' => 'Komite Sekolah',
        'subject' => 'Undangan sosialisasi',
        'category' => 'Invitation',
    ]);
    $voided = p07Letter([
        'created_by' => $this->operator->id,
        'received_date' => '2026-11-01',
        'letter_date' => '2026-10-30',
        'source_number' => 'C-003',
        'sender' => 'Yayasan Pendidikan',
        'subject' => 'Pemberitahuan libur',
        'category' => 'Notification',
        'status' => Letter::STATUS_VOID,
        'voided_at' => now(),
        'voided_by' => $this->operator->id,
        'void_reason' => 'Duplicate entry; the original letter is registered as #000045.',
    ]);

    $this->getJson('/api/letters/incoming')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2);

    $this->getJson('/api/letters/incoming?status=void')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.id', $voided->id);

    $this->getJson('/api/letters/incoming?status=all')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 3)
        ->assertJsonPath('data.items.0.id', $voided->id);

    $this->getJson('/api/letters/incoming?search=A-001')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1);

    $this->getJson('/api/letters/incoming?search=Komite')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1);

    $this->getJson('/api/letters/incoming?search='.urlencode('sosialisasi'))
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1);

    $this->getJson('/api/letters/incoming?category=Government')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1);

    $this->getJson('/api/letters/incoming?status=all&received_from=2026-09-15&received_to=2026-10-15')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.source_number', 'B-002');

    $this->getJson('/api/letters/incoming?status=all&per_page=10&page=2')
        ->assertOk()
        ->assertJsonPath('data.meta.per_page', 10)
        ->assertJsonPath('data.meta.total', 3)
        ->assertJsonPath('data.meta.last_page', 1)
        ->assertJsonCount(0, 'data.items');

    $this->getJson('/api/letters/incoming?search='.urlencode('%'))
        ->assertOk()
        ->assertJsonPath('data.meta.total', 0);

    $this->getJson('/api/letters/incoming?search='.urlencode('_'))
        ->assertOk()
        ->assertJsonPath('data.meta.total', 0);

    $this->getJson('/api/letters/incoming?per_page=99')->assertStatus(422);
    $this->getJson('/api/letters/incoming?received_from=2026-11-01&received_to=2026-10-01')->assertStatus(422);
});

// T16 — history dan audit tanpa PII.
it('records history and audit without personal or file details', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/incoming', p07Payload())->json('data');

    $this->putJson("/api/letters/incoming/{$created['id']}", p07Payload([
        'subject' => 'Undangan rapat koordinasi (revisi)',
        'expected_version' => 1,
    ]))->assertOk();

    p07Upload($this, $created['id'], p07Pdf('agenda-rahasia.pdf'))->assertOk();
    $attachment = LetterAttachment::firstOrFail();
    $this->deleteJson("/api/letters/incoming/{$created['id']}/attachments/{$attachment->id}")->assertOk();

    $this->postJson("/api/letters/incoming/{$created['id']}/void", [
        'reason' => 'Duplicate entry; the original letter is registered as #000045.',
        'expected_version' => 2,
    ])->assertOk();

    $detail = $this->getJson("/api/letters/incoming/{$created['id']}")->assertOk()->json('data');
    $eventNames = array_column($detail['events'], 'event');

    expect($eventNames)->toContain('voided')
        ->and($eventNames)->toContain('updated')
        ->and($eventNames)->toContain('attachment_added')
        ->and($eventNames)->toContain('attachment_removed');

    $updated = collect($detail['events'])->firstWhere('event', 'updated');
    expect(array_keys($updated['metadata']))->toBe(['changed_fields']);

    $added = collect($detail['events'])->firstWhere('event', 'attachment_added');
    expect(array_keys($added['metadata']))->toBe(['attachment_id', 'mime_type', 'size_bytes']);

    $auditPayload = Activity::whereIn('table_name', ['letters', 'letter_attachments'])
        ->get()
        ->map(fn (Activity $activity) => json_encode([
            $activity->before,
            $activity->after,
            $activity->new,
            $activity->delete,
        ]))
        ->implode(' ');

    expect($auditPayload)->not->toContain('Dinas Pendidikan')
        ->and($auditPayload)->not->toContain('Undangan rapat koordinasi')
        ->and($auditPayload)->not->toContain('front office')
        ->and($auditPayload)->not->toContain('Duplicate entry')
        ->and($auditPayload)->not->toContain('agenda-rahasia.pdf')
        ->and($auditPayload)->not->toContain('attachments/letters')
        ->and($auditPayload)->not->toContain($attachment->checksum_sha256);

    $eventsPayload = json_encode(LetterEvent::all()->map->only(['metadata']));
    expect($eventsPayload)->not->toContain('agenda-rahasia.pdf')
        ->and($eventsPayload)->not->toContain('attachments/letters')
        ->and($eventsPayload)->not->toContain('Dinas Pendidikan');
});

// T17 — rollback dan kompensasi file.
it('rolls back and removes files when events fail', function () {
    Sanctum::actingAs($this->operator);

    LetterEvent::$testCreatingHook = function () {
        throw new RuntimeException('Forced event failure.');
    };

    $this->withoutExceptionHandling();

    try {
        expect(fn () => $this->postJson('/api/letters/incoming', p07Payload()))
            ->toThrow(RuntimeException::class);
    } finally {
        LetterEvent::$testCreatingHook = null;
    }

    expect(Letter::count())->toBe(0);
    expect(Activity::where('table_name', 'letters')->count())->toBe(0);

    $letter = p07Letter(['created_by' => $this->operator->id]);

    LetterEvent::$testCreatingHook = function () {
        throw new RuntimeException('Forced event failure.');
    };

    try {
        expect(fn () => p07Upload($this, $letter->id, p07Pdf()))
            ->toThrow(RuntimeException::class);
    } finally {
        LetterEvent::$testCreatingHook = null;
    }

    expect(LetterAttachment::withTrashed()->count())->toBe(0);
    expect(LetterEvent::where('letter_id', $letter->id)->count())->toBe(0);
    expect(Storage::disk('local')->allFiles("attachments/letters/{$letter->id}"))->toBe([]);
});

// T18 — isolasi path dan route.
it('keeps attachments under the backup path and exposes twelve routes', function () {
    Sanctum::actingAs($this->operator);
    $letter = p07Letter(['created_by' => $this->operator->id]);

    p07Upload($this, $letter->id, p07Pdf())->assertOk();
    $attachment = LetterAttachment::firstOrFail();

    $normalize = fn (string $path) => rtrim(str_replace('\\', '/', $path), '/').'/';
    $backupRoot = $normalize((string) config('backup.attachments_path'));
    $logicalPath = $normalize(
        rtrim((string) config('filesystems.disks.local.root'), DIRECTORY_SEPARATOR)
        .DIRECTORY_SEPARATOR.$attachment->path
    );

    expect($logicalPath)->toStartWith($backupRoot)
        ->and(Storage::disk('public')->allFiles())->toBe([]);

    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/letters/incoming'));

    expect($routes)->toHaveCount(12);

    foreach ($routes as $route) {
        $middleware = $route->gatherMiddleware();
        $hasLetterAbility = collect($middleware)->contains(
            fn ($entry) => is_string($entry) && str_starts_with($entry, 'can:letters.'),
        );

        expect($hasLetterAbility)->toBeTrue();
    }
});

// Permission object-level: lampiran surat lain tidak dapat dihapus.
it('does not delete attachments through another letter', function () {
    Sanctum::actingAs($this->operator);

    $first = p07Letter(['created_by' => $this->operator->id]);
    $second = p07Letter(['created_by' => $this->operator->id, 'source_number' => 'SECOND-1']);

    p07Upload($this, $second->id, p07Pdf())->assertOk();
    $attachment = LetterAttachment::firstOrFail();

    $this->deleteJson("/api/letters/incoming/{$first->id}/attachments/{$attachment->id}")
        ->assertStatus(404);

    expect(LetterAttachment::whereKey($attachment->id)->whereNull('deleted_at')->exists())->toBeTrue();
});

// Direktori surat keluar belum diekspos pada 07a.
it('does not expose letters from other directions', function () {
    $outgoing = p07Letter([
        'created_by' => $this->operator->id,
        'direction' => Letter::DIRECTION_OUTGOING,
    ]);

    Sanctum::actingAs($this->principal);

    $this->getJson('/api/letters/incoming')->assertOk()->assertJsonPath('data.meta.total', 0);
    $this->getJson("/api/letters/incoming/{$outgoing->id}")->assertStatus(404);
    $this->getJson("/api/letters/incoming/{$outgoing->id}/attachments/1/download")->assertStatus(404);
});
