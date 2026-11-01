<?php

use App\Models\Activity;
use App\Models\District;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\Employment;
use App\Models\Gender;
use App\Models\Letter;
use App\Models\LetterDisposition;
use App\Models\LetterEvent;
use App\Models\MaritalStatus;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Religion;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 10, 9, 0, 0, 'Asia/Jakarta'));

    $this->seedAccessReferenceData();

    $this->admin = $this->makeUserWithRole('admin-sistem');
    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->wakil = $this->makeUserWithRole('wakil-kesiswaan');

    $province = Province::firstOrCreate(['name' => 'DKI Jakarta']);
    $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Utara']);
    $district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Koja']);
    $gender = Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $religion = Religion::firstOrCreate(['name' => 'Islam']);
    $maritalStatus = MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);
    $status = EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap Yayasan'], ['abbreviation' => 'PTY']);
    $teacherType = EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $staffType = EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
    $employment = Employment::firstOrCreate(['name' => 'Tetap Yayasan'], ['description' => 'Tetap']);
    $subject = Subject::firstOrCreate(['name' => 'Matematika'], ['description' => 'Matematika']);

    $this->employeeBase = [
        'employee_status_id' => $status->id,
        'employment_id' => $employment->id,
        'subject_id' => $subject->id,
        'religion_id' => $religion->id,
        'district_id' => $district->id,
        'marital_status_id' => $maritalStatus->id,
        'gender_id' => $gender->id,
        'tmt_employee' => '2020-01-01',
    ];

    $this->teacher = p07bDispoEmployee($this->employeeBase, 'Active Teacher', [
        'employee_type_id' => $teacherType->id,
    ]);
    $this->staff = p07bDispoEmployee($this->employeeBase, 'Active Staff', [
        'employee_type_id' => $staffType->id,
    ]);
    $this->exited = p07bDispoEmployee($this->employeeBase, 'Exited Teacher', [
        'employee_type_id' => $teacherType->id,
        'exit_date' => '2026-10-31',
    ]);
    $this->archived = p07bDispoEmployee($this->employeeBase, 'Archived Teacher', [
        'employee_type_id' => $teacherType->id,
    ]);
    $this->archived->delete();
});

afterEach(function () {
    Carbon::setTestNow();
    LetterEvent::$testCreatingHook = null;
});

/**
 * @param  array<string, mixed>  $base
 */
function p07bDispoEmployee(array $base, string $name, array $overrides = []): Employee
{
    return Employee::create(array_merge($base, ['name' => $name], $overrides));
}

function p07bDispoLetter(array $overrides = []): Letter
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

function p07bDisposition(Letter $letter, int $employeeId, int $assignerId, array $overrides = []): LetterDisposition
{
    return LetterDisposition::create(array_merge([
        'letter_id' => $letter->id,
        'recipient_employee_id' => $employeeId,
        'assigned_by' => $assignerId,
        'due_date' => '2026-11-15',
        'status' => LetterDisposition::STATUS_PENDING,
        'note' => 'Please review this letter.',
        'version' => 1,
    ], $overrides));
}

function p07bAssign($test, int $letterId, array $recipients, array $overrides = [])
{
    return $test->postJson("/api/letters/incoming/{$letterId}/dispositions", array_merge([
        'recipients' => $recipients,
        'due_date' => '2026-11-15',
        'note' => 'Please review this letter.',
    ], $overrides));
}

