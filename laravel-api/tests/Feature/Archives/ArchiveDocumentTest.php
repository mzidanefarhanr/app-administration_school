<?php

use App\Models\Ability;
use App\Models\Activity;
use App\Models\ArchiveDocument;
use App\Models\ArchiveFileVersion;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Letter;
use App\Models\Role;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
    $this->teacher = $this->makeUserWithRole('guru');

    $this->student = Student::factory()->create([
        'name' => 'Synthetic Archive Student',
        'nis' => '0910001',
        'nisn' => '0091000001',
    ]);

    $this->secondStudent = Student::factory()->create([
        'name' => 'Synthetic Archive Student Two',
        'nis' => '0910002',
        'nisn' => '0091000002',
    ]);

    $this->employee = Employee::factory()->create([
        'name' => 'Synthetic Archive Employee',
        'user_id' => null,
        'exit_date' => null,
    ]);

    $this->letter = Letter::create([
        'direction' => Letter::DIRECTION_INCOMING,
        'version' => 1,
        'received_date' => '2026-11-05',
        'letter_date' => '2026-11-03',
        'source_number' => '005/123/DISDIK/2026',
        'sender' => 'Dinas Pendidikan Kabupaten',
        'subject' => 'Synthetic Archive Letter Subject',
        'category' => 'Invitation',
        'notes' => null,
        'status' => Letter::STATUS_ACTIVE,
        'created_by' => $this->operator->id,
    ]);

    $this->leave = LeaveRequest::create([
        'subject_type' => LeaveRequest::SUBJECT_STUDENT,
        'student_id' => $this->student->id,
        'type' => 'sick',
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-12',
        'reason' => 'Synthetic archive leave reason.',
        'status' => LeaveRequest::STATUS_SUBMITTED,
        'version' => 1,
        'requested_by' => $this->operator->id,
        'submitted_at' => '2026-11-09 08:00:00',
        'submitted_by' => $this->operator->id,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function p09Pdf(string $name = 'document.pdf', ?string $content = null): UploadedFile
{
    $content = $content ?? "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    return UploadedFile::fake()->createWithContent($name, $content);
}

function p09RealUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'p09-archive-');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

function p09Image(string $name, string $format): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'p09-image-');
    $image = imagecreatetruecolor(4, 4);
    $color = imagecolorallocate($image, 10, 120, 200);
    imagefilledrectangle($image, 0, 0, 3, 3, $color);

    if ($format === 'png') {
        imagepng($image, $path);
    } else {
        imagejpeg($image, $path);
    }

    imagedestroy($image);

    return new UploadedFile($path, $name, null, null, true);
}

/**
 * @return array<string, mixed>
 */
