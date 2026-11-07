<?php

use App\Models\ArchiveDocument;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 10, 9, 0, 0, 'Asia/Jakarta'));
    Storage::fake('local');

    $this->seedAccessReferenceData();

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->plainUser = $this->makeUserWithRole();
});

function createDummyArchiveDoc(string $title, int $version = 1, ?string $archivedAt = null): ArchiveDocument
{
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
    $path = $file->store('archives/2026/11');

    $doc = ArchiveDocument::create([
        'category' => 'decree',
        'title' => $title,
        'document_date' => '2026-11-01',
        'version' => $version,
        'current_version' => 1,
        'created_by' => test()->operator->id,
        'updated_by' => test()->operator->id,
        'archived_at' => $archivedAt,
        'archive_reason' => $archivedAt ? 'Arsip awal' : null,
        'archived_by' => $archivedAt ? test()->operator->id : null,
    ]);

    $doc->versions()->create([
        'version_number' => 1,
        'original_name' => 'doc.pdf',
        'path' => $path,
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'checksum_sha256' => hash('sha256', 'dummy content'),
        'uploaded_by' => test()->operator->id,
    ]);

    return $doc;
}

test('guest or unauthorized user cannot perform archive batch actions', function () {
    $this->postJson('/api/archives/batch-archive', [
        'items' => [['id' => 1, 'expected_version' => 1]],
        'archive_reason' => 'Alasan arsip yang cukup panjang',
    ])->assertStatus(401);

    Sanctum::actingAs($this->plainUser);
    $this->postJson('/api/archives/batch-archive', [
        'items' => [['id' => 1, 'expected_version' => 1]],
        'archive_reason' => 'Alasan arsip yang cukup panjang',
    ])->assertStatus(403);
});

test('batch archive archives multiple documents atomically', function () {
    Sanctum::actingAs($this->operator);

    $d1 = createDummyArchiveDoc('Dokumen Arsip 1');
    $d2 = createDummyArchiveDoc('Dokumen Arsip 2');

    $response = $this->postJson('/api/archives/batch-archive', [
        'items' => [
            ['id' => $d1->id, 'expected_version' => 1],
            ['id' => $d2->id, 'expected_version' => 1],
        ],
        'archive_reason' => 'Pengarsipan dokumen lama semester 1',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Successfully archived 2 archive document(s).',
            'data' => [
                'archived_count' => 2,
            ],
        ]);

    expect($d1->fresh()->isArchived())->toBeTrue();
    expect($d2->fresh()->isArchived())->toBeTrue();
    expect($d1->fresh()->version)->toBe(2);
    expect($d2->fresh()->version)->toBe(2);
});

test('batch archive rolls back entirely on version mismatch', function () {
    Sanctum::actingAs($this->operator);

    $d1 = createDummyArchiveDoc('Dokumen Arsip 1', 1);
    $d2 = createDummyArchiveDoc('Dokumen Arsip 2', 5);

    $response = $this->postJson('/api/archives/batch-archive', [
        'items' => [
            ['id' => $d1->id, 'expected_version' => 1],
            ['id' => $d2->id, 'expected_version' => 1], // outdated expected_version
        ],
        'archive_reason' => 'Pengarsipan dokumen',
    ]);

    $response->assertStatus(409);

    expect($d1->fresh()->isArchived())->toBeFalse();
    expect($d2->fresh()->isArchived())->toBeFalse();
});

test('batch restore restores multiple archived documents', function () {
    Sanctum::actingAs($this->operator);

    $d1 = createDummyArchiveDoc('Dokumen Arsip 1', 2, now()->toDateTimeString());
    $d2 = createDummyArchiveDoc('Dokumen Arsip 2', 3, now()->toDateTimeString());

    $response = $this->postJson('/api/archives/batch-restore', [
        'items' => [
            ['id' => $d1->id, 'expected_version' => 2],
            ['id' => $d2->id, 'expected_version' => 3],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Successfully restored 2 archive document(s).',
            'data' => [
                'restored_count' => 2,
            ],
        ]);

    expect($d1->fresh()->isArchived())->toBeFalse();
    expect($d2->fresh()->isArchived())->toBeFalse();
    expect($d1->fresh()->version)->toBe(3);
    expect($d2->fresh()->version)->toBe(4);
});
