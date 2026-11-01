<?php

use App\Models\Activity;
use App\Models\Letter;
use App\Models\LetterApproval;
use App\Models\LetterAttachment;
use App\Models\LetterEvent;
use App\Models\LetterNumberCounter;
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

    config([
        'letters.numbering.school_code' => '20512345',
        'letters.numbering.format' => '{seq:03d}/{school_code}/{year}',
    ]);

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
function p07bPayload(array $overrides = []): array
{
    return array_merge([
        'received_date' => '2026-11-09',
        'letter_date' => '2026-11-08',
        'sender' => 'SMA Negeri 1',
        'recipient' => 'Dinas Pendidikan Kabupaten',
        'subject' => 'Laporan kegiatan sekolah',
        'category' => 'Notification',
        'notes' => 'Dikirim melalui kurir.',
    ], $overrides);
}

function p07bOutgoing(array $overrides = []): Letter
{
    return Letter::create(array_merge([
        'direction' => Letter::DIRECTION_OUTGOING,
        'version' => 1,
        'received_date' => '2026-11-09',
        'letter_date' => '2026-11-08',
        'source_number' => null,
        'sender' => 'SMA Negeri 1',
        'recipient' => 'Dinas Pendidikan Kabupaten',
        'subject' => 'Laporan kegiatan sekolah',
        'category' => 'Notification',
        'notes' => 'Dikirim melalui kurir.',
        'status' => Letter::STATUS_ACTIVE,
        'workflow_status' => Letter::WORKFLOW_DRAFT,
        'created_by' => 1,
    ], $overrides));
}

function p07bPdf(string $name = 'outgoing.pdf'): UploadedFile
{
    $content = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    return UploadedFile::fake()->createWithContent($name, $content);
}

function p07bUpload($test, string $url, UploadedFile $file)
{
    return $test->post($url, ['file' => $file], ['Accept' => 'application/json']);
}

function p07bAttachment(Letter $letter, int $userId): LetterAttachment
{
    return LetterAttachment::create([
        'letter_id' => $letter->id,
        'original_name' => 'draft.pdf',
        'path' => 'attachments/letters/'.$letter->id.'/'.uniqid('', true).'.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 100,
        'checksum_sha256' => str_repeat('a', 64),
        'uploaded_by' => $userId,
    ]);
}

// T1 — guest dan ability per peran.
it('enforces outgoing authentication and abilities', function () {
    $draft = p07bOutgoing(['created_by' => $this->operator->id]);
    $review = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_REVIEW,
    ]);

    $this->getJson('/api/letters/outgoing')->assertStatus(401);
    $this->getJson('/api/letters/outgoing/options')->assertStatus(401);
    $this->postJson('/api/letters/outgoing', p07bPayload())->assertStatus(401);

    Sanctum::actingAs($this->principal);
    $this->getJson('/api/letters/outgoing')->assertOk();
    $this->getJson('/api/letters/outgoing/options')->assertOk();
    $this->getJson("/api/letters/outgoing/{$draft->id}")->assertOk();
    $this->postJson('/api/letters/outgoing', p07bPayload())->assertStatus(403);
    $this->putJson("/api/letters/outgoing/{$draft->id}", p07bPayload(['expected_version' => 1]))
        ->assertStatus(403);
    $this->postJson("/api/letters/outgoing/{$draft->id}/submit", ['expected_version' => 1])
        ->assertStatus(403);
    p07bUpload($this, "/api/letters/outgoing/{$draft->id}/attachments", p07bPdf())
        ->assertStatus(403);
    $this->postJson("/api/letters/outgoing/{$review->id}/approve", ['expected_version' => 1])
        ->assertOk();

    Sanctum::actingAs($this->wakil);
    $this->getJson('/api/letters/outgoing')->assertOk();
    $this->postJson('/api/letters/outgoing', p07bPayload())->assertStatus(403);
    $this->postJson("/api/letters/outgoing/{$draft->id}/cancel", [
        'reason' => 'No longer required by the unit.',
        'expected_version' => 1,
    ])->assertStatus(403);

    Sanctum::actingAs($this->operator);
    $this->postJson('/api/letters/outgoing', p07bPayload())->assertOk();
    $this->postJson("/api/letters/outgoing/{$review->id}/approve", ['expected_version' => 1])
        ->assertStatus(403);
    $this->postJson("/api/letters/outgoing/{$review->id}/reject", [
        'reason' => 'The content is incomplete.',
        'expected_version' => 1,
    ])->assertStatus(403);

    Sanctum::actingAs($this->admin);
    $this->postJson('/api/letters/outgoing', p07bPayload(['recipient' => 'Admin Recipient']))->assertOk();
    $this->postJson("/api/letters/outgoing/{$review->id}/approve", ['expected_version' => 1])
        ->assertStatus(403);
});

