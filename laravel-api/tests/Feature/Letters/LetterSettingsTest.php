<?php

use App\Models\AcademicSetting;
use App\Models\Activity;
use App\Models\Letter;
use App\Models\LetterDocument;
use App\Models\LetterSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
});

afterEach(function () {
    Carbon::setTestNow();
});

function p07cStoredFiles(int $profileId): array
{
    return Storage::disk('local')->allFiles('attachments/letter-settings/'.$profileId);
}

function p07cMinimalPdf(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
}

// T1 — settings, pickers, dan aset per peran.
it('enforces letter settings, subject picker, and asset permissions', function () {
    $draft = $this->typedLetterRecord('student_active');
    $general = $this->typedLetterRecord('student_active', ['letter_type' => null, 'document_subject_type' => null, 'document_subject_id' => null, 'document_fields' => null]);
    $issued = $this->typedLetterRecord('student_active', [
        'workflow_status' => Letter::WORKFLOW_ISSUED,
        'official_number' => '001/20512345/2026',
        'issued_at' => Carbon::now(),
        'issued_by' => $this->principal->id,
    ]);

    $documentPath = 'attachments/letter-documents/'.$issued->id.'/fixture.pdf';
    Storage::disk('local')->put($documentPath, p07cMinimalPdf());

    LetterDocument::create([
        'letter_id' => $issued->id,
        'template_code' => 'student_active',
        'template_version' => 1,
        'letter_version' => 4,
        'official_number' => '001/20512345/2026',
        'snapshot' => ['template' => ['code' => 'student_active', 'version' => 1]],
        'pdf_path' => $documentPath,
        'pdf_sha256' => hash('sha256', p07cMinimalPdf()),
        'pdf_size_bytes' => strlen(p07cMinimalPdf()),
        'page_count' => 1,
        'renderer' => 'dompdf/2.0.8',
        'generated_at' => Carbon::now(),
        'generated_by' => $this->principal->id,
    ]);

    $this->getJson('/api/letters/settings')->assertStatus(401);
    $this->postJson('/api/letters/settings/header', [])->assertStatus(401);
    $this->postJson('/api/letters/settings/signatory', [])->assertStatus(401);
    $this->get('/api/letters/settings/assets/logo', ['Accept' => 'application/json'])->assertStatus(401);
    $this->getJson('/api/letters/outgoing/subjects/students')->assertStatus(401);
    $this->get("/api/letters/outgoing/{$draft->id}/document/preview", ['Accept' => 'application/json'])->assertStatus(401);
    $this->get("/api/letters/outgoing/{$issued->id}/document", ['Accept' => 'application/json'])->assertStatus(401);

    Sanctum::actingAs($this->operator);

    $this->getJson('/api/letters/settings')
        ->assertOk()
        ->assertJsonPath('data.permissions.can_manage_header', true)
        ->assertJsonPath('data.permissions.can_manage_signatory', false);

    $this->post('/api/letters/settings/header', [
        'expected_version' => 0,
        'school_name' => 'SMA Negeri 1 UAT',
        'address_line' => 'Jalan Pendidikan Nomor 1',
        'place_of_issue' => 'Synthetic City',
        'logo' => $this->pngUpload('logo.png'),
    ], ['Accept' => 'application/json'])->assertOk();

    $this->postJson('/api/letters/settings/signatory', [])->assertStatus(403);
    $this->get('/api/letters/settings/assets/logo')->assertOk();
    $this->get('/api/letters/settings/assets/signature')->assertStatus(403);

    $this->getJson('/api/letters/outgoing/subjects/students')->assertOk();
    $this->getJson('/api/letters/outgoing/subjects/student-mutations')->assertOk();
    $this->getJson('/api/letters/outgoing/subjects/employees')->assertOk();

    Sanctum::actingAs($this->admin);
    $this->post('/api/letters/settings/header', [
        'expected_version' => 1,
        'school_name' => 'SMA Negeri 1 UAT',
        'address_line' => 'Jalan Pendidikan Nomor 1',
        'place_of_issue' => 'Synthetic City',
    ], ['Accept' => 'application/json'])->assertOk();
    $this->postJson('/api/letters/settings/signatory', [])->assertStatus(403);
    $this->get('/api/letters/settings/assets/signature')->assertStatus(403);

    Sanctum::actingAs($this->principal);
    $this->getJson('/api/letters/settings')->assertOk();
    $this->postJson('/api/letters/settings/header', ['expected_version' => 1])->assertStatus(403);
    $this->post('/api/letters/settings/signatory', [
        'expected_version' => 1,
        'signatory_name' => 'Synthetic Principal',
        'signatory_title' => 'Kepala Sekolah',
        'signature' => $this->pngUpload('signature.png'),
    ], ['Accept' => 'application/json'])->assertOk();
    $this->get('/api/letters/settings/assets/signature')->assertOk();
    $this->getJson('/api/letters/outgoing/subjects/students')->assertStatus(403);
    $this->get("/api/letters/outgoing/{$draft->id}/document/preview")->assertOk();
    $this->get("/api/letters/outgoing/{$issued->id}/document")->assertOk();

    Sanctum::actingAs($this->wakil);
    $this->getJson('/api/letters/settings')->assertOk();
    $this->postJson('/api/letters/settings/header', [])->assertStatus(403);
    $this->postJson('/api/letters/settings/signatory', [])->assertStatus(403);
    $this->getJson('/api/letters/outgoing/subjects/students')->assertStatus(403);
    $this->get('/api/letters/settings/assets/logo')->assertOk();
    $this->get("/api/letters/outgoing/{$draft->id}/document/preview")->assertOk();
    $this->get("/api/letters/outgoing/{$issued->id}/document")->assertOk();
    $this->get("/api/letters/outgoing/{$general->id}/document/preview")->assertStatus(404);
    $this->get('/api/letters/outgoing/999999/document')->assertStatus(404);
});