function p09Payload(array $overrides = []): array
{
    return array_merge([
        'category' => 'school_document',
        'title' => 'Synthetic Archive Document',
        'document_number' => 'ARS/001/2026',
        'document_date' => '2026-11-05',
        'description' => 'Synthetic archive description.',
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function p09Create(\Tests\TestCase $test, array $overrides = [], ?UploadedFile $file = null, $actor = null): array
{
    Sanctum::actingAs($actor ?? $test->operator);

    $payload = p09Payload($overrides);
    $payload['file'] = $file ?? p09Pdf();

    $response = $test->post('/api/archives', $payload, ['Accept' => 'application/json']);
    $response->assertOk();

    return $response->json('data');
}

/**
 * @return array<string, mixed>
 */
function p09AddVersion(\Tests\TestCase $test, int $documentId, int $expectedVersion, ?UploadedFile $file = null, ?string $note = null, $actor = null): array
{
    Sanctum::actingAs($actor ?? $test->operator);

    $payload = [
        'file' => $file ?? p09Pdf(),
        'expected_version' => $expectedVersion,
    ];

    if ($note !== null) {
        $payload['note'] = $note;
    }

    $response = $test->post("/api/archives/{$documentId}/versions", $payload, ['Accept' => 'application/json']);
    $response->assertOk();

    return $response->json('data');
}

/**
 * @return array<int, int>
 */
function p09ListIds(\Tests\TestCase $test, string $query = ''): array
{
    $url = '/api/archives'.($query === '' ? '' : '?'.$query);
    $response = $test->getJson($url);
    $response->assertOk();

    return array_map(fn (array $item) => $item['id'], $response->json('data.items'));
}

function p09MakeRole(string $slug, array $abilities): Role
{
    $role = Role::create([
        'name' => ucwords(str_replace('-', ' ', $slug)),
        'slug' => $slug,
        'description' => 'Synthetic archive test role.',
        'is_protected' => false,
    ]);

    $role->abilities()->sync(
        Ability::whereIn('name', $abilities)->pluck('id')->all()
    );

    return $role;
}

// T1 — create general document, first version, hidden file internals.
it('creates a general archive document with its first immutable version', function () {
    $content = "%PDF-1.4\nSynthetic archive body one\n%%EOF\n";

    Sanctum::actingAs($this->operator);

    $response = $this->post('/api/archives', array_merge(p09Payload([
        'title' => 'General Archive Document',
        'document_number' => 'ARS/GEN/001',
        'description' => 'General archive description.',
    ]), ['file' => p09Pdf('first-version.pdf', $content)]), ['Accept' => 'application/json']);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Archive document created successfully.');

    $data = $response->json('data');

    expect($data['category'])->toBe('school_document')
        ->and($data['category_label'])->toBe('School Document')
        ->and($data['title'])->toBe('General Archive Document')
        ->and($data['document_number'])->toBe('ARS/GEN/001')
        ->and($data['document_date'])->toBe('2026-11-05')
        ->and($data['validity_status'])->toBe(ArchiveDocument::VALIDITY_NONE)
        ->and($data['subject'])->toBeNull()
        ->and($data['current_version'])->toBe(1)
        ->and($data['versions_count'])->toBe(1)
        ->and($data['version'])->toBe(1)
        ->and($data['can']['view'])->toBeTrue()
        ->and($data['can']['update'])->toBeTrue()
        ->and($data['can']['add_version'])->toBeTrue()
        ->and($data['can']['download'])->toBeTrue()
        ->and($data['latest_version']['version_number'])->toBe(1)
        ->and($data['latest_version']['original_name'])->toBe('first-version.pdf')
        ->and($data['latest_version']['mime_type'])->toBe('application/pdf')
        ->and($data['latest_version']['size_bytes'])->toBe(strlen($content));

    $payload = json_encode($response->json());

    expect($payload)->not->toContain('attachments/archives')
        ->and($payload)->not->toContain('checksum')
        ->and($payload)->not->toContain('"path"');

    $document = ArchiveDocument::findOrFail($data['id']);
    $version = ArchiveFileVersion::where('archive_document_id', $document->id)->firstOrFail();

    expect($version->version_number)->toBe(1)
        ->and($version->original_name)->toBe('first-version.pdf')
        ->and($version->mime_type)->toBe('application/pdf')
        ->and($version->size_bytes)->toBe(strlen($content))
        ->and($version->checksum_sha256)->toBe(hash('sha256', $content))
        ->and($version->uploaded_by)->toBe($this->operator->id)
        ->and($version->note)->toBeNull();

    expect($version->path)->toMatch('#^attachments/archives/'.$document->id.'/[0-9a-f-]{36}\.pdf$#');
    Storage::disk('local')->assertExists($version->path);

    $activity = Activity::where('table_name', 'archive_documents')
        ->where('record_id', $document->id)
        ->where('information', 'Created')
        ->firstOrFail();

    expect(json_encode($activity->new))->toContain('"current_version":1');
});

// T2 — invalid file and compensation when version persistence fails.
it('rejects invalid files and discards stored files when version persistence fails', function () {
    Sanctum::actingAs($this->operator);

    $this->post('/api/archives', array_merge(p09Payload(), [
        'file' => p09RealUpload('bad.exe', "%PDF-1.4\n%%EOF\n"),
    ]), ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    expect(ArchiveDocument::count())->toBe(0)
        ->and(ArchiveFileVersion::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);

    DB::statement(
        'CREATE TRIGGER p09_fail_archive_version BEFORE INSERT ON archive_file_versions '
        ."BEGIN SELECT RAISE(ABORT, 'forced archive version failure'); END"
    );

    $this->withoutExceptionHandling();

    try {
        expect(fn () => $this->post('/api/archives', array_merge(p09Payload(), [
            'file' => p09Pdf('trigger.pdf'),
        ]), ['Accept' => 'application/json']))
            ->toThrow(\Illuminate\Database\QueryException::class);
    } finally {
        DB::statement('DROP TRIGGER IF EXISTS p09_fail_archive_version');
    }

    expect(ArchiveDocument::count())->toBe(0)
        ->and(ArchiveFileVersion::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

// T3 — subject whitelist, existence, type matching, and archive visibility.
it('links only whitelisted subjects that exist for the selected type', function () {
    $studentDocument = p09Create($this, [
        'subject_type' => 'student',
        'subject_id' => $this->student->id,
    ]);

    expect($studentDocument['subject']['type'])->toBe('student')
        ->and($studentDocument['subject']['id'])->toBe($this->student->id)
        ->and($studentDocument['subject']['label'])->toBe('Synthetic Archive Student')
        ->and($studentDocument['subject']['secondary'])->toBe('NIS 0910001')
        ->and($studentDocument['subject']['archived'])->toBeFalse()
        ->and(array_keys($studentDocument['subject']))->toBe(['type', 'id', 'label', 'secondary', 'archived']);

    expect(p09ListIds($this, 'subject_type=student&subject_id='.$this->student->id))
        ->toBe([$studentDocument['id']]);

    $employeeDocument = p09Create($this, [
        'subject_type' => 'employee',
        'subject_id' => $this->employee->id,
    ]);

    expect($employeeDocument['subject']['type'])->toBe('employee')
        ->and($employeeDocument['subject']['label'])->toBe('Synthetic Archive Employee')
        ->and($employeeDocument['subject']['secondary'])->toStartWith('NUPTK ');

    $letterDocument = p09Create($this, [
        'subject_type' => 'letter',
        'subject_id' => $this->letter->id,
    ]);

    expect($letterDocument['subject']['type'])->toBe('letter')
        ->and($letterDocument['subject']['label'])->toBe('Synthetic Archive Letter Subject');

    $leaveDocument = p09Create($this, [
        'subject_type' => 'leave',
        'subject_id' => $this->leave->id,
    ]);

    expect($leaveDocument['subject']['type'])->toBe('leave')
        ->and($leaveDocument['subject']['secondary'])->toContain('Submitted');

    $deletedStudent = Student::factory()->create([
        'name' => 'Synthetic Archived Student',
        'nis' => '0910099',
        'nisn' => '0091000099',
    ]);
    $deletedStudent->delete();

    $archivedDocument = p09Create($this, [
        'subject_type' => 'student',
        'subject_id' => $deletedStudent->id,
    ]);

    expect($archivedDocument['subject']['archived'])->toBeTrue()
        ->and($archivedDocument['subject']['label'])->toBe('Synthetic Archived Student');

    $invalidPayloads = [
        ['subject_type' => 'school', 'subject_id' => $this->student->id],
        ['subject_type' => 'student', 'subject_id' => 999999],
        ['subject_type' => 'employee', 'subject_id' => $this->secondStudent->id],
        ['subject_type' => 'student'],
        ['subject_id' => $this->student->id],
    ];

    foreach ($invalidPayloads as $payload) {
        $response = $this->post('/api/archives', array_merge(p09Payload(), $payload, [
            'file' => p09Pdf(),
        ]), ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }
});

// T4 — viewer with archives.view only sees general documents.
it('limits archive viewers to general documents and hides linked archives', function () {
    p09MakeRole('test-archive-viewer', ['archives.view']);
    $viewer = $this->makeUserWithRole('test-archive-viewer');

    $general = p09Create($this, ['title' => 'Viewer General Document']);
    $linked = p09Create($this, [
        'title' => 'Viewer Student Document',
        'subject_type' => 'student',
        'subject_id' => $this->student->id,
    ]);

    Sanctum::actingAs($viewer);

    $list = $this->getJson('/api/archives')->assertOk()->json('data');
    expect($list['meta']['total'])->toBe(1)
        ->and(array_column($list['items'], 'id'))->toBe([$general['id']]);

    $this->getJson("/api/archives/{$general['id']}")->assertOk()
        ->assertJsonPath('data.can.update', false)
        ->assertJsonPath('data.can.add_version', false)
        ->assertJsonPath('data.can.download', true);

    $this->getJson("/api/archives/{$linked['id']}")->assertStatus(404);
    $this->getJson("/api/archives/{$linked['id']}/versions")->assertStatus(404);
    $this->get("/api/archives/{$linked['id']}/versions/1/download", ['Accept' => 'application/json'])
        ->assertStatus(404);

    $this->getJson('/api/archives?subject_type=student')->assertStatus(403);

    $options = $this->getJson('/api/archives/options')->assertOk()->json('data');
    expect($options['subject_types'])->toBe([])
        ->and($options['can']['manage'])->toBeFalse();

    $this->get('/api/archives/'.$general['id'].'/versions/1/download', ['Accept' => 'application/json'])
        ->assertOk();

    $this->post('/api/archives', array_merge(p09Payload(), ['file' => p09Pdf()]), ['Accept' => 'application/json'])
        ->assertStatus(403);
});

// T5 — manage ability and subject-domain restrictions on writes.
it('enforces manage ability and subject-domain restrictions on writes', function () {
    p09MakeRole('test-archive-manager', ['archives.view', 'archives.manage']);
    $manager = $this->makeUserWithRole('test-archive-manager');

    $general = p09Create($this, ['title' => 'Manager General Document'], null, $manager);

    $linked = p09Create($this, [
        'title' => 'Manager Student Document',
        'subject_type' => 'student',
        'subject_id' => $this->student->id,
    ]);

    Sanctum::actingAs($manager);

    $this->post('/api/archives', array_merge(p09Payload(), [
        'subject_type' => 'student',
        'subject_id' => $this->student->id,
        'file' => p09Pdf(),
    ]), ['Accept' => 'application/json'])->assertStatus(403);

    $this->putJson("/api/archives/{$general['id']}", [
        'title' => 'Manager General Document Updated',
        'expected_version' => $general['version'],
    ])->assertOk()->assertJsonPath('data.version', 2);

    $this->getJson("/api/archives/{$linked['id']}")->assertStatus(404);
    $this->putJson("/api/archives/{$linked['id']}", [
        'title' => 'Manager Student Document Updated',
        'expected_version' => $linked['version'],
    ])->assertStatus(403);
    $this->post("/api/archives/{$linked['id']}/versions", [
        'file' => p09Pdf(),
        'expected_version' => $linked['version'],
    ], ['Accept' => 'application/json'])->assertStatus(403);

    Sanctum::actingAs($this->principal);

    $this->post('/api/archives', array_merge(p09Payload(), ['file' => p09Pdf()]), ['Accept' => 'application/json'])
        ->assertStatus(403);
    $this->putJson("/api/archives/{$general['id']}", [
        'title' => 'Principal Attempt',
        'expected_version' => 2,
    ])->assertStatus(403);

    Sanctum::actingAs($this->operator);

    $this->putJson("/api/archives/{$linked['id']}", [
        'title' => 'Operator Subject Change Attempt',
        'subject_type' => 'student',
        'expected_version' => $linked['version'],
    ])->assertStatus(422)->assertJsonValidationErrors(['subject_type']);

    $this->putJson("/api/archives/{$linked['id']}", [
        'title' => 'Operator Stale Attempt',
        'expected_version' => 99,
    ])->assertStatus(409)
        ->assertJsonPath('latest.version', $linked['version'])
        ->assertJsonPath('latest.current_version', 1);

    expect(ArchiveDocument::findOrFail($linked['id'])->title)->toBe('Manager Student Document');

    $this->putJson("/api/archives/{$linked['id']}", [
        'title' => 'Operator Student Document Updated',
        'expected_version' => $linked['version'],
    ])->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.title', 'Operator Student Document Updated');

    $this->putJson("/api/archives/{$linked['id']}", [
        'title' => 'Missing Version',
    ])->assertStatus(422)->assertJsonValidationErrors(['expected_version']);
});

// T6 — append versions, history order, immutability, and optimistic locking.
it('appends immutable versions with optimistic locking and ordered history', function () {
    $contentOne = "%PDF-1.4\nArchive version one\n%%EOF\n";
    $contentTwo = "%PDF-1.4\nArchive version two\n%%EOF\n";
    $contentThree = "%PDF-1.4\nArchive version three\n%%EOF\n";

    $document = p09Create($this, ['title' => 'Versioned Archive Document'], p09Pdf('version-one.pdf', $contentOne));

    $second = p09AddVersion($this, $document['id'], 1, p09Pdf('version-two.pdf', $contentTwo), 'Second synthetic version');

    expect($second['current_version'])->toBe(2)
        ->and($second['version'])->toBe(2)
        ->and($second['versions_count'])->toBe(2)
        ->and($second['latest_version']['version_number'])->toBe(2)
        ->and($second['latest_version']['note'])->toBe('Second synthetic version');

    $third = p09AddVersion($this, $document['id'], 2, p09Pdf('version-three.pdf', $contentThree));

    expect($third['current_version'])->toBe(3)
        ->and($third['version'])->toBe(3)
        ->and($third['versions_count'])->toBe(3);

    $history = $this->getJson("/api/archives/{$document['id']}/versions")->assertOk()->json('data');

    expect(array_column($history['items'], 'version_number'))->toBe([3, 2, 1])
        ->and($history['meta']['total'])->toBe(3)
        ->and(json_encode($history))->not->toContain('attachments/archives')
        ->and(json_encode($history))->not->toContain('checksum');

    $this->get("/api/archives/{$document['id']}/versions/1/download", ['Accept' => 'application/json'])
        ->assertOk()
        ->assertStreamedContent($contentOne);
    $this->get("/api/archives/{$document['id']}/versions/2/download", ['Accept' => 'application/json'])
        ->assertOk()
        ->assertStreamedContent($contentTwo);

    $this->post("/api/archives/{$document['id']}/versions", [
        'file' => p09Pdf('stale.pdf'),
        'expected_version' => 1,
    ], ['Accept' => 'application/json'])
        ->assertStatus(409)
        ->assertJsonPath('latest.version', 3)
        ->assertJsonPath('latest.current_version', 3);

    expect(ArchiveFileVersion::where('archive_document_id', $document['id'])->count())->toBe(3)
        ->and(ArchiveDocument::findOrFail($document['id'])->current_version)->toBe(3);

    $version = ArchiveFileVersion::where('archive_document_id', $document['id'])->where('version_number', 1)->firstOrFail();
    $originalNote = $version->note;

    expect(fn () => $version->update(['note' => 'Changed note']))->toThrow(\DomainException::class);
    expect(fn () => $version->delete())->toThrow(\DomainException::class);

    expect($version->fresh()->note)->toBe($originalNote);
});

// T7 — file validation by content, server-generated names, and limits.
it('validates file types by content and stores server-generated names', function () {
    $pdf = p09Create($this, ['title' => 'PDF Archive'], p09Pdf('report.pdf'));

    expect($pdf['latest_version']['mime_type'])->toBe('application/pdf')
        ->and($pdf['latest_version']['original_name'])->toBe('report.pdf');

    $png = p09Create($this, ['title' => 'PNG Archive'], p09Image('photo.png', 'png'));
    expect($png['latest_version']['mime_type'])->toBe('image/png');

    $jpg = p09Create($this, ['title' => 'JPG Archive'], p09Image('photo.jpg', 'jpg'));
    expect($jpg['latest_version']['mime_type'])->toBe('image/jpeg');

    $jpeg = p09Create($this, ['title' => 'JPEG Archive'], p09Image('photo.jpeg', 'jpg'));
    expect($jpeg['latest_version']['mime_type'])->toBe('image/jpeg')
        ->and($jpeg['latest_version']['original_name'])->toBe('photo.jpeg');

    $jpegVersion = ArchiveFileVersion::where('archive_document_id', $jpeg['id'])->firstOrFail();
    expect($jpegVersion->path)->toMatch('#\.jpg$#');

    Sanctum::actingAs($this->operator);

    $doubleExtension = $this->post('/api/archives', array_merge(p09Payload([
        'title' => 'Double Extension Archive',
    ]), ['file' => p09RealUpload('report..pdf', "%PDF-1.4\n%%EOF\n")]), ['Accept' => 'application/json']);

    $doubleExtension->assertStatus(422)->assertJsonValidationErrors(['file']);

    $mismatch = $this->post('/api/archives', array_merge(p09Payload([
        'title' => 'Mismatched Archive',
    ]), ['file' => p09RealUpload('mismatch.pdf', p09PngBytes())]), ['Accept' => 'application/json']);

    $mismatch->assertStatus(422)->assertJsonValidationErrors(['file']);

    $oversized = $this->post('/api/archives', array_merge(p09Payload([
        'title' => 'Oversized Archive',
    ]), ['file' => UploadedFile::fake()->create('oversized.pdf', 10241)]), ['Accept' => 'application/json']);

    $oversized->assertStatus(422)->assertJsonValidationErrors(['file']);

    $weird = p09Create($this, ['title' => 'Weird Name Archive'], p09RealUpload('..\\..\\private report.pdf', "%PDF-1.4\n%%EOF\n"));

    expect($weird['latest_version']['original_name'])->toBe('private report.pdf');

    $weirdVersion = ArchiveFileVersion::where('archive_document_id', $weird['id'])->firstOrFail();
    expect($weirdVersion->path)->toMatch('#^attachments/archives/'.$weird['id'].'/[0-9a-f-]{36}\.pdf$#');

    $noExtension = $this->post('/api/archives', array_merge(p09Payload([
        'title' => 'No Extension Archive',
    ]), ['file' => p09RealUpload('report', "%PDF-1.4\n%%EOF\n")]), ['Accept' => 'application/json']);

    $noExtension->assertStatus(422)->assertJsonValidationErrors(['file']);

    expect(ArchiveDocument::whereIn('title', [
        'Double Extension Archive',
        'Mismatched Archive',
        'Oversized Archive',
        'No Extension Archive',
    ])->count())->toBe(0);
});

function p09PngBytes(): string
{
    $path = tempnam(sys_get_temp_dir(), 'p09-bytes-');
    $image = imagecreatetruecolor(4, 4);
    $color = imagecolorallocate($image, 200, 30, 30);
    imagefilledrectangle($image, 0, 0, 3, 3, $color);
    imagepng($image, $path);
    imagedestroy($image);

    $bytes = (string) file_get_contents($path);
    @unlink($path);

    return $bytes;
}

// T8 — metadata and date validation matrix.
it('validates archive metadata and date ranges', function () {
    Sanctum::actingAs($this->operator);

    $invalidPayloads = [
        'category missing' => [(function () {
            $payload = p09Payload();
            unset($payload['category']);

            return $payload;
        })(), 'category'],
        'category invalid' => [p09Payload(['category' => 'unknown-category']), 'category'],
        'title missing' => [(function () {
            $payload = p09Payload();
            unset($payload['title']);

            return $payload;
        })(), 'title'],
        'title too short' => [p09Payload(['title' => 'ab']), 'title'],
        'title too long' => [p09Payload(['title' => str_repeat('a', 201)]), 'title'],
        'document number too long' => [p09Payload(['document_number' => str_repeat('1', 101)]), 'document_number'],
        'document date missing' => [(function () {
            $payload = p09Payload();
            unset($payload['document_date']);

            return $payload;
        })(), 'document_date'],
        'document date invalid format' => [p09Payload(['document_date' => '2026/11/05']), 'document_date'],
        'valid from without until' => [p09Payload(['valid_from' => '2026-11-01']), 'valid_until'],
        'valid until without from' => [p09Payload(['valid_until' => '2026-12-01']), 'valid_from'],
        'valid until before from' => [p09Payload([
            'valid_from' => '2026-12-01',
            'valid_until' => '2026-11-01',
        ]), 'valid_until'],
        'description too long' => [p09Payload(['description' => str_repeat('d', 1001)]), 'description'],
        'subject type invalid' => [p09Payload(['subject_type' => 'school', 'subject_id' => $this->student->id]), 'subject_type'],
        'subject id without type' => [p09Payload(['subject_id' => $this->student->id]), 'subject_type'],
    ];

    foreach ($invalidPayloads as $label => [$payload, $field]) {
        $response = $this->post('/api/archives', array_merge($payload, [
            'file' => p09Pdf(),
        ]), ['Accept' => 'application/json']);

        $response->assertStatus(422, $label)->assertJsonValidationErrors([$field]);
    }

    $this->post('/api/archives', p09Payload(['title' => 'No File Archive']), ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    expect(ArchiveDocument::count())->toBe(0);

    $valid = p09Create($this, [
        'title' => '  Trimmed Archive Title  ',
        'document_number' => '',
        'description' => '',
        'valid_from' => '2026-11-01',
        'valid_until' => '2026-12-01',
    ]);

    expect($valid['title'])->toBe('Trimmed Archive Title')
        ->and($valid['document_number'])->toBeNull()
        ->and($valid['description'])->toBeNull()
        ->and($valid['valid_from'])->toBe('2026-11-01')
        ->and($valid['valid_until'])->toBe('2026-12-01');
});

// T9 — computed validity statuses and matching filters in WIB.
it('computes and filters validity statuses with WIB boundaries and upcoming precedence', function () {
    $documents = [
        'none' => p09Create($this, ['title' => 'Validity None', 'document_date' => '2026-10-01']),
        'upcoming_long' => p09Create($this, [
            'title' => 'Validity Upcoming Long',
            'document_date' => '2026-10-02',
            'valid_from' => '2026-11-11',
            'valid_until' => '2027-01-31',
        ]),
        'upcoming_short' => p09Create($this, [
            'title' => 'Validity Upcoming Short',
            'document_date' => '2026-10-03',
            'valid_from' => '2026-11-11',
            'valid_until' => '2026-12-05',
        ]),
        'valid' => p09Create($this, [
            'title' => 'Validity Valid',
            'document_date' => '2026-10-04',
            'valid_from' => '2026-11-01',
            'valid_until' => '2027-01-01',
        ]),
        'expiring_boundary' => p09Create($this, [
            'title' => 'Validity Expiring Boundary',
            'document_date' => '2026-10-05',
            'valid_from' => '2026-11-01',
            'valid_until' => '2026-12-10',
        ]),
        'expiring_today' => p09Create($this, [
            'title' => 'Validity Expiring Today',
            'document_date' => '2026-10-06',
            'valid_from' => '2026-11-01',
            'valid_until' => '2026-11-10',
        ]),
        'expired' => p09Create($this, [
            'title' => 'Validity Expired',
            'document_date' => '2026-10-07',
            'valid_from' => '2026-11-01',
            'valid_until' => '2026-11-09',
        ]),
    ];

    expect($documents['none']['validity_status'])->toBe(ArchiveDocument::VALIDITY_NONE)
        ->and($documents['upcoming_long']['validity_status'])->toBe(ArchiveDocument::VALIDITY_UPCOMING)
        ->and($documents['upcoming_short']['validity_status'])->toBe(ArchiveDocument::VALIDITY_UPCOMING)
        ->and($documents['valid']['validity_status'])->toBe(ArchiveDocument::VALIDITY_VALID)
        ->and($documents['expiring_boundary']['validity_status'])->toBe(ArchiveDocument::VALIDITY_EXPIRING_SOON)
        ->and($documents['expiring_today']['validity_status'])->toBe(ArchiveDocument::VALIDITY_EXPIRING_SOON)
        ->and($documents['expired']['validity_status'])->toBe(ArchiveDocument::VALIDITY_EXPIRED);

    $expected = [
        'none' => [$documents['none']['id']],
        'upcoming' => [$documents['upcoming_long']['id'], $documents['upcoming_short']['id']],
        'valid' => [$documents['valid']['id']],
        'expiring_soon' => [$documents['expiring_boundary']['id'], $documents['expiring_today']['id']],
        'expired' => [$documents['expired']['id']],
    ];

    foreach ($expected as $status => $ids) {
        $actual = p09ListIds($this, 'validity='.$status.'&per_page=100');
        sort($actual);
        sort($ids);

        expect($actual)->toBe($ids, 'validity filter: '.$status);
    }
});

// T10 — search, filters, pagination, and sorting.
it('searches, filters, paginates, and sorts archive documents', function () {
    $documents = [];

    for ($i = 1; $i <= 12; $i++) {
        $documents[$i] = p09Create($this, [
            'category' => $i % 2 === 0 ? 'other' : 'school_document',
            'title' => 'Archive Batch '.$i,
            'document_number' => 'ARS/77'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'/2026',
            'document_date' => '2026-10-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'description' => 'Description token desc'.$i,
        ]);
    }

    expect(p09ListIds($this, http_build_query(['q' => 'Batch 7'])))->toBe([$documents[7]['id']])
        ->and(p09ListIds($this, 'q=ARS%2F7704'))->toBe([$documents[4]['id']])
        ->and(p09ListIds($this, 'q=desc7'))->toBe([]);

    $otherIds = p09ListIds($this, 'category=other&per_page=100');
    sort($otherIds);
    expect($otherIds)->toBe([
        $documents[2]['id'], $documents[4]['id'], $documents[6]['id'],
        $documents[8]['id'], $documents[10]['id'], $documents[12]['id'],
    ]);

    $rangeIds = p09ListIds($this, 'date_from=2026-10-03&date_to=2026-10-05&per_page=100');
    sort($rangeIds);
    expect($rangeIds)->toBe([
        $documents[3]['id'], $documents[4]['id'], $documents[5]['id'],
    ]);

    $pageOne = $this->getJson('/api/archives?per_page=10')->assertOk()->json('data');
    expect($pageOne['meta']['total'])->toBe(12)
        ->and($pageOne['meta']['per_page'])->toBe(10)
        ->and($pageOne['meta']['last_page'])->toBe(2)
        ->and($pageOne['meta']['current_page'])->toBe(1)
        ->and(count($pageOne['items']))->toBe(10)
        ->and($pageOne['items'][0]['id'])->toBe($documents[12]['id'])
        ->and($pageOne['items'][9]['id'])->toBe($documents[3]['id']);

    $pageTwo = $this->getJson('/api/archives?per_page=10&page=2')->assertOk()->json('data');
    expect(count($pageTwo['items']))->toBe(2)
        ->and($pageTwo['items'][0]['id'])->toBe($documents[2]['id'])
        ->and($pageTwo['items'][1]['id'])->toBe($documents[1]['id']);

    $this->getJson('/api/archives?per_page=15')->assertStatus(422);
    $this->getJson('/api/archives?category=unknown')->assertStatus(422);
    $this->getJson('/api/archives?subject_type=unknown')->assertStatus(422);
    $this->getJson('/api/archives?subject_id='.$this->student->id)->assertStatus(422);
    $this->getJson('/api/archives?date_from=2026-10-10&date_to=2026-10-01')->assertStatus(422);
    $this->getJson('/api/archives?q='.str_repeat('a', 101))->assertStatus(422);
    $this->getJson('/api/archives?validity=unknown')->assertStatus(422);
});

// T11 — audit allowlist and hidden internals.
it('writes minimized audit records and never exposes paths or checksums', function () {
    $document = p09Create($this, [
        'title' => 'Confidential Archive Title',
        'document_number' => 'ARS/SECRET/001',
        'description' => 'Sensitive archive description.',
        'subject_type' => 'student',
        'subject_id' => $this->student->id,
    ]);

    $detailResponse = $this->getJson("/api/archives/{$document['id']}")->assertOk();
    $detail = $detailResponse->json('data');

    expect(json_encode($detail))->not->toContain('attachments/archives')
        ->and(json_encode($detail))->not->toContain('checksum')
        ->and(array_keys($detail['subject']))->toBe(['type', 'id', 'label', 'secondary', 'archived']);

    p09AddVersion($this, $document['id'], $document['version'], p09Pdf('private-version.pdf'), 'Sensitive version note');

    $created = Activity::where('table_name', 'archive_documents')
        ->where('record_id', $document['id'])
        ->where('information', 'Created')
        ->firstOrFail();

    $createdPayload = json_encode($created->new);

    expect($createdPayload)->toContain('"category"')
        ->and($createdPayload)->toContain('"document_date"')
        ->and($createdPayload)->toContain('"subject_type":"student"')
        ->and($createdPayload)->toContain('"current_version":1')
        ->and($createdPayload)->not->toContain('Confidential Archive Title')
        ->and($createdPayload)->not->toContain('ARS/SECRET/001')
        ->and($createdPayload)->not->toContain('Sensitive archive description.')
        ->and($createdPayload)->not->toContain('created_by')
        ->and($createdPayload)->not->toContain('updated_by');

    $updated = Activity::where('table_name', 'archive_documents')
        ->where('record_id', $document['id'])
        ->where('information', 'Updated')
        ->firstOrFail();

    $updatedPayload = json_encode([$updated->before, $updated->after]);

    expect($updatedPayload)->toContain('"current_version":2')
        ->and($updatedPayload)->not->toContain('Sensitive version note')
        ->and($updatedPayload)->not->toContain('private-version.pdf')
        ->and($updatedPayload)->not->toContain('attachments/archives')
        ->and($updatedPayload)->not->toContain('checksum');

    expect(Activity::where('table_name', 'archive_file_versions')->count())->toBe(0);

    $history = $this->getJson("/api/archives/{$document['id']}/versions")->assertOk()->json('data');
    expect(json_encode($history))->not->toContain('attachments/archives')
        ->and(json_encode($history))->not->toContain('checksum');
});

// T12 — authentication, abilities, route surface, and object scoping.
it('protects archive endpoints with authentication, abilities, and scoped routes', function () {
    $this->getJson('/api/archives/options')->assertStatus(401);
    $this->getJson('/api/archives')->assertStatus(401);
    $this->postJson('/api/archives', p09Payload())->assertStatus(401);
    $this->getJson('/api/archives/1')->assertStatus(401);
    $this->getJson('/api/archives/1/versions')->assertStatus(401);
    $this->getJson('/api/archives/1/versions/1/download')->assertStatus(401);
    $this->getJson('/api/archives/1/versions/1/preview')->assertStatus(401);
    $this->getJson('/api/archives/1/access-logs')->assertStatus(401);
    $this->getJson('/api/archives/tasks/expiring')->assertStatus(401);
    $this->getJson('/api/archives/retention/report')->assertStatus(401);
    $this->postJson('/api/archives/1/archive', ['archive_reason' => 'Synthetic retention reason.', 'expected_version' => 1])->assertStatus(401);
    $this->postJson('/api/archives/1/restore', ['expected_version' => 1])->assertStatus(401);

    Sanctum::actingAs($this->teacher);

    $this->getJson('/api/archives/options')->assertStatus(403);
    $this->getJson('/api/archives')->assertStatus(403);
    $this->getJson('/api/archives/subjects/student')->assertStatus(403);
    $this->getJson('/api/archives/tasks/expiring')->assertStatus(403);
    $this->getJson('/api/archives/retention/report')->assertStatus(403);
    $this->post('/api/archives', array_merge(p09Payload(), ['file' => p09Pdf()]), ['Accept' => 'application/json'])
        ->assertStatus(403);

    $documentA = p09Create($this, ['title' => 'Scoped Document A']);
    $documentB = p09Create($this, ['title' => 'Scoped Document B']);
    p09AddVersion($this, $documentB['id'], $documentB['version'], p09Pdf('scoped-b-two.pdf'));

    Sanctum::actingAs($this->operator);

    $this->getJson('/api/archives/not-a-number')->assertStatus(404);
    $this->getJson('/api/archives/999999')->assertStatus(404);
    $this->getJson('/api/archives/subjects/school')->assertStatus(404);
    $this->getJson("/api/archives/{$documentA['id']}/versions/2/download")->assertStatus(404);
    $this->getJson("/api/archives/{$documentA['id']}/versions/999/download")->assertStatus(404);
    $this->getJson("/api/archives/{$documentA['id']}/versions/999/preview")->assertStatus(404);

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/archives'));

    $methods = $routes->pluck('methods')->flatten()
        ->reject(fn (string $method) => $method === 'HEAD')
        ->unique()->sort()->values()->all();

    $uris = $routes->map(fn ($route) => $route->uri())->sort()->values()->all();

    // 9 route 09a + 6 route 09b = 15; tidak ada endpoint pemusnahan data.
    expect($routes)->toHaveCount(15)
        ->and($uris)->toContain('api/archives/tasks/expiring')
        ->and($uris)->toContain('api/archives/retention/report')
        ->and($uris)->toContain('api/archives/{archive}/access-logs')
        ->and($uris)->toContain('api/archives/{archive}/archive')
        ->and($uris)->toContain('api/archives/{archive}/restore')
        ->and($uris)->toContain('api/archives/{archive}/versions/{version}/preview')
        ->and($methods)->toBe(['GET', 'PATCH', 'POST', 'PUT'])
        ->and($routes->filter(fn ($route) => in_array('DELETE', $route->methods(), true)))->toBeEmpty()
        ->and($routes->filter(fn ($route) => str_contains($route->uri(), 'purge')))->toBeEmpty()
        ->and($routes->filter(fn ($route) => str_contains($route->uri(), 'destroy')))->toBeEmpty();
});

// T13 — download streaming headers, old versions, and missing files.
it('streams archive downloads with safe headers and supports old versions', function () {
    $contentOne = "%PDF-1.4\nDownload body one\n%%EOF\n";
    $contentTwo = "%PDF-1.4\nDownload body two\n%%EOF\n";

    $document = p09Create($this, ['title' => 'Download Archive'], p09Pdf('download-report.pdf', $contentOne));
    p09AddVersion($this, $document['id'], 1, p09Pdf('download-report-v2.pdf', $contentTwo));

    $latest = $this->get("/api/archives/{$document['id']}/versions/2/download", ['Accept' => 'application/json'])
        ->assertOk();

    $latest->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Document-Filename', 'download-report-v2.pdf')
        ->assertHeader('Access-Control-Expose-Headers', 'X-Document-Filename')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'")
        ->assertStreamedContent($contentTwo);

    expect($latest->headers->get('Cache-Control'))->toContain('private')
        ->and($latest->headers->get('Cache-Control'))->toContain('no-store')
        ->and($latest->headers->get('Content-Disposition'))->toBeNull()
        ->and($latest->headers->get('Content-Length'))->toBe((string) strlen($contentTwo));

    $old = $this->get("/api/archives/{$document['id']}/versions/1/download", ['Accept' => 'application/json'])
        ->assertOk()
        ->assertHeader('X-Document-Filename', 'download-report.pdf')
        ->assertStreamedContent($contentOne);

    expect($old->headers->get('Content-Disposition'))->toBeNull();

    $version = ArchiveFileVersion::where('archive_document_id', $document['id'])->where('version_number', 1)->firstOrFail();
    Storage::disk('local')->delete($version->path);

    $this->getJson("/api/archives/{$document['id']}/versions/1/download")
        ->assertStatus(404)
        ->assertJsonPath('message', 'Archive file not found.');

    $this->getJson("/api/archives/{$document['id']}/versions/999/download")
        ->assertStatus(404)
        ->assertJsonPath('message', 'Archive version not found.');
});

// Subject picker contract for linked domains.
it('lists subject picker options only for permitted domains', function () {
    Sanctum::actingAs($this->operator);

    $students = $this->getJson('/api/archives/subjects/student?'.http_build_query(['q' => 'Synthetic Archive Student']))
        ->assertOk()
        ->json('data');

    expect($students['type'])->toBe('student')
        ->and(array_column($students['items'], 'id'))->toContain($this->student->id)
        ->and(array_keys($students['items'][0]))->toBe(['type', 'id', 'label', 'secondary', 'archived']);

    $limited = $this->getJson('/api/archives/subjects/student?limit=1')->assertOk()->json('data');
    expect(count($limited['items']))->toBe(1);

    $employees = $this->getJson('/api/archives/subjects/employee?'.http_build_query(['q' => 'Synthetic Archive Employee']))
        ->assertOk()
        ->json('data');
    expect(array_column($employees['items'], 'id'))->toContain($this->employee->id);

    $letters = $this->getJson('/api/archives/subjects/letter?'.http_build_query(['q' => 'Synthetic Archive Letter']))
        ->assertOk()
        ->json('data');
    expect(array_column($letters['items'], 'id'))->toContain($this->letter->id);

    $leaves = $this->getJson('/api/archives/subjects/leave?q=submitted')
        ->assertOk()
        ->json('data');
    expect(array_column($leaves['items'], 'id'))->toContain($this->leave->id);

    $deleted = Student::factory()->create([
        'name' => 'Synthetic Picker Archived',
        'nis' => '0910088',
        'nisn' => '0091000088',
    ]);
    $deleted->delete();

    $picker = $this->getJson('/api/archives/subjects/student?'.http_build_query(['q' => 'Synthetic Picker Archived']))
        ->assertOk()
        ->json('data');

    expect($picker['items'][0]['archived'])->toBeTrue();

    $this->getJson('/api/archives/subjects/student?limit=0')->assertStatus(422);

    p09MakeRole('test-archive-picker-viewer', ['archives.view']);
    $viewer = $this->makeUserWithRole('test-archive-picker-viewer');

    Sanctum::actingAs($viewer);
    $this->getJson('/api/archives/subjects/student')->assertStatus(403);
});