// T2 — create draft valid.
it('creates an outgoing draft with event and audit', function () {
    Sanctum::actingAs($this->operator);

    $response = $this->postJson('/api/letters/outgoing', p07bPayload());

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.direction', 'outgoing')
        ->assertJsonPath('data.workflow_status', 'draft')
        ->assertJsonPath('data.lifecycle_status', 'draft')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.official_number', null)
        ->assertJsonPath('data.recipient', 'Dinas Pendidikan Kabupaten')
        ->assertJsonPath('data.attachments_count', 0)
        ->assertJsonPath('data.events.0.event', 'created')
        ->assertJsonPath('data.events.0.actor.id', $this->operator->id)
        ->assertJsonPath('data.approvals', [])
        ->assertJsonPath('data.dispositions', []);

    $letter = Letter::firstOrFail();

    expect($letter->direction)->toBe(Letter::DIRECTION_OUTGOING)
        ->and($letter->workflow_status)->toBe(Letter::WORKFLOW_DRAFT)
        ->and($letter->official_number)->toBeNull()
        ->and($letter->created_by)->toBe($this->operator->id);

    expect(LetterEvent::where('letter_id', $letter->id)->where('event', 'created')->exists())->toBeTrue();
    expect(Activity::where('table_name', 'letters')->where('record_id', $letter->id)
        ->where('information', 'Created')->where('user_id', $this->operator->id)->exists())->toBeTrue();
});

// T3 — validasi create.
it('validates outgoing create payloads', function () {
    Sanctum::actingAs($this->operator);

    $invalid = [
        ['received_date' => null],
        ['letter_date' => null],
        ['sender' => null],
        ['recipient' => null],
        ['subject' => null],
        ['category' => 'Unknown'],
        ['notes' => str_repeat('x', 2001)],
        ['received_date' => '2026-11-11'],
        ['letter_date' => '2026-11-10'],
        ['recipient' => str_repeat('R', 151)],
    ];

    foreach ($invalid as $overrides) {
        $this->postJson('/api/letters/outgoing', p07bPayload($overrides))->assertStatus(422);
    }

    expect(Letter::count())->toBe(0);
});

// T4 — edit draft berversi.
it('updates outgoing drafts with versioning and audit', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/outgoing', p07bPayload())->assertOk();
    $id = (int) $created->json('data.id');

    $decoy = p07bOutgoing([
        'created_by' => $this->operator->id,
        'subject' => 'Decoy letter',
        'recipient' => 'Unchanged recipient',
    ]);

    $updated = $this->putJson("/api/letters/outgoing/{$id}", p07bPayload([
        'subject' => 'Revisi laporan kegiatan',
        'recipient' => 'Kantor Wilayah',
        'notes' => null,
        'expected_version' => 1,
    ]))
        ->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.subject', 'Revisi laporan kegiatan')
        ->assertJsonPath('data.recipient', 'Kantor Wilayah')
        ->assertJsonPath('data.notes', null)
        ->assertJsonPath('data.events.0.event', 'updated');

    expect($updated->json('data.events.0.metadata.changed_fields'))
        ->toContain('subject')
        ->toContain('recipient')
        ->toContain('notes')
        ->not->toContain('sender');

    expect($decoy->fresh()->subject)->toBe('Decoy letter')
        ->and($decoy->fresh()->recipient)->toBe('Unchanged recipient');

    $this->putJson("/api/letters/outgoing/{$id}", p07bPayload([
        'subject' => 'Stale update',
        'expected_version' => 1,
    ]))
        ->assertStatus(409)
        ->assertJsonPath('current_versions.letter_version', 2)
        ->assertJsonPath('current_versions.workflow_status', 'draft');
});