// T2 — readiness tanpa konteks lengkap tidak menghasilkan 500.
it('reports readiness without an active context without server errors', function () {
    Sanctum::actingAs($this->wakil);

    $this->getJson('/api/letters/settings')
        ->assertOk()
        ->assertJsonPath('data.active_school_year.name', '2026/2027')
        ->assertJsonPath('data.school_profile.npsn', '20512345')
        ->assertJsonPath('data.settings', null)
        ->assertJsonPath('data.readiness.active_year', true)
        ->assertJsonPath('data.readiness.profile', true)
        ->assertJsonPath('data.readiness.header', false)
        ->assertJsonPath('data.readiness.signatory', false)
        ->assertJsonPath('data.readiness.signature', false)
        ->assertJsonPath('data.readiness.ready', false)
        ->assertJsonPath('data.readiness.missing', ['header', 'signatory', 'signature'])
        ->assertJsonPath('data.suggestions.school_name', 'SMA Negeri 1 UAT')
        ->assertJsonPath('data.suggestions.address_line', 'Jalan Pendidikan Nomor 1')
        ->assertJsonPath('data.suggestions.signatory_name', $this->principal->name)
        ->assertJsonPath('data.suggestions.signatory_title', 'Kepala Sekolah')
        ->assertJsonPath('data.suggestions.contact_line', 'school@example.test');

    AcademicSetting::find(AcademicSetting::SINGLETON_ID)->update(['active_school_year_id' => null]);

    $noContext = $this->getJson('/api/letters/settings')->assertOk();
    expect($noContext->json('data.active_school_year'))->toBeNull()
        ->and($noContext->json('data.school_profile'))->toBeNull()
        ->and($noContext->json('data.settings'))->toBeNull()
        ->and($noContext->json('data.readiness.missing'))
        ->toContain('active_school_year')
        ->toContain('school_profile')
        ->toContain('header')
        ->toContain('signatory')
        ->toContain('signature');

    AcademicSetting::find(AcademicSetting::SINGLETON_ID)->update(['active_school_year_id' => $this->nextYear->id]);

    $noProfile = $this->getJson('/api/letters/settings')->assertOk();
    expect($noProfile->json('data.active_school_year.name'))->toBe('2027/2028')
        ->and($noProfile->json('data.school_profile'))->toBeNull()
        ->and($noProfile->json('data.readiness.missing'))
        ->toContain('school_profile')
        ->not->toContain('active_school_year');

    expect($noProfile->getContent())->not->toContain('attachments/')
        ->and($noProfile->getContent())->not->toContain('.png');
});