// T19 — create disposisi multi-penerima.
it('creates one disposition per recipient with events and open counts', function () {
    $letter = p07bDispoLetter(['created_by' => $this->operator->id]);

    Sanctum::actingAs($this->operator);

    $response = p07bAssign($this, $letter->id, [$this->teacher->id, $this->staff->id], [
        'note' => 'Handle this before the due date.',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $letter->id)
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.dispositions_open_count', 2)
        ->assertJsonPath('data.dispositions_overdue_count', 0)
        ->assertJsonCount(2, 'data.dispositions')
        ->assertJsonPath('data.events.0.event', 'disposition_created');

    $dispositions = collect($response->json('data.dispositions'));

    expect($dispositions->pluck('recipient.id')->all())->toBe([$this->teacher->id, $this->staff->id])
        ->and($dispositions->pluck('status')->unique()->all())->toBe([LetterDisposition::STATUS_PENDING])
        ->and($dispositions->pluck('version')->unique()->all())->toBe([1])
        ->and($dispositions->pluck('due_date')->unique()->all())->toBe(['2026-11-15'])
        ->and($dispositions->pluck('note')->unique()->all())->toBe(['Handle this before the due date.'])
        ->and($dispositions->pluck('assigned_by.id')->unique()->all())->toBe([$this->operator->id])
        ->and($dispositions->pluck('overdue')->unique()->all())->toBe([false]);

    expect(LetterDisposition::count())->toBe(2);
    expect($letter->fresh()->version)->toBe(1);

    $events = LetterEvent::where('letter_id', $letter->id)
        ->where('event', 'disposition_created')
        ->get();

    expect($events)->toHaveCount(2)
        ->and($events->map(fn (LetterEvent $event) => $event->metadata['recipient_employee_id'])->sort()->values()->all())
        ->toBe(collect([$this->teacher->id, $this->staff->id])->sort()->values()->all())
        ->and($events->map(fn (LetterEvent $event) => array_keys($event->metadata))->unique()->values()->all())
        ->toBe([['disposition_id', 'recipient_employee_id', 'due_date']]);
});

// T19 tambahan — opsi dan direktori penerima.
it('lists only active employees as disposition recipients', function () {
    Sanctum::actingAs($this->operator);

    $this->getJson('/api/letters/dispositions/options')->assertOk()
        ->assertJsonPath('data.statuses.0.value', 'pending')
        ->assertJsonPath('data.default_per_page', 20);

    $this->getJson('/api/letters/dispositions/recipients')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2)
        ->assertJsonPath('data.items.0.name', 'Active Staff')
        ->assertJsonPath('data.items.1.name', 'Active Teacher')
        ->assertJsonPath('data.items.0.employee_type', 'Tenaga Kependidikan');

    $this->getJson('/api/letters/dispositions/recipients?search=Teacher')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.name', 'Active Teacher');

    $this->getJson('/api/letters/dispositions/recipients?search=Missing')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 0);

    $this->getJson('/api/letters/dispositions/recipients?per_page=7')->assertStatus(422);

    Sanctum::actingAs($this->principal);
    $this->getJson('/api/letters/dispositions/recipients')->assertStatus(403);

    Sanctum::actingAs($this->wakil);
    $this->getJson('/api/letters/dispositions/recipients')->assertStatus(403);
    $this->getJson('/api/letters/dispositions')->assertOk();

    auth()->forgetGuards();
    $this->getJson('/api/letters/dispositions/options')->assertStatus(401);
    $this->getJson('/api/letters/dispositions/recipients')->assertStatus(401);
    $this->getJson('/api/letters/dispositions')->assertStatus(401);
});

