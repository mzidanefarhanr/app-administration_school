<?php

use App\Models\Activity;
use App\Models\Employee;
use App\Models\Letter;
use App\Models\LetterDocument;
use App\Models\LetterEvent;
use App\Models\LetterNumberCounter;
use App\Models\LetterSettings;
use App\Models\StudentMutation;
use App\Models\StudentRombel;
use Carbon\Carbon;
use Composer\InstalledVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;
use Tests\Support\CreatesLetterFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class, CreatesLetterFixtures::class);

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

    $this->seedLetterContext();
    $this->makeLetterSettings();
});

afterEach(function () {
    Carbon::setTestNow();
    LetterEvent::$testCreatingHook = null;
});

function p07cLongDutyDescription(): string
{
    $text = trim(str_repeat(
        'Menghadiri kegiatan peningkatan kompetensi tenaga pendidik dan tenaga kependidikan di lingkungan dinas pendidikan kabupaten. ',
        22,
    ));

    return rtrim(mb_substr($text, 0, 2000));
}

/**
 * Gabungkan isi stream PDF setelah dekompresi agar teks canvas dapat diperiksa.
 */
function p07cPdfStreams(string $bytes): string
{
    $text = '';
    $offset = 0;

    while (($start = strpos($bytes, 'stream', $offset)) !== false) {
        $start += 6;

        if (($bytes[$start] ?? '') === "\r") {
            $start++;
        }

        if (($bytes[$start] ?? '') === "\n") {
            $start++;
        }

        $end = strpos($bytes, 'endstream', $start);

        if ($end === false) {
            break;
        }

        $raw = substr($bytes, $start, $end - $start);
        $decoded = @gzuncompress($raw);

        if ($decoded === false) {
            $decoded = @gzdecode($raw);
        }

        $text .= is_string($decoded) ? $decoded : $raw;
        $offset = $end + 9;
    }

    return $text;
}

function p07cPageObjectCount(string $bytes): int
{
    return (int) preg_match_all('/\/Type\s*\/Page\b/', $bytes);
}

function p07cDocumentFiles(): array
{
    return Storage::disk('local')->allFiles('attachments/letter-documents');
}

// T1 — guest, peran, dan object-level akses dokumen.
it('enforces document preview and download permissions by role', function () {
    $draft = $this->createTypedViaApi('student_active');
    $issued = $this->issueTypedViaApi('student_active');

    $general = Letter::create([
        'direction' => Letter::DIRECTION_OUTGOING,
        'version' => 1,
        'received_date' => '2026-11-09',
        'letter_date' => '2026-11-08',
        'sender' => 'SMA Negeri 1 UAT',
        'recipient' => 'Dinas Pendidikan Kabupaten',
        'subject' => 'General issued letter',
        'category' => 'Notification',
        'status' => Letter::STATUS_ACTIVE,
        'workflow_status' => Letter::WORKFLOW_ISSUED,
        'created_by' => $this->operator->id,
    ]);

    auth()->forgetGuards();
    $this->get("/api/letters/outgoing/{$draft->id}/document/preview", ['Accept' => 'application/json'])
        ->assertStatus(401);
    $this->get("/api/letters/outgoing/{$issued->id}/document", ['Accept' => 'application/json'])
        ->assertStatus(401);

    Sanctum::actingAs($this->wakil);
    $this->get("/api/letters/outgoing/{$draft->id}/document/preview")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    $this->get("/api/letters/outgoing/{$issued->id}/document")->assertOk();
    $this->postJson('/api/letters/outgoing', $this->typedPayload('student_active'))->assertStatus(403);
    $this->get('/api/letters/settings/assets/signature')->assertStatus(403);
    $this->get("/api/letters/outgoing/{$general->id}/document/preview")->assertStatus(404);

    Sanctum::actingAs($this->principal);
    $this->get("/api/letters/outgoing/{$draft->id}/document/preview")->assertOk();
    $this->get("/api/letters/outgoing/{$issued->id}/document")->assertOk();
    $this->postJson('/api/letters/settings/header', [])->assertStatus(403);
    $this->get('/api/letters/settings/assets/signature')->assertOk();

    Sanctum::actingAs($this->operator);
    $this->get("/api/letters/outgoing/{$draft->id}/document/preview")->assertOk();
    $this->postJson("/api/letters/outgoing/{$issued->id}/issue", [
        'expected_version' => $issued->version,
    ])->assertStatus(403);
    $this->get('/api/letters/settings/assets/signature')->assertStatus(403);

    Sanctum::actingAs($this->admin);
    $this->get('/api/letters/settings/assets/signature')->assertStatus(403);
    $this->get("/api/letters/outgoing/{$draft->id}/document/preview")->assertOk();
    $this->get("/api/letters/outgoing/{$issued->id}/document/preview")->assertStatus(404);
    $this->get("/api/letters/outgoing/{$draft->id}/document")->assertStatus(404);
    $this->get('/api/letters/outgoing/999999/document')->assertStatus(404);
});

// T10 — create tiga jenis surat bertipe.
it('creates all typed letter kinds and keeps them unsigned drafts', function () {
    $types = ['student_active', 'student_mutation', 'employee_duty'];
    $created = [];

    foreach ($types as $type) {
        Sanctum::actingAs($this->operator);

        $data = $this->postJson('/api/letters/outgoing', $this->typedPayload($type))
            ->assertOk()
            ->json('data');

        expect($data['letter_type'])->toBe($type)
            ->and($data['letter_type_label'])->toBe(config('letters.letter_types.'.$type.'.label'))
            ->and($data['workflow_status'])->toBe(Letter::WORKFLOW_DRAFT)
            ->and($data['official_number'])->toBeNull()
            ->and($data['has_document'])->toBeFalse()
            ->and($data['version'])->toBe(1)
            ->and($data['document']['available'])->toBeFalse()
            ->and($data['document_subject']['type'])->toBe(config('letters.letter_types.'.$type.'.subject_type'));

        $created[$type] = $data;
    }

    expect(Letter::where('direction', Letter::DIRECTION_OUTGOING)->count())->toBe(3)
        ->and(LetterDocument::count())->toBe(0)
        ->and(LetterNumberCounter::count())->toBe(0)
        ->and(p07cDocumentFiles())->toBe([]);

    $active = Letter::findOrFail($created['student_active']['id']);
    $mutation = Letter::findOrFail($created['student_mutation']['id']);
    $duty = Letter::findOrFail($created['employee_duty']['id']);

    expect($active->document_subject_type)->toBe('student')
        ->and($active->document_subject_id)->toBe($this->student->id)
        ->and($active->document_fields)->toBe(['purpose' => 'Melanjutkan pendidikan ke jenjang berikutnya'])
        ->and($mutation->document_subject_type)->toBe('student_mutation')
        ->and($mutation->document_subject_id)->toBe($this->mutation->id)
        ->and($mutation->document_fields)->toBe([])
        ->and($duty->document_subject_type)->toBe('employee')
        ->and($duty->document_subject_id)->toBe($this->employee->id)
        ->and($duty->document_fields['location'])->toBe('Dinas Pendidikan Kabupaten')
        ->and(LetterEvent::where('letter_id', $active->id)->pluck('event')->all())->toBe(['created']);
});

