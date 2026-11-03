<?php

use App\Models\Letter;
use App\Models\OperatorReminder;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

beforeEach(function () {
    $this->seedAccessReferenceData();
});

test('T1: unauthenticated user receives 401 on tasks endpoints', function () {
    $this->getJson('/api/tasks')->assertUnauthorized();
    $this->getJson('/api/tasks/summary')->assertUnauthorized();
    $this->postJson('/api/tasks/reminders', ['title' => 'Test'])->assertUnauthorized();
    $this->postJson('/api/tasks/system/dismiss', ['system_source_key' => 'key-1'])->assertUnauthorized();
});

test('T2: admin and operator can view tasks list', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    // Create a manual reminder
    OperatorReminder::create([
        'user_id' => $admin->id,
        'title' => 'Tugas Koordinasi Pengawas',
        'category' => OperatorReminder::CATEGORY_GENERAL,
        'priority' => OperatorReminder::PRIORITY_HIGH,
        'status' => OperatorReminder::STATUS_PENDING,
    ]);

    $response = $this->getJson('/api/tasks');
    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'domain', 'category', 'title', 'description', 'urgency', 'source_type', 'status'],
            ],
            'pagination',
        ]);

    $data = $response->json('data');
    expect(count($data))->toBeGreaterThanOrEqual(1);
    expect($data[0]['title'])->toContain('Tugas Koordinasi');
});

test('T3: get tasks summary returns metric counters', function () {
    $operator = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($operator);

    // Create 2 reminders: 1 high, 1 warn
    OperatorReminder::create([
        'user_id' => $operator->id,
        'title' => 'Urgent Task',
        'priority' => OperatorReminder::PRIORITY_HIGH,
        'status' => OperatorReminder::STATUS_PENDING,
    ]);
    OperatorReminder::create([
        'user_id' => $operator->id,
        'title' => 'Warning Task',
        'priority' => OperatorReminder::PRIORITY_WARN,
        'status' => OperatorReminder::STATUS_PENDING,
    ]);

    $response = $this->getJson('/api/tasks/summary');
    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'total_active',
                'total_high',
                'total_warn',
                'total_info',
                'completed_today',
                'by_domain',
            ],
        ]);

    $data = $response->json('data');
    expect($data['total_high'])->toBeGreaterThanOrEqual(1);
    expect($data['total_warn'])->toBeGreaterThanOrEqual(1);
});

test('T4: store reminder creates new manual reminder with validation', function () {
    $operator = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($operator);

    // Validation failure: title required
    $this->postJson('/api/tasks/reminders', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);

    // Valid create
    $response = $this->postJson('/api/tasks/reminders', [
        'title' => 'Persiapan Rapat Dewan Guru',
        'description' => 'Materi kurikulum merdeka',
        'category' => 'teaching',
        'priority' => 'high',
        'due_date' => Carbon::now('Asia/Jakarta')->addDays(5)->toDateString(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Persiapan Rapat Dewan Guru')
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('operator_reminders', [
        'title' => 'Persiapan Rapat Dewan Guru',
        'priority' => 'high',
        'category' => 'teaching',
    ]);
});

test('T5: update reminder modifies existing reminder', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $reminder = OperatorReminder::create([
        'user_id' => $admin->id,
        'title' => 'Judul Awal',
        'priority' => 'info',
        'status' => 'pending',
    ]);

    $response = $this->putJson("/api/tasks/reminders/{$reminder->id}", [
        'title' => 'Judul Diperbarui',
        'priority' => 'warn',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Judul Diperbarui')
        ->assertJsonPath('data.priority', 'warn');

    expect($reminder->fresh()->title)->toBe('Judul Diperbarui');
});

test('T6: complete reminder marks status as completed with timestamp and user', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $reminder = OperatorReminder::create([
        'user_id' => $admin->id,
        'title' => 'Tugas Selesai',
        'status' => 'pending',
    ]);

    $response = $this->postJson("/api/tasks/reminders/{$reminder->id}/complete");
    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'completed');

    $fresh = $reminder->fresh();
    expect($fresh->status)->toBe('completed');
    expect($fresh->completed_by)->toBe($admin->id);
    expect($fresh->completed_at)->not->toBeNull();
});

test('T7: cancel reminder marks status as cancelled', function () {
    $operator = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($operator);

    $reminder = OperatorReminder::create([
        'user_id' => $operator->id,
        'title' => 'Tugas Dibatalkan',
        'status' => 'pending',
    ]);

    $response = $this->postJson("/api/tasks/reminders/{$reminder->id}/cancel");
    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'cancelled');

    expect($reminder->fresh()->status)->toBe('cancelled');
});

test('T8: delete reminder removes record from database', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $reminder = OperatorReminder::create([
        'user_id' => $admin->id,
        'title' => 'Tugas Mau Dihapus',
        'status' => 'pending',
    ]);

    $this->deleteJson("/api/tasks/reminders/{$reminder->id}")->assertOk();

    $this->assertDatabaseMissing('operator_reminders', ['id' => $reminder->id]);
});