// T20 — duplikasi penerima aktif, penerima invalid, surat void.
it('rejects duplicate, inactive, and missing recipients', function () {
    $letter = p07bDispoLetter(['created_by' => $this->operator->id]);

    Sanctum::actingAs($this->operator);

    p07bAssign($this, $letter->id, [$this->teacher->id])->assertOk();

    p07bAssign($this, $letter->id, [$this->teacher->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('recipients');

    p07bAssign($this, $letter->id, [$this->staff->id, $this->staff->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('recipients.1');

    p07bAssign($this, $letter->id, [999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('recipients.0');

    p07bAssign($this, $letter->id, [$this->exited->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('recipients');

    p07bAssign($this, $letter->id, [$this->archived->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('recipients');

    p07bAssign($this, $letter->id, [$this->teacher->id], ['due_date' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors('due_date');

    p07bAssign($this, $letter->id, [$this->teacher->id], ['due_date' => '15-11-2026'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('due_date');

    expect(LetterDisposition::where('letter_id', $letter->id)->count())->toBe(1);

    $voided = p07bDispoLetter([
        'created_by' => $this->operator->id,
        'status' => Letter::STATUS_VOID,
        'voided_at' => Carbon::now(),
        'voided_by' => $this->operator->id,
        'void_reason' => 'No longer needed for processing.',
    ]);

    p07bAssign($this, $voided->id, [$this->teacher->id])
        ->assertStatus(409)
        ->assertJsonPath('errors.status.0', 'This letter is void and cannot receive dispositions.')
        ->assertJsonPath('current_versions.status', 'void');

    expect(LetterDisposition::where('letter_id', $voided->id)->count())->toBe(0)
        ->and(LetterDisposition::where('letter_id', $letter->id)->count())->toBe(1);
});

// T20 tambahan — penerima boleh di-assign lagi setelah disposisi lama selesai.
it('allows reassigning a recipient after the previous disposition closes', function () {
    $letter = p07bDispoLetter(['created_by' => $this->operator->id]);

    Sanctum::actingAs($this->operator);

    $created = p07bAssign($this, $letter->id, [$this->teacher->id])->assertOk();
    $dispositionId = (int) $created->json('data.dispositions.0.id');

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$dispositionId}", [
        'status' => 'in_progress',
        'expected_version' => 1,
    ])->assertOk();

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$dispositionId}", [
        'status' => 'completed',
        'follow_up_note' => 'The first assignment has been completed.',
        'expected_version' => 2,
    ])->assertOk();

    p07bAssign($this, $letter->id, [$this->teacher->id])
        ->assertOk()
        ->assertJsonPath('data.dispositions_open_count', 1);

    expect(LetterDisposition::where('letter_id', $letter->id)->count())->toBe(2);
});

// T21 — update disposisi pending→in_progress→completed.
it('moves dispositions through in-progress and completed with follow-up', function () {
    $letter = p07bDispoLetter(['created_by' => $this->operator->id]);

    Sanctum::actingAs($this->operator);

    $created = p07bAssign($this, $letter->id, [$this->teacher->id], ['due_date' => '2026-11-09']);
    $dispositionId = (int) $created->json('data.dispositions.0.id');

    expect($created->json('data.dispositions.0.overdue'))->toBeTrue()
        ->and($created->json('data.dispositions_overdue_count'))->toBe(1);

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$dispositionId}", [
        'status' => 'in_progress',
        'expected_version' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('data.dispositions.0.status', 'in_progress')
        ->assertJsonPath('data.dispositions.0.version', 2)
        ->assertJsonPath('data.dispositions.0.overdue', true)
        ->assertJsonPath('data.dispositions_open_count', 1);

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$dispositionId}", [
        'status' => 'completed',
        'expected_version' => 2,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('follow_up_note');

    expect(LetterDisposition::findOrFail($dispositionId)->status)->toBe(LetterDisposition::STATUS_IN_PROGRESS);

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$dispositionId}", [
        'status' => 'completed',
        'follow_up_note' => 'Followed up with the principal.',
        'expected_version' => 2,
    ])
        ->assertOk()
        ->assertJsonPath('data.dispositions.0.status', 'completed')
        ->assertJsonPath('data.dispositions.0.version', 3)
        ->assertJsonPath('data.dispositions.0.follow_up_note', 'Followed up with the principal.')
        ->assertJsonPath('data.dispositions.0.completed_by.id', $this->operator->id)
        ->assertJsonPath('data.dispositions.0.overdue', false)
        ->assertJsonPath('data.dispositions_open_count', 0)
        ->assertJsonPath('data.dispositions_overdue_count', 0)
        ->assertJsonPath('data.events.0.event', 'disposition_completed');

    $disposition = LetterDisposition::findOrFail($dispositionId);

    expect($disposition->completed_at)->not->toBeNull()
        ->and($disposition->cancel_reason)->toBeNull();

    $updated = LetterEvent::where('letter_id', $letter->id)
        ->where('event', 'disposition_updated')
        ->firstOrFail();

    expect($updated->metadata['changed_fields'])->toContain('status');

    $completed = LetterEvent::where('letter_id', $letter->id)
        ->where('event', 'disposition_completed')
        ->firstOrFail();

    expect(array_keys($completed->metadata))->toBe(['disposition_id']);
});

// T21 tambahan — edit catatan dan due date memperbarui overdue.
it('updates open disposition details and recomputes overdue', function () {
    $letter = p07bDispoLetter(['created_by' => $this->operator->id]);

    Sanctum::actingAs($this->operator);

    $created = p07bAssign($this, $letter->id, [$this->teacher->id], ['due_date' => '2026-11-20']);
    $dispositionId = (int) $created->json('data.dispositions.0.id');

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$dispositionId}", [
        'due_date' => '2026-11-09',
        'note' => 'Revised instruction for follow-up.',
        'expected_version' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('data.dispositions.0.due_date', '2026-11-09')
        ->assertJsonPath('data.dispositions.0.note', 'Revised instruction for follow-up.')
        ->assertJsonPath('data.dispositions.0.overdue', true)
        ->assertJsonPath('data.dispositions.0.version', 2)
        ->assertJsonPath('data.dispositions_overdue_count', 1);

    $event = LetterEvent::where('letter_id', $letter->id)
        ->where('event', 'disposition_updated')
        ->firstOrFail();

    expect($event->metadata['changed_fields'])->toContain('due_date')->toContain('note');
});

// T22 — transisi tidak sah, versi basi, dan terminal read-only.
it('rejects invalid transitions and stale disposition versions', function () {
    $letter = p07bDispoLetter(['created_by' => $this->operator->id]);
    $disposition = p07bDisposition($letter, $this->teacher->id, $this->operator->id);

    Sanctum::actingAs($this->operator);

    $base = "/api/letters/incoming/{$letter->id}/dispositions/{$disposition->id}";

    $this->putJson($base, [
        'status' => 'completed',
        'follow_up_note' => 'Completed out of order.',
        'expected_version' => 1,
    ])
        ->assertStatus(409)
        ->assertJsonPath('current_versions.disposition_version', 1)
        ->assertJsonPath('current_versions.status', 'pending');

    $this->putJson($base, [
        'status' => 'in_progress',
        'expected_version' => 99,
    ])
        ->assertStatus(409)
        ->assertJsonPath('current_versions.disposition_version', 1);

    $this->putJson($base, [
        'status' => 'archived',
        'expected_version' => 1,
    ])->assertStatus(422);

    expect($disposition->fresh()->status)->toBe(LetterDisposition::STATUS_PENDING)
        ->and($disposition->fresh()->version)->toBe(1)
        ->and(LetterEvent::where('letter_id', $letter->id)->count())->toBe(0);

    $this->putJson($base, [
        'status' => 'in_progress',
        'expected_version' => 1,
    ])->assertOk();

    $this->putJson($base, [
        'status' => 'completed',
        'follow_up_note' => 'A stale completion attempt.',
        'expected_version' => 1,
    ])->assertStatus(409);

    expect($disposition->fresh()->status)->toBe(LetterDisposition::STATUS_IN_PROGRESS)
        ->and($disposition->fresh()->version)->toBe(2);
});

// T22 tambahan — cancel dan terminal read-only.
it('cancels open dispositions and keeps terminal rows read-only', function () {
    $letter = p07bDispoLetter(['created_by' => $this->operator->id]);
    $pending = p07bDisposition($letter, $this->teacher->id, $this->operator->id);

    Sanctum::actingAs($this->operator);

    $this->postJson("/api/letters/incoming/{$letter->id}/dispositions/{$pending->id}/cancel", [
        'reason' => 'short',
        'expected_version' => 1,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    $this->postJson("/api/letters/incoming/{$letter->id}/dispositions/{$pending->id}/cancel", [
        'reason' => 'Recipient is no longer available.',
        'expected_version' => 99,
    ])->assertStatus(409);

    $this->postJson("/api/letters/incoming/{$letter->id}/dispositions/{$pending->id}/cancel", [
        'reason' => 'Recipient is no longer available.',
        'expected_version' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('data.dispositions.0.status', 'cancelled')
        ->assertJsonPath('data.dispositions.0.version', 2)
        ->assertJsonPath('data.dispositions.0.cancel_reason', 'Recipient is no longer available.')
        ->assertJsonPath('data.dispositions.0.cancelled_by.id', $this->operator->id)
        ->assertJsonPath('data.dispositions_open_count', 0)
        ->assertJsonPath('data.events.0.event', 'disposition_cancelled');

    expect($pending->fresh()->cancelled_at)->not->toBeNull();

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$pending->id}", [
        'note' => 'Attempting to edit cancelled row.',
        'expected_version' => 2,
    ])->assertStatus(409);

    $this->postJson("/api/letters/incoming/{$letter->id}/dispositions/{$pending->id}/cancel", [
        'reason' => 'A repeated cancellation attempt.',
        'expected_version' => 2,
    ])->assertStatus(409);

    $completed = p07bDisposition($letter, $this->staff->id, $this->operator->id, [
        'status' => LetterDisposition::STATUS_COMPLETED,
        'follow_up_note' => 'Completed before this test.',
        'completed_at' => Carbon::now(),
        'completed_by' => $this->operator->id,
    ]);

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$completed->id}", [
        'status' => 'in_progress',
        'expected_version' => 1,
    ])->assertStatus(409);

    $this->postJson("/api/letters/incoming/{$letter->id}/dispositions/{$completed->id}/cancel", [
        'reason' => 'Cancelling a completed disposition.',
        'expected_version' => 1,
    ])->assertStatus(409);

    expect(fn () => $completed->delete())->toThrow(DomainException::class);
});