// T5 — non-draft read-only untuk edit dan lampiran.
it('keeps non-draft outgoing letters read-only for edits and attachments', function () {
    Sanctum::actingAs($this->operator);

    $states = [
        ['workflow' => Letter::WORKFLOW_REVIEW, 'status' => Letter::STATUS_ACTIVE],
        ['workflow' => Letter::WORKFLOW_APPROVED, 'status' => Letter::STATUS_ACTIVE],
        ['workflow' => Letter::WORKFLOW_ISSUED, 'status' => Letter::STATUS_ACTIVE],
        ['workflow' => Letter::WORKFLOW_CANCELLED, 'status' => Letter::STATUS_VOID],
    ];

    foreach ($states as $index => $state) {
        $letter = p07bOutgoing([
            'created_by' => $this->operator->id,
            'workflow_status' => $state['workflow'],
            'status' => $state['status'],
            'official_number' => $state['workflow'] === Letter::WORKFLOW_ISSUED
                ? 'PRE-'.$index.'/20512345/2026'
                : null,
            'voided_at' => $state['status'] === Letter::STATUS_VOID ? Carbon::now() : null,
            'voided_by' => $state['status'] === Letter::STATUS_VOID ? $this->operator->id : null,
            'void_reason' => $state['status'] === Letter::STATUS_VOID ? 'Cancelled for the test.' : null,
        ]);

        $attachment = p07bAttachment($letter, $this->operator->id);

        $this->putJson("/api/letters/outgoing/{$letter->id}", p07bPayload(['expected_version' => 1]))
            ->assertStatus(409);
        p07bUpload($this, "/api/letters/outgoing/{$letter->id}/attachments", p07bPdf())
            ->assertStatus(409);
        $this->deleteJson("/api/letters/outgoing/{$letter->id}/attachments/{$attachment->id}")
            ->assertStatus(409);

        expect($letter->fresh()->workflow_status)->toBe($state['workflow']);
        expect(LetterAttachment::whereKey($attachment->id)->whereNull('deleted_at')->exists())->toBeTrue();
    }
});

// T6 — submit membekukan edit.
it('submits drafts and blocks edits after submission', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/outgoing', p07bPayload())->assertOk();
    $id = (int) $created->json('data.id');

    $this->postJson("/api/letters/outgoing/{$id}/submit", ['expected_version' => 1])
        ->assertOk()
        ->assertJsonPath('data.workflow_status', 'review')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.events.0.event', 'submitted')
        ->assertJsonPath('data.events.0.metadata.from', 'draft')
        ->assertJsonPath('data.events.0.metadata.to', 'review');

    $this->putJson("/api/letters/outgoing/{$id}", p07bPayload(['expected_version' => 2]))
        ->assertStatus(409);
});

// T7 — approve mencatat ledger dan bukan pembuat.
it('approves reviewed letters and records the approval ledger', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/outgoing', p07bPayload())->assertOk();
    $id = (int) $created->json('data.id');
    $this->postJson("/api/letters/outgoing/{$id}/submit", ['expected_version' => 1])->assertOk();

    $decoy = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_REVIEW,
        'subject' => 'Decoy review',
    ]);

    Sanctum::actingAs($this->principal);

    $this->postJson("/api/letters/outgoing/{$id}/approve", [
        'notes' => 'Approved for distribution.',
        'expected_version' => 2,
    ])
        ->assertOk()
        ->assertJsonPath('data.workflow_status', 'approved')
        ->assertJsonPath('data.version', 3)
        ->assertJsonPath('data.approved_by.id', $this->principal->id)
        ->assertJsonPath('data.events.0.event', 'approved')
        ->assertJsonPath('data.approvals.0.action', 'approved')
        ->assertJsonPath('data.approvals.0.notes', 'Approved for distribution.')
        ->assertJsonPath('data.approvals.0.actor.id', $this->principal->id);

    $letter = Letter::findOrFail($id);
    expect($letter->approved_by)->toBe($this->principal->id)
        ->and($letter->approved_at)->not->toBeNull();

    expect(LetterApproval::where('letter_id', $id)->where('action', 'approved')->count())->toBe(1);
    expect($decoy->fresh()->workflow_status)->toBe(Letter::WORKFLOW_REVIEW);
});