// T11 — validasi create bertipe.
it('rejects invalid typed create payloads without writing rows', function () {
    Sanctum::actingAs($this->operator);

    $cases = [
        ['student_active', ['document_subject_id' => $this->student->id, 'document_fields' => null], 'document_fields.purpose'],
        ['student_active', ['document_subject_id' => $this->student->id, 'document_fields' => ['purpose' => 'abc']], 'document_fields.purpose'],
        ['student_active', ['document_subject_id' => $this->student->id, 'document_fields' => ['purpose' => 'Valid purpose text', 'extra' => 'x']], 'document_fields.extra'],
        ['student_active', ['document_subject_id' => $this->otherStudent->id], 'document_subject_id'],
        ['student_active', ['document_subject_id' => 999999], 'document_subject_id'],
        ['student_mutation', ['document_subject_id' => $this->cancelledMutation->id], 'document_subject_id'],
        ['student_mutation', ['document_subject_id' => $this->inboundMutation->id], 'document_subject_id'],
        ['student_mutation', ['document_subject_id' => $this->mutation->id, 'document_fields' => ['note' => 'x']], 'document_fields.note'],
        ['employee_duty', ['document_subject_id' => $this->exitedEmployee->id], 'document_subject_id'],
        ['employee_duty', ['document_subject_id' => 999999], 'document_subject_id'],
        [
            'employee_duty',
            [
                'document_subject_id' => $this->employee->id,
                'document_fields' => [
                    'duty_description' => 'Valid duty description',
                    'start_date' => '2026-11-14',
                    'end_date' => '2026-11-12',
                    'location' => 'Dinas Pendidikan',
                ],
            ],
            'document_fields.end_date',
        ],
    ];

    foreach ($cases as [$type, $overrides, $errorKey]) {
        $response = $this->postJson('/api/letters/outgoing', $this->typedPayload($type, $overrides))
            ->assertStatus(422);

        expect($response->json('errors'))->toHaveKey($errorKey);
    }

    $this->postJson('/api/letters/outgoing', $this->outgoingPayload([
        'letter_type' => 'unknown_type',
        'document_subject_id' => $this->student->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('letter_type');

    $this->postJson('/api/letters/outgoing', $this->typedPayload('student_active', [
        'document_subject_id' => $this->student->id,
        'document_fields' => [
            'purpose' => 'Valid purpose text',
            'duty_description' => 'x',
        ],
    ]))->assertStatus(422)->assertJsonValidationErrors('document_fields.duty_description');

    expect(Letter::where('direction', Letter::DIRECTION_OUTGOING)->count())->toBe(0)
        ->and(LetterDocument::count())->toBe(0)
        ->and(LetterNumberCounter::count())->toBe(0)
        ->and(p07cDocumentFiles())->toBe([]);
});

// T12 — tipe/subjek immutable dan field hanya dapat diedit saat draft.
it('locks typed identity and allows context edits only on drafts', function () {
    $letter = $this->createTypedViaApi('student_active');

    Sanctum::actingAs($this->operator);
    $updated = $this->putJson("/api/letters/outgoing/{$letter->id}", $this->typedPayload('student_active', [
        'expected_version' => $letter->version,
        'document_fields' => ['purpose' => 'Updated purpose for certificate'],
    ]))->assertOk();

    expect($updated->json('data.version'))->toBe(2)
        ->and($updated->json('data.document_fields.purpose'))->toBe('Updated purpose for certificate');

    $this->putJson("/api/letters/outgoing/{$letter->id}", $this->typedPayload('student_mutation', [
        'expected_version' => 2,
    ]))->assertStatus(422)->assertJsonValidationErrors('letter_type');

    $this->putJson("/api/letters/outgoing/{$letter->id}", $this->typedPayload('student_active', [
        'expected_version' => 2,
        'document_subject_id' => $this->student->id + 1000,
    ]))->assertStatus(422)->assertJsonValidationErrors('document_subject_id');

    $general = $this->postJson('/api/letters/outgoing', $this->outgoingPayload())
        ->assertOk()
        ->json('data');

    $this->putJson("/api/letters/outgoing/{$general['id']}", $this->outgoingPayload([
        'expected_version' => 1,
        'letter_type' => 'student_active',
        'document_subject_id' => $this->student->id,
        'document_fields' => ['purpose' => 'Valid purpose text'],
    ]))->assertStatus(422)->assertJsonValidationErrors('letter_type');

    $letter = $this->submitViaApi($letter->fresh());
    $this->putJson("/api/letters/outgoing/{$letter->id}", $this->typedPayload('student_active', [
        'expected_version' => $letter->version,
        'document_fields' => ['purpose' => 'Another purpose text'],
    ]))->assertStatus(409)->assertJsonValidationErrors('workflow_status');

    $letter = $this->approveViaApi($letter);
    Sanctum::actingAs($this->operator);
    $this->putJson("/api/letters/outgoing/{$letter->id}", $this->typedPayload('student_active', [
        'expected_version' => $letter->version,
        'document_fields' => ['purpose' => 'Yet another purpose'],
    ]))->assertStatus(409);

    $letter = $this->issueViaApi($letter);

    Sanctum::actingAs($this->operator);
    $this->putJson("/api/letters/outgoing/{$letter->id}", $this->typedPayload('student_active', [
        'expected_version' => $letter->version,
        'document_fields' => ['purpose' => 'Final purpose attempt'],
    ]))->assertStatus(409);

    expect($letter->fresh()->document_fields)->toBe(['purpose' => 'Updated purpose for certificate'])
        ->and($letter->fresh()->version)->toBe(5)
        ->and(LetterEvent::where('letter_id', $letter->id)->where('event', 'updated')->count())->toBe(1);
});

// T13 — submit typed memerlukan settings/subjek; general tetap dapat submit.
it('blocks typed submit without settings or eligible subject while general letters still submit', function () {
    $typed = $this->createTypedViaApi('student_active');

    LetterSettings::query()->delete();

    Sanctum::actingAs($this->operator);
    $response = $this->postJson("/api/letters/outgoing/{$typed->id}/submit", [
        'expected_version' => 1,
    ])->assertStatus(422);

    expect($response->json('errors'))->toHaveKey('letter_settings')
        ->and($typed->fresh()->workflow_status)->toBe(Letter::WORKFLOW_DRAFT)
        ->and($typed->fresh()->version)->toBe(1)
        ->and(LetterDocument::count())->toBe(0)
        ->and(LetterNumberCounter::count())->toBe(0)
        ->and(p07cDocumentFiles())->toBe([]);

    $general = $this->postJson('/api/letters/outgoing', $this->outgoingPayload())
        ->assertOk()
        ->json('data');

    $this->postJson("/api/letters/outgoing/{$general['id']}/submit", [
        'expected_version' => 1,
    ])->assertOk()->assertJsonPath('data.workflow_status', Letter::WORKFLOW_REVIEW);

    $this->makeLetterSettings();
    $typed = $this->createTypedViaApi('student_active');

    StudentRombel::whereKey($this->studentRombel->id)->update(['archived_at' => now()]);

    $this->postJson("/api/letters/outgoing/{$typed->id}/submit", [
        'expected_version' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('document_subject_id');

    expect($typed->fresh()->workflow_status)->toBe(Letter::WORKFLOW_DRAFT)
        ->and(LetterDocument::count())->toBe(0)
        ->and(LetterNumberCounter::count())->toBe(0);
});

// T14 — preview draft/review/approved watermarked tanpa penerbitan.
it('renders watermarked previews for draft review and approved typed letters', function () {
    config(['letters.document.font' => 'Helvetica']);

    $letters = [];

    foreach (['student_active', 'student_mutation', 'employee_duty'] as $type) {
        $letters[$type] = $this->createTypedViaApi($type);
    }

    Sanctum::actingAs($this->wakil);

    $preview = function (Letter $letter): string {
        $response = $this->get("/api/letters/outgoing/{$letter->id}/document/preview")->assertOk();

        $response->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Content-Security-Policy', 'sandbox')
            ->assertHeader('X-Document-Filename', 'preview-letter-'.$letter->id.'.pdf')
            ->assertHeader('Access-Control-Expose-Headers', 'X-Document-Filename');

        expect($response->headers->has('Content-Disposition'))->toBeFalse();

        $bytes = $response->getContent();
        $streams = p07cPdfStreams($bytes);

        expect($bytes)->toStartWith('%PDF-')
            ->and(substr_count($streams, 'PREVIEW'))->toBeGreaterThanOrEqual(1)
            ->and($streams)->toContain('Page 1 of');

        return $bytes;
    };

    foreach ($letters as $letter) {
        $preview($letter);
    }

    $active = $this->submitViaApi($letters['student_active']);
    Sanctum::actingAs($this->wakil);
    $preview($active);

    $active = $this->approveViaApi($active);
    Sanctum::actingAs($this->wakil);
    $preview($active);

    expect(LetterDocument::count())->toBe(0)
        ->and(LetterNumberCounter::count())->toBe(0)
        ->and(p07cDocumentFiles())->toBe([]);

    foreach (['student_mutation', 'employee_duty'] as $type) {
        $fresh = $letters[$type]->fresh();

        expect($fresh->workflow_status)->toBe(Letter::WORKFLOW_DRAFT)
            ->and($fresh->version)->toBe(1)
            ->and($fresh->official_number)->toBeNull();
    }

    expect($active->fresh()->workflow_status)->toBe(Letter::WORKFLOW_APPROVED)
        ->and($active->fresh()->version)->toBe(3)
        ->and($active->fresh()->official_number)->toBeNull()
        ->and(LetterEvent::where('letter_id', $active->id)->pluck('event')->sort()->values()->all())
        ->toBe(['approved', 'created', 'submitted']);
});

// T15a — preview dengan settings/subjek invalid.
it('returns validation errors for preview when settings or subject are invalid', function () {
    $letter = $this->createTypedViaApi('student_active');

    LetterSettings::query()->delete();

    Sanctum::actingAs($this->operator);
    $this->get("/api/letters/outgoing/{$letter->id}/document/preview", ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('letter_settings');

    expect($letter->fresh()->version)->toBe(1);

    $this->makeLetterSettings();
    StudentRombel::whereKey($this->studentRombel->id)->update(['archived_at' => now()]);

    $this->get("/api/letters/outgoing/{$letter->id}/document/preview", ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('document_subject_id');

    expect($letter->fresh()->version)->toBe(1)
        ->and(LetterDocument::count())->toBe(0)
        ->and(LetterNumberCounter::count())->toBe(0)
        ->and(p07cDocumentFiles())->toBe([]);
});

// T15b — preview untuk status terminal, general, dan legacy.
it('does not preview issued cancelled general or legacy letters', function () {
    $issued = $this->issueTypedViaApi('student_active');
    $cancelled = $this->issueTypedViaApi('student_active');

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$cancelled->id}/cancel", [
        'reason' => 'Synthetic cancellation reason for preview check',
        'expected_version' => $cancelled->version,
    ])->assertOk();

    $general = Letter::create([
        'direction' => Letter::DIRECTION_OUTGOING,
        'version' => 1,
        'received_date' => '2026-11-09',
        'letter_date' => '2026-11-08',
        'sender' => 'SMA Negeri 1 UAT',
        'recipient' => 'Dinas Pendidikan Kabupaten',
        'subject' => 'General draft letter',
        'category' => 'Notification',
        'status' => Letter::STATUS_ACTIVE,
        'workflow_status' => Letter::WORKFLOW_DRAFT,
        'created_by' => $this->operator->id,
    ]);

    $legacy = Letter::create([
        'direction' => Letter::DIRECTION_OUTGOING,
        'version' => 1,
        'received_date' => '2026-11-09',
        'letter_date' => '2026-11-08',
        'sender' => 'SMA Negeri 1 UAT',
        'recipient' => 'Dinas Pendidikan Kabupaten',
        'subject' => 'Legacy issued letter without PDF',
        'category' => 'Notification',
        'status' => Letter::STATUS_ACTIVE,
        'workflow_status' => Letter::WORKFLOW_ISSUED,
        'official_number' => 'L/2026/001',
        'created_by' => $this->operator->id,
    ]);

    Sanctum::actingAs($this->wakil);

    foreach ([$issued, $cancelled, $general, $legacy] as $letter) {
        $this->get("/api/letters/outgoing/{$letter->id}/document/preview")
            ->assertStatus(404);
    }

    expect($issued->fresh()->version)->toBe(4)
        ->and($cancelled->fresh()->version)->toBe(5)
        ->and(LetterDocument::count())->toBe(2)
        ->and(LetterNumberCounter::where('period', '2026')->value('last_number'))->toBe(2);
});

// T16 — issue typed menghasilkan dokumen, snapshot, hash, dan event.
it('issues typed letters with a stored immutable document and snapshot', function () {
    $letter = $this->issueTypedViaApi('student_active');
    $letter->refresh();

    expect($letter->workflow_status)->toBe(Letter::WORKFLOW_ISSUED)
        ->and($letter->official_number)->toBe('001/20512345/2026')
        ->and($letter->version)->toBe(4)
        ->and($letter->issued_by)->toBe($this->principal->id)
        ->and($letter->issued_at)->not->toBeNull();

    $document = LetterDocument::where('letter_id', $letter->id)->firstOrFail();
    $rendererVersion = InstalledVersions::getPrettyVersion('dompdf/dompdf');

    expect($document->template_code)->toBe('student_active')
        ->and($document->template_version)->toBe(1)
        ->and($document->letter_version)->toBe(4)
        ->and($document->official_number)->toBe('001/20512345/2026')
        ->and($document->page_count)->toBeGreaterThanOrEqual(1)
        ->and($document->renderer)->toBe('dompdf/'.$rendererVersion)
        ->and($document->generated_by)->toBe($this->principal->id);

    Storage::disk('local')->assertExists($document->pdf_path);

    $bytes = Storage::disk('local')->get($document->pdf_path);

    expect($bytes)->toStartWith('%PDF-')
        ->and(hash('sha256', $bytes))->toBe($document->pdf_sha256)
        ->and(strlen($bytes))->toBe($document->pdf_size_bytes)
        ->and(p07cPageObjectCount($bytes))->toBe($document->page_count);

    $snapshot = $document->snapshot;

    expect($snapshot['letter']['official_number'])->toBe('001/20512345/2026')
        ->and($snapshot['template'])->toBe(['code' => 'student_active', 'version' => 1])
        ->and($snapshot['school']['name'])->toBe('SMA Negeri 1 UAT')
        ->and($snapshot['school']['npsn'])->toBe('20512345')
        ->and($snapshot['signatory']['name'])->toBe('Synthetic Principal')
        ->and($snapshot['subject']['name'])->toBe('Synthetic Student Active')
        ->and($snapshot['subject']['nisn'])->toBe('0071000001')
        ->and($snapshot['fields']['purpose'])->toBe('Melanjutkan pendidikan ke jenjang berikutnya')
        ->and($snapshot['issued_by']['id'])->toBe($this->principal->id)
        ->and($snapshot['issued_at'])->toBe('2026-11-10T09:00:00+07:00');

    $snapshotJson = json_encode($snapshot);

    expect($snapshotJson)->not->toContain('"nik"')
        ->not->toContain('"address"')
        ->not->toContain('"phone"')
        ->not->toContain('"parent');

    $issuedEvent = LetterEvent::where('letter_id', $letter->id)->where('event', LetterEvent::EVENT_ISSUED)->firstOrFail();

    expect($issuedEvent->metadata)->toMatchArray([
        'official_number' => '001/20512345/2026',
        'period' => '2026',
        'sequence' => 1,
        'template_code' => 'student_active',
        'template_version' => 1,
        'page_count' => $document->page_count,
    ])->and(array_keys($issuedEvent->metadata))->toHaveCount(6);

    Sanctum::actingAs($this->wakil);
    $detail = $this->getJson("/api/letters/outgoing/{$letter->id}")->assertOk();
    $detailJson = json_encode($detail->json('data'));

    expect($detail->json('data.document.available'))->toBeTrue()
        ->and($detail->json('data.has_document'))->toBeTrue()
        ->and($detail->json('data.document_subject.type'))->toBe('student')
        ->and($detailJson)->not->toContain('pdf_path')
        ->not->toContain('pdf_sha256')
        ->not->toContain('"snapshot"')
        ->not->toContain('attachments/letter-documents');
});

// T17 — issue gagal settings/subjek/template/disk.
it('rolls back issue when settings subject template or storage fail', function () {
    $settingsCase = $this->approveViaApi($this->submitViaApi($this->createTypedViaApi('student_active')));
    LetterSettings::query()->delete();

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$settingsCase->id}/issue", [
        'expected_version' => $settingsCase->version,
    ])->assertStatus(422)->assertJsonValidationErrors('letter_settings');

    p07cAssertFailedIssueState($settingsCase);

    $this->makeLetterSettings();
    $subjectCase = $this->approveViaApi($this->submitViaApi($this->createTypedViaApi('student_active')));
    StudentRombel::whereKey($this->studentRombel->id)->update(['archived_at' => now()]);

    $this->postJson("/api/letters/outgoing/{$subjectCase->id}/issue", [
        'expected_version' => $subjectCase->version,
    ])->assertStatus(422)->assertJsonValidationErrors('document_subject_id');

    p07cAssertFailedIssueState($subjectCase);

    $renderCase = $this->approveViaApi($this->submitViaApi($this->createTypedViaApi('employee_duty')));
    config(['letters.templates.employee_duty.view' => 'letters.pdf.missing-template']);

    $this->postJson("/api/letters/outgoing/{$renderCase->id}/issue", [
        'expected_version' => $renderCase->version,
    ])->assertStatus(500);

    config(['letters.templates.employee_duty.view' => 'letters.pdf.employee-duty']);
    p07cAssertFailedIssueState($renderCase);

    $writeCase = $this->approveViaApi($this->submitViaApi($this->createTypedViaApi('employee_duty')));
    config(['letters.disk' => 'missing-disk']);

    $this->postJson("/api/letters/outgoing/{$writeCase->id}/issue", [
        'expected_version' => $writeCase->version,
    ])->assertStatus(500);

    config(['letters.disk' => 'local']);
    p07cAssertFailedIssueState($writeCase);

    expect(LetterNumberCounter::count())->toBe(0)
        ->and(p07cDocumentFiles())->toBe([]);
});

function p07cAssertFailedIssueState(Letter $letter): void
{
    $fresh = $letter->fresh();

    expect($fresh->workflow_status)->toBe(Letter::WORKFLOW_APPROVED)
        ->and($fresh->version)->toBe(3)
        ->and($fresh->official_number)->toBeNull()
        ->and($fresh->issued_at)->toBeNull()
        ->and(LetterDocument::where('letter_id', $letter->id)->count())->toBe(0)
        ->and(LetterEvent::where('letter_id', $letter->id)->where('event', LetterEvent::EVENT_ISSUED)->count())->toBe(0);
}

// T18 — kegagalan insert dokumen/event memaksa rollback penuh.
it('rolls back fully when document or issued event insertion fails', function () {
    $letter = $this->approveViaApi($this->submitViaApi($this->createTypedViaApi('student_active')));

    Event::listen('eloquent.creating: '.LetterDocument::class, function (): void {
        throw new RuntimeException('forced document insert failure');
    });

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$letter->id}/issue", [
        'expected_version' => $letter->version,
    ])->assertStatus(500);

    Event::forget('eloquent.creating: '.LetterDocument::class);
    p07cAssertFailedIssueState($letter);

    expect(LetterNumberCounter::count())->toBe(0)
        ->and(LetterDocument::count())->toBe(0)
        ->and(p07cDocumentFiles())->toBe([]);

    $second = $this->approveViaApi($this->submitViaApi($this->createTypedViaApi('student_active')));

    LetterEvent::$testCreatingHook = function (LetterEvent $event): void {
        if ($event->event === LetterEvent::EVENT_ISSUED) {
            throw new RuntimeException('forced issued event failure');
        }
    };

    $this->postJson("/api/letters/outgoing/{$second->id}/issue", [
        'expected_version' => $second->version,
    ])->assertStatus(500);

    LetterEvent::$testCreatingHook = null;
    p07cAssertFailedIssueState($second);

    expect(LetterNumberCounter::count())->toBe(0)
        ->and(LetterDocument::count())->toBe(0)
        ->and(LetterEvent::where('letter_id', $second->id)->where('event', LetterEvent::EVENT_ISSUED)->count())->toBe(0)
        ->and(p07cDocumentFiles())->toBe([]);
});