// T23 — void ditolak saat disposisi terbuka.
it('blocks void until all dispositions close and then keeps the history', function () {
    $letter = p07bDispoLetter(['created_by' => $this->operator->id]);
    $pending = p07bDisposition($letter, $this->teacher->id, $this->operator->id);
    $inProgress = p07bDisposition($letter, $this->staff->id, $this->operator->id, [
        'status' => LetterDisposition::STATUS_IN_PROGRESS,
        'due_date' => '2026-11-09',
    ]);

    Sanctum::actingAs($this->operator);

    $this->postJson("/api/letters/incoming/{$letter->id}/void", [
        'reason' => 'This letter must be voided.',
        'expected_version' => 1,
    ])
        ->assertStatus(409)
        ->assertJsonPath('errors.open_dispositions.0', 'This letter cannot be voided while dispositions are still open.')
        ->assertJsonPath('current_versions.open_dispositions', 2)
        ->assertJsonPath('current_versions.letter_version', 1);

    expect($letter->fresh()->status)->toBe(Letter::STATUS_ACTIVE)
        ->and(LetterDisposition::where('letter_id', $letter->id)->count())->toBe(2)
        ->and(LetterEvent::where('letter_id', $letter->id)->where('event', 'voided')->count())->toBe(0);

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$pending->id}", [
        'status' => 'in_progress',
        'expected_version' => 1,
    ])->assertOk();

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$pending->id}", [
        'status' => 'completed',
        'follow_up_note' => 'The follow-up is complete.',
        'expected_version' => 2,
    ])->assertOk();

    $this->postJson("/api/letters/incoming/{$letter->id}/dispositions/{$inProgress->id}/cancel", [
        'reason' => 'The second recipient no longer needs the letter.',
        'expected_version' => 1,
    ])->assertOk();

    $this->postJson("/api/letters/incoming/{$letter->id}/void", [
        'reason' => 'This letter must be voided.',
        'expected_version' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'void')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.dispositions_open_count', 0)
        ->assertJsonPath('data.events.0.event', 'voided');

    expect(LetterDisposition::where('letter_id', $letter->id)->count())->toBe(2)
        ->and(LetterDisposition::where('letter_id', $letter->id)->where('status', 'completed')->count())->toBe(1)
        ->and(LetterDisposition::where('letter_id', $letter->id)->where('status', 'cancelled')->count())->toBe(1);

    p07bAssign($this, $letter->id, [$this->teacher->id])
        ->assertStatus(409)
        ->assertJsonPath('errors.status.0', 'This letter is void and cannot receive dispositions.');

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$pending->id}", [
        'note' => 'Editing after void must fail.',
        'expected_version' => 3,
    ])
        ->assertStatus(409)
        ->assertJsonPath('errors.status.0', 'This letter is void; dispositions are read-only.');

    expect(LetterDisposition::findOrFail($pending->id)->note)->toBe('Please review this letter.');
});