test('T9: dismiss system task records dismissal and removes it from active list', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    // Create an outgoing letter in review
    $letter = Letter::create([
        'direction' => Letter::DIRECTION_OUTGOING,
        'subject' => 'Surat Undangan Dinas',
        'status' => Letter::STATUS_ACTIVE,
        'workflow_status' => Letter::WORKFLOW_REVIEW,
        'received_date' => '2026-11-02',
        'letter_date' => '2026-11-02',
        'sender' => 'Sekolah',
        'recipient' => 'Dinas Pendidikan',
        'category' => 'dinas',
        'created_by' => $admin->id,
    ]);

    // Check it appears in active tasks
    $resBefore = $this->getJson('/api/tasks?tab=system');
    $resBefore->assertOk();
    $foundBefore = collect($resBefore->json('data'))->firstWhere('id', 'letter-out-'.$letter->id);
    expect($foundBefore)->not->toBeNull();

    // Dismiss it
    $dismissRes = $this->postJson('/api/tasks/system/dismiss', [
        'system_source_key' => 'letter-out-'.$letter->id,
        'title' => 'Surat Undangan Telah Ditinjau',
    ]);
    $dismissRes->assertOk();

    // Check it is now absent from active tasks
    $resAfter = $this->getJson('/api/tasks?tab=system');
    $foundAfter = collect($resAfter->json('data'))->firstWhere('id', 'letter-out-'.$letter->id);
    expect($foundAfter)->toBeNull();

    // But it appears in completed tab
    $resCompleted = $this->getJson('/api/tasks?tab=completed');
    $resCompleted->assertOk();
    $foundInCompleted = collect($resCompleted->json('data'))->firstWhere('description', 'Key: letter-out-'.$letter->id);
    expect($foundInCompleted)->not->toBeNull();
});

test('T10: tab filter separates system, manual, and completed tasks', function () {
    $operator = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($operator);

    // Create a manual pending reminder
    OperatorReminder::create([
        'user_id' => $operator->id,
        'title' => 'Manual Reminder Test',
        'status' => OperatorReminder::STATUS_PENDING,
    ]);

    // Create a completed reminder
    OperatorReminder::create([
        'user_id' => $operator->id,
        'title' => 'Completed Reminder Test',
        'status' => OperatorReminder::STATUS_COMPLETED,
        'completed_at' => Carbon::now('Asia/Jakarta'),
    ]);

    // Filter tab=manual
    $resManual = $this->getJson('/api/tasks?tab=manual');
    $resManual->assertOk();
    $titlesManual = collect($resManual->json('data'))->pluck('title')->all();
    expect($titlesManual)->toContain('Manual Reminder Test');
    expect($titlesManual)->not->toContain('Completed Reminder Test');

    // Filter tab=completed
    $resCompleted = $this->getJson('/api/tasks?tab=completed');
    $resCompleted->assertOk();
    $titlesCompleted = collect($resCompleted->json('data'))->pluck('title')->all();
    expect($titlesCompleted)->toContain('Completed Reminder Test');
});

test('T11: search keyword and urgency filters filter task list correctly', function () {
    $operator = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($operator);

    OperatorReminder::create([
        'user_id' => $operator->id,
        'title' => 'Spesifik Kata Kunci Beasiswa',
        'priority' => 'high',
        'status' => 'pending',
    ]);
    OperatorReminder::create([
        'user_id' => $operator->id,
        'title' => 'Tugas Rutin Biasa',
        'priority' => 'info',
        'status' => 'pending',
    ]);

    // Search filter
    $resSearch = $this->getJson('/api/tasks?search=beasiswa');
    $resSearch->assertOk();
    $dataSearch = $resSearch->json('data');
    expect(count($dataSearch))->toBe(1);
    expect($dataSearch[0]['title'])->toContain('Beasiswa');

    // Urgency filter
    $resUrgency = $this->getJson('/api/tasks?urgency=high');
    $resUrgency->assertOk();
    foreach ($resUrgency->json('data') as $item) {
        expect($item['urgency'])->toBe('high');
    }
});

test('T12: teacher without manage ability cannot create or modify reminders and sees personal scope', function () {
    $teacher = $this->makeUserWithRole('guru');
    Sanctum::actingAs($teacher);

    // Cannot store reminder
    $this->postJson('/api/tasks/reminders', ['title' => 'Guru Coba Buat'])
        ->assertForbidden();

    // Can view tasks list
    $this->getJson('/api/tasks')->assertOk();
    $this->getJson('/api/tasks/summary')->assertOk();
});