// T19 — snapshot dan PDF tidak berubah setelah master/settings berubah.
it('keeps issued snapshots immutable after settings and masters change', function () {
    $first = $this->issueTypedViaApi('student_active');
    $document = LetterDocument::where('letter_id', $first->id)->firstOrFail();
    $path = $document->pdf_path;
    $originalBytes = Storage::disk('local')->get($path);
    $originalSnapshot = $document->snapshot;
    $originalSha = $document->pdf_sha256;

    $settings = LetterSettings::firstOrFail();
    $settings->update([
        'school_name' => 'SMA Negeri 1 UAT Changed',
        'address_line' => 'Changed Address',
        'signatory_name' => 'Changed Principal',
    ]);

    $this->student->update(['name' => 'Changed Student Name']);
    $this->rombel->update(['name' => '10-CHANGED']);

    Sanctum::actingAs($this->wakil);
    $download = $this->get("/api/letters/outgoing/{$first->id}/document")->assertOk();

    expect($download->getContent())->toBe($originalBytes)
        ->and(hash('sha256', $download->getContent()))->toBe($originalSha);

    $document->refresh();

    expect($document->snapshot)->toBe($originalSnapshot)
        ->and($document->pdf_sha256)->toBe($originalSha)
        ->and(Storage::disk('local')->get($path))->toBe($originalBytes)
        ->and(LetterDocument::where('letter_id', $first->id)->count())->toBe(1);

    $second = $this->issueTypedViaApi('student_active');
    $secondDocument = LetterDocument::where('letter_id', $second->id)->firstOrFail();

    expect($secondDocument->snapshot['school']['name'])->toBe('SMA Negeri 1 UAT Changed')
        ->and($secondDocument->snapshot['school']['address_line'])->toBe('Changed Address')
        ->and($secondDocument->snapshot['signatory']['name'])->toBe('Changed Principal')
        ->and($secondDocument->snapshot['subject']['name'])->toBe('Changed Student Name')
        ->and($secondDocument->snapshot['subject']['class'])->toBe('10-CHANGED');

    $document->refresh();

    expect($document->snapshot)->toBe($originalSnapshot)
        ->and(Storage::disk('local')->get($path))->toBe($originalBytes);
});