// T8 — self approval dan tanpa ability.
it('prevents self approval and approval without ability', function () {
    $self = p07bOutgoing([
        'created_by' => $this->principal->id,
        'workflow_status' => Letter::WORKFLOW_REVIEW,
    ]);
    $other = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_REVIEW,
    ]);

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$self->id}/approve", ['expected_version' => 1])
        ->assertStatus(403);

    Sanctum::actingAs($this->operator);
    $this->postJson("/api/letters/outgoing/{$other->id}/approve", ['expected_version' => 1])
        ->assertStatus(403);

    expect($self->fresh()->workflow_status)->toBe(Letter::WORKFLOW_REVIEW)
        ->and($other->fresh()->workflow_status)->toBe(Letter::WORKFLOW_REVIEW)
        ->and(LetterApproval::count())->toBe(0);
});

// T9 — reject kembali ke draft.
it('rejects reviewed letters back to draft', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/outgoing', p07bPayload())->assertOk();
    $id = (int) $created->json('data.id');
    $this->postJson("/api/letters/outgoing/{$id}/submit", ['expected_version' => 1])->assertOk();

    Sanctum::actingAs($this->principal);

    $this->postJson("/api/letters/outgoing/{$id}/reject", [
        'reason' => 'The attachment is still incomplete.',
        'expected_version' => 2,
    ])
        ->assertOk()
        ->assertJsonPath('data.workflow_status', 'draft')
        ->assertJsonPath('data.version', 3)
        ->assertJsonPath('data.events.0.event', 'rejected')
        ->assertJsonPath('data.approvals.0.action', 'rejected')
        ->assertJsonPath('data.approvals.0.notes', 'The attachment is still incomplete.');

    Sanctum::actingAs($this->operator);

    $this->putJson("/api/letters/outgoing/{$id}", p07bPayload([
        'subject' => 'Rejected letter revised',
        'expected_version' => 3,
    ]))
        ->assertOk()
        ->assertJsonPath('data.workflow_status', 'draft')
        ->assertJsonPath('data.version', 4);
});

// T10 — reject invalid dan versi basi.
it('validates rejection reasons and versions', function () {
    $letter = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_REVIEW,
    ]);

    Sanctum::actingAs($this->principal);

    $this->postJson("/api/letters/outgoing/{$letter->id}/reject", [
        'reason' => 'short',
        'expected_version' => 1,
    ])->assertStatus(422);

    $this->postJson("/api/letters/outgoing/{$letter->id}/reject", [
        'reason' => 'A valid rejection reason.',
        'expected_version' => 99,
    ])->assertStatus(409);

    expect($letter->fresh()->workflow_status)->toBe(Letter::WORKFLOW_REVIEW)
        ->and(LetterApproval::count())->toBe(0);
});

// T11 — issue atomik.
it('issues approved letters with atomic sequential numbers', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/outgoing', p07bPayload())->assertOk();
    $firstId = (int) $created->json('data.id');
    $this->postJson("/api/letters/outgoing/{$firstId}/submit", ['expected_version' => 1])->assertOk();

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$firstId}/approve", ['expected_version' => 2])->assertOk();

    $this->postJson("/api/letters/outgoing/{$firstId}/issue", ['expected_version' => 3])
        ->assertOk()
        ->assertJsonPath('data.workflow_status', 'issued')
        ->assertJsonPath('data.official_number', '001/20512345/2026')
        ->assertJsonPath('data.issued_by.id', $this->principal->id)
        ->assertJsonPath('data.version', 4)
        ->assertJsonPath('data.events.0.event', 'issued')
        ->assertJsonPath('data.events.0.metadata.official_number', '001/20512345/2026')
        ->assertJsonPath('data.events.0.metadata.period', '2026')
        ->assertJsonPath('data.events.0.metadata.sequence', 1);

    expect(Letter::findOrFail($firstId)->issued_at)->not->toBeNull();
    expect(LetterNumberCounter::where('scope', 'outgoing')->where('period', '2026')->value('last_number'))->toBe(1);

    foreach ([2, 3] as $sequence) {
        $letter = p07bOutgoing([
            'created_by' => $this->operator->id,
            'workflow_status' => Letter::WORKFLOW_APPROVED,
        ]);

        $this->postJson("/api/letters/outgoing/{$letter->id}/issue", ['expected_version' => 1])
            ->assertOk()
            ->assertJsonPath('data.official_number', str_pad((string) $sequence, 3, '0', STR_PAD_LEFT).'/20512345/2026');
    }

    expect(Letter::where('direction', 'outgoing')->whereNotNull('official_number')->count())->toBe(3)
        ->and(Letter::where('direction', 'outgoing')->distinct()->count('official_number'))->toBe(3)
        ->and(LetterNumberCounter::where('scope', 'outgoing')->where('period', '2026')->value('last_number'))->toBe(3);
});