// T24 — list/filter disposisi.
it('filters and paginates dispositions with escaped wildcard searches', function () {
    $alpha = p07bDispoLetter([
        'created_by' => $this->operator->id,
        'subject' => 'Alpha coordination',
        'source_number' => '001/AAA/2026',
        'sender' => 'Dinas Alpha',
        'received_date' => '2026-11-01',
    ]);
    $beta = p07bDispoLetter([
        'created_by' => $this->operator->id,
        'subject' => 'Beta budget',
        'source_number' => '002/BBB/2026',
        'sender' => 'Biro Beta',
        'received_date' => '2026-11-02',
    ]);
    $gamma = p07bDispoLetter([
        'created_by' => $this->operator->id,
        'subject' => 'Gamma event',
        'source_number' => null,
        'sender' => 'Yayasan Gamma',
        'received_date' => '2026-11-03',
    ]);

    p07bDisposition($alpha, $this->teacher->id, $this->operator->id, ['due_date' => '2026-11-09']);
    p07bDisposition($alpha, $this->staff->id, $this->operator->id, [
        'due_date' => '2026-11-20',
        'status' => LetterDisposition::STATUS_IN_PROGRESS,
    ]);
    p07bDisposition($beta, $this->teacher->id, $this->operator->id, [
        'due_date' => '2026-11-05',
        'status' => LetterDisposition::STATUS_COMPLETED,
        'follow_up_note' => 'Completed already.',
        'completed_at' => Carbon::now(),
        'completed_by' => $this->operator->id,
    ]);
    p07bDisposition($gamma, $this->staff->id, $this->operator->id, [
        'due_date' => '2026-11-01',
        'status' => LetterDisposition::STATUS_CANCELLED,
        'cancel_reason' => 'Cancelled already.',
        'cancelled_at' => Carbon::now(),
        'cancelled_by' => $this->operator->id,
    ]);

    Sanctum::actingAs($this->wakil);

    $this->getJson('/api/letters/dispositions')->assertOk()
        ->assertJsonPath('data.meta.total', 2)
        ->assertJsonPath('data.filters.status', 'open')
        ->assertJsonPath('data.items.0.letter.subject', 'Alpha coordination')
        ->assertJsonPath('data.items.0.overdue', true)
        ->assertJsonPath('data.items.1.overdue', false);

    $this->getJson('/api/letters/dispositions?status=completed')->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.letter.subject', 'Beta budget');

    $this->getJson('/api/letters/dispositions?status=all')->assertOk()
        ->assertJsonPath('data.meta.total', 4);

    $this->getJson('/api/letters/dispositions?overdue=true')->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.letter.subject', 'Alpha coordination');

    $this->getJson("/api/letters/dispositions?status=all&recipient_employee_id={$this->staff->id}")->assertOk()
        ->assertJsonPath('data.meta.total', 2)
        ->assertJsonPath('data.filters.recipient_employee_id', $this->staff->id);

    $this->getJson('/api/letters/dispositions?status=all&search=Beta')->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.letter.subject', 'Beta budget');

    $this->getJson('/api/letters/dispositions?search=001/AAA')->assertOk()
        ->assertJsonPath('data.meta.total', 2);

    $this->getJson('/api/letters/dispositions?search=Dinas%20Alpha')->assertOk()
        ->assertJsonPath('data.meta.total', 2);

    $this->getJson('/api/letters/dispositions?status=all&search=Active%20Teacher')->assertOk()
        ->assertJsonPath('data.meta.total', 2);

    $this->getJson('/api/letters/dispositions?search=%25')->assertOk()
        ->assertJsonPath('data.meta.total', 0);

    $this->getJson('/api/letters/dispositions?search=_')->assertOk()
        ->assertJsonPath('data.meta.total', 0);

    $this->getJson('/api/letters/dispositions?per_page=10&page=1')->assertOk()
        ->assertJsonPath('data.meta.per_page', 10)
        ->assertJsonPath('data.meta.total', 2);

    $this->getJson('/api/letters/dispositions?per_page=7')->assertStatus(422);
    $this->getJson('/api/letters/dispositions?status=archived')->assertStatus(422);
    $this->getJson('/api/letters/dispositions?overdue=maybe')->assertStatus(422);
    $this->getJson('/api/letters/dispositions?recipient_employee_id=abc')->assertStatus(422);
});