// T20 — subjek tidak eligible lagi saat issue.
it('revalidates subject eligibility at issue time', function () {
    $studentLetter = $this->approveViaApi($this->submitViaApi($this->createTypedViaApi('student_active')));
    $employeeLetter = $this->approveViaApi($this->submitViaApi($this->createTypedViaApi('employee_duty')));
    $mutationLetter = $this->approveViaApi($this->submitViaApi($this->createTypedViaApi('student_mutation')));

    StudentRombel::whereKey($this->studentRombel->id)->update(['archived_at' => now()]);
    Employee::whereKey($this->employee->id)->update(['exit_date' => '2026-01-01']);
    StudentMutation::whereKey($this->mutation->id)->update(['status' => StudentMutation::STATUS_CANCELLED]);

    Sanctum::actingAs($this->principal);

    foreach ([$studentLetter, $employeeLetter, $mutationLetter] as $letter) {
        $this->postJson("/api/letters/outgoing/{$letter->id}/issue", [
            'expected_version' => $letter->version,
        ])->assertStatus(422)->assertJsonValidationErrors('document_subject_id');

        $fresh = $letter->fresh();

        expect($fresh->workflow_status)->toBe(Letter::WORKFLOW_APPROVED)
            ->and($fresh->version)->toBe(3)
            ->and($fresh->official_number)->toBeNull();
    }

    expect(LetterDocument::count())->toBe(0)
        ->and(LetterNumberCounter::count())->toBe(0)
        ->and(p07cDocumentFiles())->toBe([]);
});

