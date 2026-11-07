<?php

use App\Models\Letter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 10, 9, 0, 0, 'Asia/Jakarta'));
    Storage::fake('local');
    Storage::fake('public');

    $this->seedAccessReferenceData();

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->plainUser = $this->makeUserWithRole();
});

afterEach(function () {
    Carbon::setTestNow();
});

function createDummyIncomingLetter(string $subject, int $version = 1, string $status = 'active'): Letter
{
    return Letter::create([
        'direction' => Letter::DIRECTION_INCOMING,
        'version' => $version,
        'status' => $status,
        'received_date' => '2026-11-05',
        'letter_date' => '2026-11-03',
        'source_number' => '005/123/DISDIK/2026',
        'sender' => 'Dinas Pendidikan Kabupaten',
        'subject' => $subject,
        'category' => 'Invitation',
        'notes' => 'Catatan surat masuk.',
        'created_by' => test()->operator->id,
        'updated_by' => test()->operator->id,
    ]);
}

test('guest or unauthorized user cannot perform incoming letters batch void', function () {
    $this->postJson('/api/letters/incoming/batch-void', [
        'items' => [['id' => 1, 'expected_version' => 1]],
        'reason' => 'Alasan pembatalan surat masuk yang cukup panjang',
    ])->assertStatus(401);

    Sanctum::actingAs($this->plainUser);
    $this->postJson('/api/letters/incoming/batch-void', [
        'items' => [['id' => 1, 'expected_version' => 1]],
        'reason' => 'Alasan pembatalan surat masuk yang cukup panjang',
    ])->assertStatus(403);
});

test('batch void voids multiple incoming letters atomically', function () {
    Sanctum::actingAs($this->operator);

    $l1 = createDummyIncomingLetter('Surat Masuk 1');
    $l2 = createDummyIncomingLetter('Surat Masuk 2');

    $response = $this->postJson('/api/letters/incoming/batch-void', [
        'items' => [
            ['id' => $l1->id, 'expected_version' => 1],
            ['id' => $l2->id, 'expected_version' => 1],
        ],
        'reason' => 'Pembatalan massal karena salah input dinas',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Successfully voided 2 letter(s).',
            'data' => [
                'voided_count' => 2,
            ],
        ]);

    expect($l1->fresh()->status)->toBe('void');
    expect($l2->fresh()->status)->toBe('void');
    expect($l1->fresh()->version)->toBe(2);
    expect($l2->fresh()->version)->toBe(2);
});

test('batch void rolls back entirely on version mismatch', function () {
    Sanctum::actingAs($this->operator);

    $l1 = createDummyIncomingLetter('Surat Masuk 1', 1);
    $l2 = createDummyIncomingLetter('Surat Masuk 2', 4);

    $response = $this->postJson('/api/letters/incoming/batch-void', [
        'items' => [
            ['id' => $l1->id, 'expected_version' => 1],
            ['id' => $l2->id, 'expected_version' => 1], // outdated expected_version
        ],
        'reason' => 'Pembatalan surat massal',
    ]);

    $response->assertStatus(409);

    expect($l1->fresh()->status)->toBe('active');
    expect($l2->fresh()->status)->toBe('active');
});