// T3 — header dan logo privat.
it('saves headers with a private re-encoded logo and audit trail', function () {
    Sanctum::actingAs($this->operator);

    $response = $this->post('/api/letters/settings/header', [
        'expected_version' => 0,
        'government_line' => 'PEMERINTAH PROVINSI UAT',
        'school_name' => 'SMA Negeri 1 UAT',
        'address_line' => 'Jalan Pendidikan Nomor 1',
        'contact_line' => 'school@example.test',
        'place_of_issue' => 'Synthetic City',
        'logo' => $this->pngUpload('logo.png'),
    ], ['Accept' => 'application/json'])->assertOk();

    $response->assertJsonPath('data.settings.school_name', 'SMA Negeri 1 UAT')
        ->assertJsonPath('data.settings.version', 1)
        ->assertJsonPath('data.settings.logo_available', true)
        ->assertJsonPath('data.settings.stamp_available', false)
        ->assertJsonPath('data.readiness.header', true)
        ->assertJsonPath('data.readiness.ready', false);

    expect($response->getContent())->not->toContain('attachments/')
        ->and($response->getContent())->not->toContain('.png');

    $settings = LetterSettings::where('school_profile_id', $this->profile->id)->firstOrFail();
    expect($settings->version)->toBe(1)
        ->and($settings->logo_path)->toStartWith('attachments/letter-settings/'.$this->profile->id.'/logo-')
        ->and(Storage::disk('local')->exists($settings->logo_path))->toBeTrue()
        ->and(Storage::disk('local')->get($settings->logo_path))->toStartWith("\x89PNG")
        ->and(hash('sha256', Storage::disk('local')->get($settings->logo_path)))->toBe($settings->logo_sha256);

    $audit = Activity::where('table_name', 'letter_settings')
        ->where('record_id', $settings->id)
        ->where('information', 'Created')
        ->first();

    expect($audit)->not->toBeNull()
        ->and(json_encode($audit->new))->toContain('SMA Negeri 1 UAT')
        ->and(json_encode($audit->new))->not->toContain('Jalan Pendidikan Nomor 1')
        ->and(json_encode($audit->new))->not->toContain('attachments/');

    $oldPath = $settings->logo_path;

    $this->post('/api/letters/settings/header', [
        'expected_version' => 1,
        'school_name' => 'SMA Negeri 1 UAT',
        'address_line' => 'Jalan Pendidikan Nomor 1',
        'place_of_issue' => 'Synthetic City',
        'logo' => $this->pngUpload('logo.png', $this->pngBytes(200, 80, 10)),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.settings.version', 2);

    $settings->refresh();

    expect($settings->logo_path)->not->toBe($oldPath)
        ->and(Storage::disk('local')->exists($oldPath))->toBeFalse()
        ->and(Storage::disk('local')->exists($settings->logo_path))->toBeTrue();

    $this->post('/api/letters/settings/header', [
        'expected_version' => 2,
        'school_name' => 'SMA Negeri 1 UAT',
        'address_line' => 'Jalan Pendidikan Nomor 1',
        'place_of_issue' => 'Synthetic City',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.settings.version', 2);
});

// T4 — logo tidak valid tidak boleh mengubah settings.
it('rejects invalid logo uploads without mutating settings', function () {
    $settings = $this->makeLetterSettings();
    $originalPath = $settings->logo_path;
    $originalFiles = p07cStoredFiles($this->profile->id);

    $large = imagecreatetruecolor(2001, 2);
    imagefill($large, 0, 0, imagecolorallocate($large, 30, 30, 30));
    ob_start();
    imagepng($large);
    $oversizedDocument = (string) ob_get_clean();
    imagedestroy($large);

    $invalidFiles = [
        'svg' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        'php' => UploadedFile::fake()->createWithContent('logo.php', $this->pngBytes()),
        'oversized' => UploadedFile::fake()->createWithContent('logo.png', str_repeat('a', 2049 * 1024)),
        'dimension' => UploadedFile::fake()->createWithContent('logo.png', $oversizedDocument),
        'corrupt' => UploadedFile::fake()->createWithContent('logo.png', 'not-an-image'),
    ];

    Sanctum::actingAs($this->operator);

    foreach ($invalidFiles as $label => $file) {
        $this->post('/api/letters/settings/header', [
            'expected_version' => 1,
            'school_name' => 'Changed By Invalid Upload',
            'logo' => $file,
        ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    $settings->refresh();

    expect($settings->version)->toBe(1)
        ->and($settings->school_name)->toBe('SMA Negeri 1 UAT')
        ->and($settings->logo_path)->toBe($originalPath)
        ->and(Storage::disk('local')->exists($originalPath))->toBeTrue()
        ->and(p07cStoredFiles($this->profile->id))->toBe($originalFiles)
        ->and(LetterSettings::count())->toBe(1);
});

// T5 — signatory, signature, dan stamp privat.
it('saves the signatory with private signature and stamp assets', function () {
    $settings = $this->makeLetterSettings();
    $oldSignature = $settings->signature_path;
    $oldStamp = $settings->stamp_path;

    $jpeg = imagecreatetruecolor(24, 24);
    imagefill($jpeg, 0, 0, imagecolorallocate($jpeg, 30, 160, 60));
    ob_start();
    imagejpeg($jpeg, null, 90);
    $jpegBytes = (string) ob_get_clean();
    imagedestroy($jpeg);

    Sanctum::actingAs($this->principal);

    $response = $this->post('/api/letters/settings/signatory', [
        'expected_version' => 1,
        'signatory_name' => 'Synthetic Headmaster',
        'signatory_title' => 'Kepala Sekolah',
        'signatory_identifier_label' => 'NIP',
        'signatory_identifier' => '199002022010011002',
        'signature' => UploadedFile::fake()->createWithContent('signature.jpg', $jpegBytes),
        'stamp' => $this->pngUpload('stamp.png', $this->pngBytes(200, 120, 0)),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.settings.signatory_name', 'Synthetic Headmaster')
        ->assertJsonPath('data.settings.signature_available', true)
        ->assertJsonPath('data.settings.stamp_available', true)
        ->assertJsonPath('data.settings.version', 2)
        ->assertJsonPath('data.permissions.can_manage_signatory', true);

    expect($response->getContent())->not->toContain('attachments/')
        ->and($response->getContent())->not->toContain('.png');

    $settings->refresh();

    expect($settings->signature_path)->not->toBe($oldSignature)
        ->and($settings->stamp_path)->not->toBe($oldStamp)
        ->and(Storage::disk('local')->exists($oldSignature))->toBeFalse()
        ->and(Storage::disk('local')->exists($oldStamp))->toBeFalse()
        ->and(Storage::disk('local')->exists($settings->signature_path))->toBeTrue()
        ->and(Storage::disk('local')->get($settings->signature_path))->toStartWith("\x89PNG")
        ->and($settings->signature_mime)->toBe('image/png')
        ->and(hash('sha256', Storage::disk('local')->get($settings->signature_path)))->toBe($settings->signature_sha256);

    $auditJson = Activity::where('table_name', 'letter_settings')
        ->where('record_id', $settings->id)
        ->get()
        ->map(fn (Activity $activity) => json_encode([$activity->before, $activity->after, $activity->new]))
        ->implode(' ');

    expect($auditJson)->not->toContain('Synthetic Headmaster')
        ->and($auditJson)->not->toContain('199002022010011002')
        ->and($auditJson)->not->toContain('attachments/')
        ->and($auditJson)->not->toContain('signature.jpg');
});

// T6 — akses aset sesuai ability dan whitelist.
it('serves whitelisted settings assets only to permitted abilities', function () {
    $settings = $this->makeLetterSettings();

    Sanctum::actingAs($this->operator);

    $logo = $this->get('/api/letters/settings/assets/logo')->assertOk();
    expect($logo->headers->get('Content-Type'))->toContain('image/png')
        ->and($logo->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($logo->headers->get('Content-Security-Policy'))->toContain('sandbox');

    $this->get('/api/letters/settings/assets/signature')->assertStatus(403);
    $this->get('/api/letters/settings/assets/stamp')->assertStatus(403);
    $this->get('/api/letters/settings/assets/passport')->assertStatus(404);

    Sanctum::actingAs($this->wakil);
    $this->get('/api/letters/settings/assets/logo')->assertOk();
    $this->get('/api/letters/settings/assets/signature')->assertStatus(403);

    Sanctum::actingAs($this->principal);
    $this->get('/api/letters/settings/assets/signature')->assertOk();
    $this->get('/api/letters/settings/assets/stamp')->assertOk();

    expect(Storage::disk('local')->get($settings->logo_path))->not->toBe('');
});

// T7 — settings terpisah per tahun ajaran/profil.
it('scopes settings per school profile and active year', function () {
    $first = $this->makeLetterSettings();
    $firstLogo = Storage::disk('local')->get($first->logo_path);

    $secondProfile = $this->makeProfile($this->nextSchool, $this->nextYear);

    AcademicSetting::find(AcademicSetting::SINGLETON_ID)
        ->update(['active_school_year_id' => $this->nextYear->id]);

    Sanctum::actingAs($this->operator);

    $this->post('/api/letters/settings/header', [
        'expected_version' => 0,
        'school_name' => 'SMA Negeri 2 UAT',
        'address_line' => 'Jalan Pendidikan Nomor 2',
        'place_of_issue' => 'Synthetic City',
        'logo' => $this->pngUpload('logo.png', $this->pngBytes(10, 160, 90)),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.settings.school_name', 'SMA Negeri 2 UAT')
        ->assertJsonPath('data.settings.version', 1)
        ->assertJsonPath('data.active_school_year.name', '2027/2028')
        ->assertJsonPath('data.school_profile.npsn', '20512346');

    $second = LetterSettings::where('school_profile_id', $secondProfile->id)->firstOrFail();

    expect($second->school_profile_id)->toBe($secondProfile->id)
        ->and($first->fresh()->school_name)->toBe('SMA Negeri 1 UAT')
        ->and($first->fresh()->version)->toBe(1)
        ->and($second->logo_path)->toContain('/'.$secondProfile->id.'/');

    $secondLogo = $this->get('/api/letters/settings/assets/logo')->assertOk();
    expect($secondLogo->streamedContent())->not->toBe($firstLogo);

    $this->getJson('/api/letters/outgoing/options')
        ->assertOk()
        ->assertJsonPath('data.default_sender', 'SMA Negeri 2 UAT');
});

// T8 — versi basi tidak mengubah settings.
it('rejects stale settings versions without mutating', function () {
    $settings = $this->makeLetterSettings();

    Sanctum::actingAs($this->operator);

    $this->postJson('/api/letters/settings/header', [
        'expected_version' => 99,
        'school_name' => 'Stale Header',
    ])
        ->assertStatus(409)
        ->assertJsonPath('current_versions.settings_version', 1);

    $this->postJson('/api/letters/settings/header', [
        'expected_version' => 0,
        'school_name' => 'Older Header',
    ])->assertStatus(409);

    Sanctum::actingAs($this->principal);

    $this->postJson('/api/letters/settings/signatory', [
        'expected_version' => 99,
        'signatory_name' => 'Stale Principal',
        'signatory_title' => 'Kepala Sekolah',
    ])->assertStatus(409);

    $settings->refresh();

    expect($settings->version)->toBe(1)
        ->and($settings->school_name)->toBe('SMA Negeri 1 UAT')
        ->and($settings->signatory_name)->toBe('Synthetic Principal');
});

// T9 — default sender memakai profil aktif dengan fallback profil pertama.
it('uses the active profile school as the default sender with a fallback', function () {
    $this->makeLetterSettings();
    $this->makeProfile($this->nextSchool, $this->nextYear);

    Sanctum::actingAs($this->wakil);

    $this->getJson('/api/letters/outgoing/options')
        ->assertOk()
        ->assertJsonPath('data.default_sender', 'SMA Negeri 1 UAT');

    AcademicSetting::find(AcademicSetting::SINGLETON_ID)->update(['active_school_year_id' => null]);

    $this->getJson('/api/letters/outgoing/options')
        ->assertOk()
        ->assertJsonPath('data.default_sender', 'SMA Negeri 1 UAT');

    $this->profile->delete();

    $this->getJson('/api/letters/outgoing/options')
        ->assertOk()
        ->assertJsonPath('data.default_sender', 'SMA Negeri 2 UAT');
});

// Route inventory settings.
it('exposes letter settings routes with letter abilities', function () {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/letters/settings'));

    expect($routes)->toHaveCount(4);

    foreach ($routes as $route) {
        $middleware = $route->gatherMiddleware();
        $hasLetterAbility = collect($middleware)->contains(
            fn ($entry) => is_string($entry) && str_starts_with($entry, 'can:letters.'),
        );

        expect($hasLetterAbility)->toBeTrue();
    }
});