// T21 — surat tugas multi-halaman.
it('issues multi-page duty letters with an accurate page count', function () {
    config(['letters.document.font' => 'Helvetica']);

    $description = p07cLongDutyDescription();
    $letter = $this->createTypedViaApi('employee_duty', [
        'document_fields' => [
            'duty_description' => $description,
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-14',
            'location' => 'Dinas Pendidikan Kabupaten',
        ],
    ]);

    $letter = $this->submitViaApi($letter);
    $letter = $this->approveViaApi($letter);

    Sanctum::actingAs($this->wakil);
    $preview = $this->get("/api/letters/outgoing/{$letter->id}/document/preview")->assertOk();
    $previewStreams = p07cPdfStreams($preview->getContent());

    preg_match_all('/Page \d+ of (\d+)/', $previewStreams, $previewPages);
    $previewPageCount = (int) end($previewPages[1]);

    expect($previewPageCount)->toBeGreaterThanOrEqual(2)
        ->and(substr_count($previewStreams, 'PREVIEW'))->toBeGreaterThanOrEqual($previewPageCount)
        ->and(LetterDocument::count())->toBe(0)
        ->and(LetterNumberCounter::count())->toBe(0);

    $letter = $this->issueViaApi($letter);
    $document = LetterDocument::where('letter_id', $letter->id)->firstOrFail();

    expect($document->page_count)->toBeGreaterThanOrEqual(2)
        ->and($document->snapshot['fields']['duty_description'])->toBe($description);

    $bytes = Storage::disk('local')->get($document->pdf_path);
    $streams = p07cPdfStreams($bytes);

    expect(p07cPageObjectCount($bytes))->toBe($document->page_count)
        ->and(strlen($bytes))->toBeGreaterThan(15000)
        ->and($streams)->toContain('Page 1 of '.$document->page_count)
        ->and($streams)->toContain('Page '.$document->page_count.' of '.$document->page_count)
        ->and($streams)->toContain('001/20512345/2026');
});