// T12 — issue ditolak tanpa nomor baru.
it('blocks issue for self, missing ability, and wrong status without consuming a number', function () {
    $self = p07bOutgoing([
        'created_by' => $this->principal->id,
        'workflow_status' => Letter::WORKFLOW_APPROVED,
    ]);
    $review = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_REVIEW,
    ]);
    $approved = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_APPROVED,
    ]);

    Sanctum::actingAs($this->operator);
    $this->postJson("/api/letters/outgoing/{$approved->id}/issue", ['expected_version' => 1])
        ->assertStatus(403);

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$self->id}/issue", ['expected_version' => 1])
        ->assertStatus(403);
    $this->postJson("/api/letters/outgoing/{$review->id}/issue", ['expected_version' => 1])
        ->assertStatus(409);

    expect($self->fresh()->official_number)->toBeNull()
        ->and($review->fresh()->workflow_status)->toBe(Letter::WORKFLOW_REVIEW)
        ->and($approved->fresh()->workflow_status)->toBe(Letter::WORKFLOW_APPROVED)
        ->and(LetterNumberCounter::count())->toBe(0);
});

// T13 — replay issue hanya menghasilkan satu nomor.
it('does not issue twice and reports the existing number', function () {
    $letter = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_APPROVED,
    ]);

    Sanctum::actingAs($this->principal);

    $this->postJson("/api/letters/outgoing/{$letter->id}/issue", ['expected_version' => 1])
        ->assertOk()
        ->assertJsonPath('data.official_number', '001/20512345/2026');

    $this->postJson("/api/letters/outgoing/{$letter->id}/issue", ['expected_version' => 1])
        ->assertStatus(409)
        ->assertJsonPath('current_versions.official_number', '001/20512345/2026')
        ->assertJsonPath('current_versions.workflow_status', 'issued');

    $this->postJson("/api/letters/outgoing/{$letter->id}/issue", ['expected_version' => 2])
        ->assertStatus(409);

    expect(LetterEvent::where('letter_id', $letter->id)->where('event', 'issued')->count())->toBe(1)
        ->and(LetterNumberCounter::where('scope', 'outgoing')->where('period', '2026')->value('last_number'))->toBe(1);
});

// T14/T15 — reset periode dan format invalid.
it('resets numbering per calendar year and rejects invalid formats', function () {
    $first = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_APPROVED,
    ]);

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$first->id}/issue", ['expected_version' => 1])
        ->assertOk()
        ->assertJsonPath('data.official_number', '001/20512345/2026');

    Carbon::setTestNow(Carbon::create(2027, 1, 2, 9, 0, 0, 'Asia/Jakarta'));

    $second = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_APPROVED,
    ]);

    $this->postJson("/api/letters/outgoing/{$second->id}/issue", ['expected_version' => 1])
        ->assertOk()
        ->assertJsonPath('data.official_number', '001/20512345/2027');

    expect(LetterNumberCounter::where('period', '2026')->value('last_number'))->toBe(1)
        ->and(LetterNumberCounter::where('period', '2027')->value('last_number'))->toBe(1);

    config(['letters.numbering.format' => '{seq:03d}/{bogus}/{year}']);

    $third = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_APPROVED,
    ]);

    $this->postJson("/api/letters/outgoing/{$third->id}/issue", ['expected_version' => 1])
        ->assertStatus(422);

    expect($third->fresh()->workflow_status)->toBe(Letter::WORKFLOW_APPROVED)
        ->and($third->fresh()->official_number)->toBeNull()
        ->and(LetterNumberCounter::where('period', '2027')->value('last_number'))->toBe(1);

    config(['letters.numbering.format' => '{seq:03d}/{school_code}/{year}', 'letters.numbering.school_code' => null]);

    $fourth = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_APPROVED,
    ]);

    $this->postJson("/api/letters/outgoing/{$fourth->id}/issue", ['expected_version' => 1])
        ->assertStatus(422);

    expect($fourth->fresh()->workflow_status)->toBe(Letter::WORKFLOW_APPROVED)
        ->and($fourth->fresh()->official_number)->toBeNull();
});