// T24 tambahan — incoming list menampilkan hitungan disposisi.
it('includes disposition counts in the incoming letter list', function () {
    $letter = p07bDispoLetter(['created_by' => $this->operator->id]);
    $other = p07bDispoLetter([
        'created_by' => $this->operator->id,
        'subject' => 'Letter without dispositions',
    ]);

    p07bDisposition($letter, $this->teacher->id, $this->operator->id, ['due_date' => '2026-11-09']);
    p07bDisposition($letter, $this->staff->id, $this->operator->id, ['due_date' => '2026-11-20']);

    Sanctum::actingAs($this->wakil);

    $this->getJson('/api/letters/incoming?search=Undangan')->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.id', $letter->id)
        ->assertJsonPath('data.items.0.dispositions_open_count', 2)
        ->assertJsonPath('data.items.0.dispositions_overdue_count', 1);

    $this->getJson('/api/letters/incoming?search=without')->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.id', $other->id)
        ->assertJsonPath('data.items.0.dispositions_open_count', 0)
        ->assertJsonPath('data.items.0.dispositions_overdue_count', 0);
});

// T25 — audit/history privacy.
it('records disposition history without notes or personal details', function () {
    $letter = p07bDispoLetter([
        'created_by' => $this->operator->id,
        'subject' => 'Sensitive subject',
        'sender' => 'Sensitive sender',
    ]);

    Sanctum::actingAs($this->operator);

    $created = p07bAssign($this, $letter->id, [$this->teacher->id, $this->staff->id], [
        'note' => 'Sensitive disposition note.',
    ]);
    $dispositions = collect($created->json('data.dispositions'));
    $first = $dispositions->firstWhere('recipient.id', $this->teacher->id);
    $second = $dispositions->firstWhere('recipient.id', $this->staff->id);

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$first['id']}", [
        'status' => 'in_progress',
        'expected_version' => 1,
    ])->assertOk();

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$first['id']}", [
        'status' => 'completed',
        'follow_up_note' => 'Sensitive follow-up detail.',
        'expected_version' => 2,
    ])->assertOk();

    $this->postJson("/api/letters/incoming/{$letter->id}/dispositions/{$second['id']}/cancel", [
        'reason' => 'Sensitive cancellation reason.',
        'expected_version' => 1,
    ])->assertOk();

    $activities = Activity::whereIn('table_name', ['letters', 'letter_dispositions'])->get();
    $auditPayload = $activities
        ->map(fn (Activity $activity) => json_encode([
            $activity->before,
            $activity->after,
            $activity->new,
            $activity->delete,
        ]))
        ->implode(' ');

    expect($activities->where('table_name', 'letter_dispositions')->count())->toBeGreaterThan(0)
        ->and($auditPayload)->not->toContain('Sensitive subject')
        ->and($auditPayload)->not->toContain('Sensitive sender')
        ->and($auditPayload)->not->toContain('Sensitive disposition note')
        ->and($auditPayload)->not->toContain('Sensitive follow-up detail')
        ->and($auditPayload)->not->toContain('Sensitive cancellation reason')
        ->and($auditPayload)->not->toContain('Active Teacher')
        ->and($auditPayload)->not->toContain('Active Staff');

    expect(Activity::where('table_name', 'letter_events')->count())->toBe(0);

    $eventPayload = json_encode(LetterEvent::all()->map->only(['metadata']));
    expect($eventPayload)->not->toContain('Sensitive subject')
        ->and($eventPayload)->not->toContain('Sensitive sender')
        ->and($eventPayload)->not->toContain('Sensitive disposition note')
        ->and($eventPayload)->not->toContain('Sensitive follow-up detail')
        ->and($eventPayload)->not->toContain('Sensitive cancellation reason')
        ->and($eventPayload)->not->toContain('Active Teacher')
        ->and($eventPayload)->not->toContain('Active Staff');

    $completedEvent = LetterEvent::where('letter_id', $letter->id)
        ->where('event', 'disposition_completed')
        ->firstOrFail();

    expect(array_keys($completedEvent->metadata))->toBe(['disposition_id'])
        ->and(LetterDisposition::findOrFail($first['id'])->follow_up_note)->toBe('Sensitive follow-up detail.');

    $event = LetterEvent::where('letter_id', $letter->id)->firstOrFail();
    expect(fn () => $event->update(['metadata' => []]))->toThrow(RuntimeException::class);
    expect(fn () => $event->delete())->toThrow(RuntimeException::class);
});