// T22 — font dan gambar tertanam tanpa remote resource.
it('embeds fonts and images without remote resources', function () {
    $letter = $this->issueTypedViaApi('student_active');
    $document = LetterDocument::where('letter_id', $letter->id)->firstOrFail();
    $bytes = Storage::disk('local')->get($document->pdf_path);
    $streams = p07cPdfStreams($bytes);

    expect(substr_count($bytes, '/FontFile2'))->toBeGreaterThanOrEqual(1)
        ->and($bytes)->toContain('DejaVuSans')
        ->and(substr_count($bytes, '/Subtype /Image'))->toBeGreaterThanOrEqual(2)
        ->and($bytes)->not->toContain('http://')
        ->and($bytes)->not->toContain('https://')
        ->and($bytes)->not->toContain('data:image')
        ->and($streams)->not->toContain('http://')
        ->and($streams)->not->toContain('https://');
});

// T23 — download issued dan cancelled-after-issue.
it('downloads issued and cancelled documents with secure headers and stable bytes', function () {
    $letter = $this->issueTypedViaApi('student_active');
    $document = LetterDocument::where('letter_id', $letter->id)->firstOrFail();
    $stored = Storage::disk('local')->get($document->pdf_path);

    Sanctum::actingAs($this->wakil);
    $response = $this->get("/api/letters/outgoing/{$letter->id}/document")->assertOk();

    $response->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Content-Security-Policy', 'sandbox')
        ->assertHeader('X-Document-Filename', '001-20512345-2026.pdf')
        ->assertHeader('Access-Control-Expose-Headers', 'X-Document-Filename');

    expect($response->headers->has('Content-Disposition'))->toBeFalse()
        ->and($response->getContent())->toBe($stored)
        ->and((int) $response->headers->get('Content-Length'))->toBe(strlen($stored))
        ->and(hash('sha256', $response->getContent()))->toBe($document->pdf_sha256);

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$letter->id}/cancel", [
        'reason' => 'Synthetic cancellation reason for issued letter',
        'expected_version' => $letter->version,
    ])->assertOk()
        ->assertJsonPath('data.workflow_status', Letter::WORKFLOW_CANCELLED)
        ->assertJsonPath('data.official_number', '001/20512345/2026');

    expect($letter->fresh()->version)->toBe(5)
        ->and(LetterDocument::where('letter_id', $letter->id)->count())->toBe(1);

    Sanctum::actingAs($this->wakil);
    $cancelledDownload = $this->get("/api/letters/outgoing/{$letter->id}/document")->assertOk();

    expect($cancelledDownload->getContent())->toBe($stored)
        ->and($cancelledDownload->headers->get('X-Document-Filename'))
        ->toBe('001-20512345-2026.pdf')
        ->and($cancelledDownload->headers->has('Content-Disposition'))->toBeFalse();
});

// T24 — download tersedia hanya untuk issued/cancelled dengan file.
it('returns 404 for unavailable documents without regenerating', function () {
    $draft = $this->createTypedViaApi('student_active');
    $review = $this->submitViaApi($this->createTypedViaApi('student_active'));
    $approved = $this->approveViaApi($this->submitViaApi($this->createTypedViaApi('student_active')));
    $issued = $this->issueTypedViaApi('student_active');
    $cancelledBeforeIssue = $this->approveViaApi($this->submitViaApi($this->createTypedViaApi('student_active')));

    Sanctum::actingAs($this->principal);
    $this->postJson("/api/letters/outgoing/{$cancelledBeforeIssue->id}/cancel", [
        'reason' => 'Synthetic cancellation before issue',
        'expected_version' => $cancelledBeforeIssue->version,
    ])->assertOk();

    $general = Letter::create([
        'direction' => Letter::DIRECTION_OUTGOING,
        'version' => 1,
        'received_date' => '2026-11-09',
        'letter_date' => '2026-11-08',
        'sender' => 'SMA Negeri 1 UAT',
        'recipient' => 'Dinas Pendidikan Kabupaten',
        'subject' => 'General draft letter',
        'category' => 'Notification',
        'status' => Letter::STATUS_ACTIVE,
        'workflow_status' => Letter::WORKFLOW_DRAFT,
        'created_by' => $this->operator->id,
    ]);

    $legacy = Letter::create([
        'direction' => Letter::DIRECTION_OUTGOING,
        'version' => 1,
        'received_date' => '2026-11-09',
        'letter_date' => '2026-11-08',
        'sender' => 'SMA Negeri 1 UAT',
        'recipient' => 'Dinas Pendidikan Kabupaten',
        'subject' => 'Legacy issued letter without PDF',
        'category' => 'Notification',
        'status' => Letter::STATUS_ACTIVE,
        'workflow_status' => Letter::WORKFLOW_ISSUED,
        'official_number' => 'L/2026/001',
        'created_by' => $this->operator->id,
    ]);

    $document = LetterDocument::where('letter_id', $issued->id)->firstOrFail();
    Storage::disk('local')->delete($document->pdf_path);

    $documentCount = LetterDocument::count();
    $fileCount = count(p07cDocumentFiles());

    Sanctum::actingAs($this->wakil);

    foreach ([$draft, $review, $approved, $cancelledBeforeIssue, $general, $legacy, $issued] as $letter) {
        $this->get("/api/letters/outgoing/{$letter->id}/document")->assertStatus(404);
    }

    expect(LetterDocument::count())->toBe($documentCount)
        ->and(count(p07cDocumentFiles()))->toBe($fileCount)
        ->and($draft->fresh()->version)->toBe(1)
        ->and($review->fresh()->version)->toBe(2)
        ->and($approved->fresh()->version)->toBe(3)
        ->and($issued->fresh()->version)->toBe(4)
        ->and($cancelledBeforeIssue->fresh()->version)->toBe(4);
});