// T16 — cancel draft/review tanpa nomor.
it('cancels draft and review letters without a number', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/outgoing', p07bPayload())->assertOk();
    $draftId = (int) $created->json('data.id');

    $this->postJson("/api/letters/outgoing/{$draftId}/cancel", [
        'reason' => 'Cancelled before submission.',
        'expected_version' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('data.workflow_status', 'cancelled')
        ->assertJsonPath('data.status', 'void')
        ->assertJsonPath('data.official_number', null)
        ->assertJsonPath('data.cancel_reason', 'Cancelled before submission.')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.events.0.event', 'cancelled')
        ->assertJsonPath('data.events.0.metadata.from_status', 'draft');

    $review = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_REVIEW,
    ]);

    $this->postJson("/api/letters/outgoing/{$review->id}/cancel", [
        'reason' => 'Cancelled while still in review.',
        'expected_version' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('data.workflow_status', 'cancelled')
        ->assertJsonPath('data.official_number', null)
        ->assertJsonPath('data.events.0.metadata.from_status', 'review');
});

// T17 — cancel approved/issued oleh approve; nomor dipertahankan.
it('cancels approved and issued letters with the approve ability and keeps the number', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/outgoing', p07bPayload())->assertOk();
    $id = (int) $created->json('data.id');
    $this->postJson("/api/letters/outgoing/{$id}/submit", ['expected_version' => 1])->assertOk();

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$id}/approve", ['expected_version' => 2])->assertOk();
    $this->postJson("/api/letters/outgoing/{$id}/issue", ['expected_version' => 3])
        ->assertOk()
        ->assertJsonPath('data.official_number', '001/20512345/2026');

    Sanctum::actingAs($this->operator);
    $this->postJson("/api/letters/outgoing/{$id}/cancel", [
        'reason' => 'Operator cannot cancel an issued letter.',
        'expected_version' => 4,
    ])->assertStatus(403);

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$id}/cancel", [
        'reason' => 'School leadership cancelled the issued letter.',
        'expected_version' => 4,
    ])
        ->assertOk()
        ->assertJsonPath('data.workflow_status', 'cancelled')
        ->assertJsonPath('data.official_number', '001/20512345/2026')
        ->assertJsonPath('data.events.0.metadata.from_status', 'issued');

    expect(LetterNumberCounter::where('scope', 'outgoing')->where('period', '2026')->value('last_number'))->toBe(1);

    $approved = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_APPROVED,
    ]);

    $this->postJson("/api/letters/outgoing/{$approved->id}/cancel", [
        'reason' => 'Approved letter no longer needed.',
        'expected_version' => 1,
    ])->assertOk()->assertJsonPath('data.official_number', null);
});