// T26 — rollback dan kompensasi.
it('rolls back disposition changes when events fail', function () {
    $letter = p07bDispoLetter(['created_by' => $this->operator->id]);

    Sanctum::actingAs($this->operator);
    $this->withoutExceptionHandling();

    LetterEvent::$testCreatingHook = function (LetterEvent $event) {
        if ($event->event === 'disposition_created') {
            throw new RuntimeException('Forced disposition event failure.');
        }
    };

    try {
        expect(fn () => p07bAssign($this, $letter->id, [$this->teacher->id, $this->staff->id]))
            ->toThrow(RuntimeException::class);
    } finally {
        LetterEvent::$testCreatingHook = null;
    }

    expect(LetterDisposition::where('letter_id', $letter->id)->count())->toBe(0)
        ->and(LetterEvent::where('letter_id', $letter->id)->count())->toBe(0);

    $disposition = p07bDisposition($letter, $this->teacher->id, $this->operator->id);

    $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$disposition->id}", [
        'status' => 'in_progress',
        'expected_version' => 1,
    ])->assertOk();

    LetterEvent::$testCreatingHook = function (LetterEvent $event) {
        if ($event->event === 'disposition_completed') {
            throw new RuntimeException('Forced completion event failure.');
        }
    };

    try {
        expect(fn () => $this->putJson("/api/letters/incoming/{$letter->id}/dispositions/{$disposition->id}", [
            'status' => 'completed',
            'follow_up_note' => 'This completion must roll back.',
            'expected_version' => 2,
        ]))->toThrow(RuntimeException::class);
    } finally {
        LetterEvent::$testCreatingHook = null;
    }

    $disposition->refresh();

    expect($disposition->status)->toBe(LetterDisposition::STATUS_IN_PROGRESS)
        ->and($disposition->version)->toBe(2)
        ->and($disposition->follow_up_note)->toBeNull()
        ->and($disposition->completed_at)->toBeNull()
        ->and(LetterEvent::where('letter_id', $letter->id)->where('event', 'disposition_completed')->count())->toBe(0);

    LetterEvent::$testCreatingHook = function (LetterEvent $event) {
        if ($event->event === 'disposition_cancelled') {
            throw new RuntimeException('Forced cancellation event failure.');
        }
    };

    try {
        expect(fn () => $this->postJson("/api/letters/incoming/{$letter->id}/dispositions/{$disposition->id}/cancel", [
            'reason' => 'This cancellation must roll back.',
            'expected_version' => 2,
        ]))->toThrow(RuntimeException::class);
    } finally {
        LetterEvent::$testCreatingHook = null;
    }

    $disposition->refresh();

    expect($disposition->status)->toBe(LetterDisposition::STATUS_IN_PROGRESS)
        ->and($disposition->version)->toBe(2)
        ->and($disposition->cancel_reason)->toBeNull()
        ->and($disposition->cancelled_at)->toBeNull()
        ->and(LetterEvent::where('letter_id', $letter->id)->where('event', 'disposition_cancelled')->count())->toBe(0);
});