// T25 — serialisasi dan filter.
it('serializes typed metadata and filters without leaking storage details', function () {
    Sanctum::actingAs($this->operator);

    $general = $this->postJson('/api/letters/outgoing', $this->outgoingPayload())
        ->assertOk()
        ->json('data');
    $draft = $this->createTypedViaApi('student_mutation');
    $issued = $this->issueTypedViaApi('student_active');

    $options = $this->getJson('/api/letters/outgoing/options')->assertOk()->json('data');

    expect($options['letter_types'])->toHaveCount(3)
        ->and(collect($options['letter_types'])->pluck('value')->all())
        ->toBe(['student_active', 'student_mutation', 'employee_duty'])
        ->and($options['settings_ready'])->toBeTrue()
        ->and($options['settings_missing'])->toBe([])
        ->and($options['document']['preview_statuses'])->toBe(['draft', 'review', 'approved'])
        ->and($options['document']['download_statuses'])->toBe(['issued', 'cancelled'])
        ->and($options['document']['watermark'])->toBe('PREVIEW — NOT ISSUED')
        ->and($options['typed_fields'])->toHaveKeys([
            'purpose_min_length',
            'purpose_max_length',
            'duty_description_min_length',
            'duty_description_max_length',
            'location_min_length',
            'location_max_length',
        ])
        ->and($options['subject_picker'])->toHaveKeys([
            'search_max_length',
            'per_page_options',
            'default_per_page',
            'max_per_page',
        ]);

    $list = $this->getJson('/api/letters/outgoing')->assertOk();
    $items = collect($list->json('data.items'))->keyBy('id');

    expect($list->json('data.meta.total'))->toBe(3);

    $generalItem = $items->get($general['id']);
    $issuedItem = $items->get($issued->id);

    expect($generalItem['letter_type'])->toBeNull()
        ->and($generalItem['letter_type_label'])->toBe('General Letter')
        ->and($generalItem['has_document'])->toBeFalse()
        ->and($issuedItem['letter_type'])->toBe('student_active')
        ->and($issuedItem['letter_type_label'])->toBe('Student Active Certificate')
        ->and($issuedItem['has_document'])->toBeTrue();

    $detail = $this->getJson("/api/letters/outgoing/{$issued->id}")->assertOk()->json('data');
    $detailJson = json_encode($detail);

    expect($detail['document']['available'])->toBeTrue()
        ->and($detail['document']['template_code'])->toBe('student_active')
        ->and($detail['document']['template_version'])->toBe(1)
        ->and($detail['document']['page_count'])->toBeGreaterThanOrEqual(1)
        ->and($detail['document']['size_bytes'])->toBeGreaterThan(0)
        ->and($detail['document']['generated_at'])->not->toBeNull()
        ->and($detail['document_subject']['type'])->toBe('student')
        ->and($detail['document_fields']['purpose'])->toBe('Melanjutkan pendidikan ke jenjang berikutnya')
        ->and($detailJson)->not->toContain('pdf_path')
        ->not->toContain('pdf_sha256')
        ->not->toContain('"snapshot"')
        ->not->toContain('attachments/letter-');

    $draftDetail = $this->getJson("/api/letters/outgoing/{$draft->id}")->assertOk()->json('data');

    expect($draftDetail['document']['available'])->toBeFalse()
        ->and($draftDetail['document_subject']['type'])->toBe('student_mutation')
        ->and($draftDetail['document_fields'])->toBe([])
        ->and($draftDetail['letter_type_label'])->toBe('Student Mutation Certificate');

    $generalDetail = $this->getJson("/api/letters/outgoing/{$general['id']}")->assertOk()->json('data');

    expect($generalDetail['document']['available'])->toBeFalse()
        ->and($generalDetail['document_subject'])->toBeNull()
        ->and($generalDetail['document_fields'])->toBeNull()
        ->and($generalDetail['letter_type_label'])->toBe('General Letter');

    expect($this->getJson('/api/letters/outgoing?letter_type=general')->json('data.meta.total'))->toBe(1)
        ->and($this->getJson('/api/letters/outgoing?letter_type=student_active')->json('data.meta.total'))->toBe(1)
        ->and($this->getJson('/api/letters/outgoing?letter_type=student_mutation')->json('data.meta.total'))->toBe(1)
        ->and($this->getJson('/api/letters/outgoing?letter_type=all')->json('data.meta.total'))->toBe(3)
        ->and($this->getJson('/api/letters/outgoing?has_document=1')->json('data.meta.total'))->toBe(1)
        ->and($this->getJson('/api/letters/outgoing?has_document=0')->json('data.meta.total'))->toBe(2)
        ->and($this->getJson('/api/letters/outgoing?letter_type=student_active&has_document=1')->json('data.meta.total'))->toBe(1);

    $this->getJson('/api/letters/outgoing?letter_type=unknown')->assertStatus(422);
    $this->getJson('/api/letters/outgoing?has_document=2')->assertStatus(422);
});