// T18 — cancelled read-only.
it('keeps cancelled outgoing letters read-only', function () {
    $letter = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_CANCELLED,
        'status' => Letter::STATUS_VOID,
        'official_number' => '009/20512345/2026',
        'voided_at' => Carbon::now(),
        'voided_by' => $this->principal->id,
        'void_reason' => 'Cancelled permanently for the test.',
    ]);

    $attachment = p07bAttachment($letter, $this->operator->id);

    Sanctum::actingAs($this->operator);
    $this->putJson("/api/letters/outgoing/{$letter->id}", p07bPayload(['expected_version' => 1]))
        ->assertStatus(409);
    $this->postJson("/api/letters/outgoing/{$letter->id}/submit", ['expected_version' => 1])
        ->assertStatus(409);
    p07bUpload($this, "/api/letters/outgoing/{$letter->id}/attachments", p07bPdf())
        ->assertStatus(409);
    $this->deleteJson("/api/letters/outgoing/{$letter->id}/attachments/{$attachment->id}")
        ->assertStatus(409);

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$letter->id}/cancel", [
        'reason' => 'A repeated cancellation attempt.',
        'expected_version' => 1,
    ])->assertStatus(409);
    $this->postJson("/api/letters/outgoing/{$letter->id}/approve", ['expected_version' => 1])
        ->assertStatus(409);
    $this->postJson("/api/letters/outgoing/{$letter->id}/issue", ['expected_version' => 1])
        ->assertStatus(409);

    expect($letter->fresh()->workflow_status)->toBe(Letter::WORKFLOW_CANCELLED)
        ->and($letter->fresh()->official_number)->toBe('009/20512345/2026')
        ->and($letter->fresh()->version)->toBe(1);
});

// T24 — filter dan pagination outgoing.
it('filters and paginates outgoing letters', function () {
    Sanctum::actingAs($this->wakil);

    p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_DRAFT,
        'subject' => 'Alpha draft',
        'recipient' => 'Alpha Recipient',
        'letter_date' => '2026-11-01',
    ]);
    p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_ISSUED,
        'subject' => 'Beta issued',
        'recipient' => 'Beta Recipient',
        'official_number' => '005/20512345/2026',
        'letter_date' => '2026-11-02',
    ]);
    p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_REVIEW,
        'subject' => 'Gamma review',
        'recipient' => 'Gamma Recipient',
        'letter_date' => '2026-11-03',
    ]);

    $this->getJson('/api/letters/outgoing')->assertOk()
        ->assertJsonPath('data.meta.total', 3)
        ->assertJsonPath('data.items.0.subject', 'Gamma review');

    $this->getJson('/api/letters/outgoing?workflow_status=issued')->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.official_number', '005/20512345/2026');

    $this->getJson('/api/letters/outgoing?search=Beta')->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.subject', 'Beta issued');

    $this->getJson('/api/letters/outgoing?search=005/20512345')->assertOk()
        ->assertJsonPath('data.meta.total', 1);

    $this->getJson('/api/letters/outgoing?letter_from=2026-11-02&letter_to=2026-11-03')->assertOk()
        ->assertJsonPath('data.meta.total', 2);

    $this->getJson('/api/letters/outgoing?search=%25')->assertOk()
        ->assertJsonPath('data.meta.total', 0);

    $this->getJson('/api/letters/outgoing?per_page=10&page=1')->assertOk()
        ->assertJsonPath('data.meta.per_page', 10)
        ->assertJsonPath('data.meta.total', 3);
});

// T25 — history dan audit tanpa PII.
it('records outgoing history and audit without personal details', function () {
    Sanctum::actingAs($this->operator);

    $created = $this->postJson('/api/letters/outgoing', p07bPayload())->assertOk();
    $id = (int) $created->json('data.id');
    $this->postJson("/api/letters/outgoing/{$id}/submit", ['expected_version' => 1])->assertOk();

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$id}/approve", [
        'notes' => 'Sensitive approval note.',
        'expected_version' => 2,
    ])->assertOk();
    $this->postJson("/api/letters/outgoing/{$id}/issue", ['expected_version' => 3])->assertOk();
    $this->postJson("/api/letters/outgoing/{$id}/cancel", [
        'reason' => 'Sensitive cancellation reason.',
        'expected_version' => 4,
    ])->assertOk();

    $auditPayload = Activity::whereIn('table_name', ['letters', 'letter_approvals', 'letter_events'])
        ->get()
        ->map(fn (Activity $activity) => json_encode([
            $activity->before,
            $activity->after,
            $activity->new,
            $activity->delete,
        ]))
        ->implode(' ');

    expect($auditPayload)->not->toContain('Laporan kegiatan sekolah')
        ->and($auditPayload)->not->toContain('Dinas Pendidikan Kabupaten')
        ->and($auditPayload)->not->toContain('Dikirim melalui kurir')
        ->and($auditPayload)->not->toContain('Sensitive approval note')
        ->and($auditPayload)->not->toContain('Sensitive cancellation reason')
        ->and($auditPayload)->not->toContain('attachments/letters');

    expect(Activity::where('table_name', 'letter_approvals')->count())->toBe(0)
        ->and(Activity::where('table_name', 'letter_events')->count())->toBe(0);

    $eventsPayload = json_encode(LetterEvent::all()->map->only(['metadata']));
    expect($eventsPayload)->not->toContain('Laporan kegiatan sekolah')
        ->and($eventsPayload)->not->toContain('Dinas Pendidikan Kabupaten')
        ->and($eventsPayload)->not->toContain('Sensitive approval note');

    $approval = LetterApproval::firstOrFail();
    expect(fn () => $approval->update(['notes' => 'Tampered.']))->toThrow(RuntimeException::class);
    expect(fn () => $approval->delete())->toThrow(RuntimeException::class);
});