// Route inventory.
it('exposes disposition routes with the expected letter abilities', function () {
    $nested = collect(Route::getRoutes())->filter(
        fn ($route) => str_starts_with($route->uri(), 'api/letters/incoming/')
            && str_contains($route->uri(), 'dispositions')
    );

    $topLevel = collect(Route::getRoutes())->filter(
        fn ($route) => str_starts_with($route->uri(), 'api/letters/dispositions')
    );

    expect($nested)->toHaveCount(3)
        ->and($topLevel)->toHaveCount(3);

    foreach ($nested as $route) {
        $middleware = $route->gatherMiddleware();

        expect(collect($middleware)->contains(
            fn ($entry) => is_string($entry) && str_starts_with($entry, 'can:letters.manage'),
        ))->toBeTrue();
    }

    $options = $topLevel->firstWhere('uri', 'api/letters/dispositions/options');
    $recipients = $topLevel->firstWhere('uri', 'api/letters/dispositions/recipients');
    $index = $topLevel->firstWhere('uri', 'api/letters/dispositions');

    expect(collect($options->gatherMiddleware())->contains(
        fn ($entry) => is_string($entry) && str_starts_with($entry, 'can:letters.view'),
    ))->toBeTrue()
        ->and(collect($index->gatherMiddleware())->contains(
            fn ($entry) => is_string($entry) && str_starts_with($entry, 'can:letters.view'),
        ))->toBeTrue()
        ->and(collect($recipients->gatherMiddleware())->contains(
            fn ($entry) => is_string($entry) && str_starts_with($entry, 'can:letters.manage'),
        ))->toBeTrue()
        ->and(collect($recipients->gatherMiddleware())->contains(
            fn ($entry) => is_string($entry) && str_starts_with($entry, 'can:employees.view'),
        ))->toBeTrue();
});