// T26 — privacy audit/history dan immutability.
it('keeps audit and history free of document PII and immutable records', function () {
    $purpose = 'Audit privacy purpose for student certificate';
    $letter = $this->issueTypedViaApi('student_active', [
        'document_fields' => ['purpose' => $purpose],
    ]);

    $document = LetterDocument::where('letter_id', $letter->id)->firstOrFail();
    $activities = Activity::where('table_name', 'letters')->where('record_id', $letter->id)->get();
    $audit = $activities->toJson();

    expect($activities)->not->toBeEmpty()
        ->and($audit)->not->toContain($purpose)
        ->not->toContain($this->student->nisn)
        ->not->toContain($this->student->nik)
        ->not->toContain('Synthetic Principal')
        ->not->toContain('198001012006041001')
        ->not->toContain('attachments/letter-documents')
        ->not->toContain('pdf_sha256')
        ->not->toContain('snapshot')
        ->not->toContain('document_fields');

    $events = LetterEvent::where('letter_id', $letter->id)->get();
    $eventJson = $events->toJson();

    expect($eventJson)->not->toContain($purpose)
        ->not->toContain($this->student->nisn)
        ->not->toContain('attachments/')
        ->not->toContain('pdf_sha256')
        ->not->toContain('snapshot');

    $issuedEvent = $events->firstWhere('event', LetterEvent::EVENT_ISSUED);

    expect($issuedEvent)->not->toBeNull()
        ->and(array_keys($issuedEvent->metadata))->toHaveCount(6)
        ->and($issuedEvent->metadata)->toMatchArray([
            'official_number' => '001/20512345/2026',
            'period' => '2026',
            'sequence' => 1,
            'template_code' => 'student_active',
            'template_version' => 1,
        ]);

    expect(fn () => $document->update(['page_count' => 99]))
        ->toThrow(RuntimeException::class);
    expect(fn () => $document->delete())
        ->toThrow(RuntimeException::class);

    $createdEvent = $events->firstWhere('event', LetterEvent::EVENT_CREATED);

    expect(fn () => $createdEvent->update(['metadata' => ['x' => 1]]))
        ->toThrow(RuntimeException::class);

    expect($document->fresh()->page_count)->toBeGreaterThanOrEqual(1);

    Sanctum::actingAs($this->principal);
    $this->postJson('/api/letters/settings/signatory', [
        'signatory_name' => 'Changed Signatory Name',
        'signatory_title' => 'Kepala Sekolah',
        'signatory_identifier_label' => 'NIP',
        'signatory_identifier' => '199001012010011002',
        'expected_version' => 1,
    ])->assertOk();

    $settingsActivity = Activity::where('table_name', 'letter_settings')->latest('id')->first();
    $settingsAudit = $settingsActivity?->toJson();

    expect($settingsActivity)->not->toBeNull()
        ->and($settingsAudit)->not->toContain('Changed Signatory Name')
        ->not->toContain('199001012010011002')
        ->not->toContain('address_line')
        ->not->toContain('signature_path')
        ->not->toContain('logo_path');
});

// T27 — file privat dan tidak ada URL publik.
it('keeps letter assets and documents on the private disk only', function () {
    $letter = $this->issueTypedViaApi('student_active');

    $files = Storage::disk('local')->allFiles();

    expect($files)->not->toBeEmpty();

    foreach ($files as $path) {
        expect($path)->toStartWith('attachments/')
            ->and($path)->toMatch('/^attachments\/(letter-settings|letter-documents)\//');
    }

    expect(Storage::disk('public')->allFiles())->toBe([]);

    $document = LetterDocument::where('letter_id', $letter->id)->firstOrFail();
    $settings = LetterSettings::firstOrFail();

    expect($document->pdf_path)->toStartWith('attachments/letter-documents/'.$letter->id.'/')
        ->and($document->pdf_path)->toEndWith('.pdf')
        ->and($settings->signature_path)->toStartWith('attachments/letter-settings/')
        ->and($settings->logo_path)->toStartWith('attachments/letter-settings/')
        ->and($settings->stamp_path)->toStartWith('attachments/letter-settings/');

    $publicRoutes = collect(Route::getRoutes())
        ->map(fn ($route) => $route->uri())
        ->filter(fn (string $uri) => str_starts_with($uri, 'storage/') || str_starts_with($uri, 'attachments/'));

    expect($publicRoutes)->toBeEmpty();

    auth()->forgetGuards();
    $this->get("/api/letters/outgoing/{$letter->id}/document", ['Accept' => 'application/json'])
        ->assertStatus(401);
    $this->get("/api/letters/outgoing/{$letter->id}/document/preview", ['Accept' => 'application/json'])
        ->assertStatus(401);

    Sanctum::actingAs($this->wakil);
    $detailJson = json_encode($this->getJson("/api/letters/outgoing/{$letter->id}")->assertOk()->json('data'));

    expect($detailJson)->not->toContain('attachments/')
        ->not->toContain('pdf_path')
        ->not->toContain('pdf_sha256')
        ->not->toContain('"snapshot"');
});

// T28 — inventaris route dan regresi alur umum.
it('exposes all letter routes with letter abilities and keeps regressions green', function () {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/letters'));

    expect($routes)->toHaveCount(37);

    foreach ($routes as $route) {
        $hasLetterAbility = collect($route->gatherMiddleware())->contains(
            fn ($entry) => is_string($entry) && str_starts_with($entry, 'can:letters.'),
        );

        expect($hasLetterAbility)->toBeTrue();
    }

    $uris = $routes->map(fn ($route) => $route->uri())->all();

    expect($uris)
        ->toContain('api/letters/settings/assets/{asset}')
        ->toContain('api/letters/outgoing/{letter}/document/preview')
        ->toContain('api/letters/outgoing/{letter}/document')
        ->toContain('api/letters/outgoing/{letter}/issue')
        ->toContain('api/letters/incoming')
        ->toContain('api/letters/dispositions/options');

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/letters/incoming')->assertOk();
    $this->getJson('/api/letters/dispositions/options')->assertOk();
    $this->getJson('/api/letters/outgoing/options')->assertOk();

    Sanctum::actingAs($this->operator);
    $general = $this->postJson('/api/letters/outgoing', $this->outgoingPayload())
        ->assertOk()
        ->json('data');

    $general = $this->submitViaApi(Letter::findOrFail($general['id']));
    $general = $this->approveViaApi($general);
    $general = $this->issueViaApi($general);

    expect($general->workflow_status)->toBe(Letter::WORKFLOW_ISSUED)
        ->and($general->official_number)->toBe('001/20512345/2026')
        ->and($general->letter_type)->toBeNull()
        ->and(LetterDocument::count())->toBe(0)
        ->and(p07cDocumentFiles())->toBe([]);

    $this->get("/api/letters/outgoing/{$general->id}/document")
        ->assertStatus(404);
});