// T26 — rollback issue/approval/upload.
it('rolls back issue, approval, and attachment changes when events fail', function () {
    $approved = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_APPROVED,
    ]);

    Sanctum::actingAs($this->principal);
    $this->withoutExceptionHandling();

    LetterEvent::$testCreatingHook = function (LetterEvent $event) {
        if ($event->event === 'issued') {
            throw new RuntimeException('Forced issue event failure.');
        }
    };

    try {
        expect(fn () => $this->postJson("/api/letters/outgoing/{$approved->id}/issue", ['expected_version' => 1]))
            ->toThrow(RuntimeException::class);
    } finally {
        LetterEvent::$testCreatingHook = null;
    }

    expect($approved->fresh()->workflow_status)->toBe(Letter::WORKFLOW_APPROVED)
        ->and($approved->fresh()->official_number)->toBeNull()
        ->and($approved->fresh()->version)->toBe(1)
        ->and(LetterNumberCounter::count())->toBe(0)
        ->and(LetterEvent::where('letter_id', $approved->id)->where('event', 'issued')->count())->toBe(0);

    $review = p07bOutgoing([
        'created_by' => $this->operator->id,
        'workflow_status' => Letter::WORKFLOW_REVIEW,
    ]);

    LetterEvent::$testCreatingHook = function (LetterEvent $event) {
        if ($event->event === 'approved') {
            throw new RuntimeException('Forced approval event failure.');
        }
    };

    try {
        expect(fn () => $this->postJson("/api/letters/outgoing/{$review->id}/approve", ['expected_version' => 1]))
            ->toThrow(RuntimeException::class);
    } finally {
        LetterEvent::$testCreatingHook = null;
    }

    expect($review->fresh()->workflow_status)->toBe(Letter::WORKFLOW_REVIEW)
        ->and($review->fresh()->version)->toBe(1)
        ->and(LetterApproval::where('letter_id', $review->id)->count())->toBe(0)
        ->and(LetterEvent::where('letter_id', $review->id)->where('event', 'approved')->count())->toBe(0);

    $draft = p07bOutgoing(['created_by' => $this->operator->id]);

    Sanctum::actingAs($this->operator);

    LetterEvent::$testCreatingHook = function () {
        throw new RuntimeException('Forced attachment event failure.');
    };

    try {
        expect(fn () => p07bUpload($this, "/api/letters/outgoing/{$draft->id}/attachments", p07bPdf()))
            ->toThrow(RuntimeException::class);
    } finally {
        LetterEvent::$testCreatingHook = null;
    }

    expect(LetterAttachment::where('letter_id', $draft->id)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles("attachments/letters/{$draft->id}"))->toBe([]);
});

// Route inventory.
it('exposes outgoing routes with letter abilities', function () {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/letters/outgoing'));

    expect($routes)->toHaveCount(13);

    foreach ($routes as $route) {
        $middleware = $route->gatherMiddleware();
        $hasLetterAbility = collect($middleware)->contains(
            fn ($entry) => is_string($entry) && str_starts_with($entry, 'can:letters.'),
        );

        expect($hasLetterAbility)->toBeTrue();
    }
});
