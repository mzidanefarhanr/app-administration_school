<?php

namespace App\Services\Letters;

use App\Models\Letter;
use App\Models\LetterDocument;
use App\Models\LetterSettings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Snapshot, render, dan penyimpanan dokumen PDF surat bertipe (Fase 07c, DEC-34).
 *
 * Tidak ada regenerate/reissue: dokumen dibuat sekali di dalam transaksi issue
 * dan baris letter_documents bersifat immutable. Preview dirender dari data
 * saat ini tanpa menulis file atau mengonsumsi nomor.
 */
class LetterDocumentService
{
    private const PREVIEW_STATUSES = [
        Letter::WORKFLOW_DRAFT,
        Letter::WORKFLOW_REVIEW,
        Letter::WORKFLOW_APPROVED,
    ];

    private const DOWNLOAD_STATUSES = [
        Letter::WORKFLOW_ISSUED,
        Letter::WORKFLOW_CANCELLED,
    ];

    private const MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public function __construct(
        private readonly LetterSettingsService $settings,
        private readonly LetterSubjectService $subjects,
        private readonly LetterSettingsAssetService $assets,
        private readonly LetterTemplateRegistry $templates,
        private readonly LetterDompdfRenderer $renderer,
    ) {}

    /**
     * Validasi readiness, subjek, dan field sebelum submit/issue.
     *
     * @return array<string, mixed>
     */
    public function prepare(Letter $letter): array
    {
        $letterType = (string) $letter->letter_type;

        if (! $letter->isTyped() || ! $this->templates->isKnown($letterType)) {
            throw ValidationException::withMessages([
                'letter_type' => ['The letter type is not available.'],
            ]);
        }

        $context = $this->settings->assertReady();
        $template = $this->templates->templateFor($letterType);
        $subject = $this->subjects->resolveForLetter($letter, $context['school_year']);

        return [
            'letter_type' => $letterType,
            'template' => $template,
            'settings' => $context['settings'],
            'profile' => $context['profile'],
            'subject' => $subject,
        ];
    }

    /**
     * Render dokumen final setelah nomor resmi dibuat.
     *
     * @param  array<string, mixed>  $prepared
     * @return array{snapshot: array<string, mixed>, template: array{code: string, version: int, view: string}, bytes: string, page_count: int, renderer: string}
     */
    public function renderIssued(
        array $prepared,
        Letter $letter,
        string $officialNumber,
        Carbon $issuedAt,
        User $actor,
    ): array {
        $snapshot = $this->snapshot($prepared, $letter, $officialNumber, $issuedAt, $actor);
        $rendered = $this->renderer->render(
            $prepared['template']['view'],
            $this->viewData($snapshot, $prepared['settings']),
        );

        return $rendered + [
            'snapshot' => $snapshot,
            'template' => $prepared['template'],
        ];
    }

    /**
     * Render preview watermarked tanpa nomor resmi dan tanpa menulis file.
     *
     * @param  array<string, mixed>  $prepared
     * @return array{bytes: string, page_count: int, renderer: string}
     */
    public function renderPreview(array $prepared, Letter $letter): array
    {
        $snapshot = $this->snapshot($prepared, $letter, null, null, null);

        return $this->renderer->render(
            $prepared['template']['view'],
            $this->viewData($snapshot, $prepared['settings']),
            (string) config('letters.document.preview_watermark'),
        );
    }

    /**
     * @return array{path: string, sha256: string, size_bytes: int}
     */
    public function write(Letter $letter, string $bytes): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('The generated letter PDF is empty.');
        }

        $directory = trim((string) config('letters.document.documents_path'), '/').'/'.$letter->id;
        $path = $directory.'/'.Str::uuid()->toString().'.pdf';
        $disk = Storage::disk((string) config('letters.disk'));

        if (! $disk->put($path, $bytes)) {
            throw new \RuntimeException('The letter PDF could not be stored.');
        }

        return [
            'path' => $path,
            'sha256' => hash('sha256', $bytes),
            'size_bytes' => strlen($bytes),
        ];
    }

    public function discard(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        try {
            Storage::disk((string) config('letters.disk'))->delete($path);
        } catch (\Throwable) {
            // Kompensasi file tidak boleh menutupi error asli.
        }
    }

    /**
     * @param  array<string, mixed>  $rendered
     * @param  array{path: string, sha256: string, size_bytes: int}  $stored
     */
    public function record(
        Letter $letter,
        array $rendered,
        array $stored,
        Carbon $generatedAt,
        User $actor,
    ): LetterDocument {
        return LetterDocument::create([
            'letter_id' => $letter->id,
            'template_code' => $rendered['template']['code'],
            'template_version' => $rendered['template']['version'],
            'letter_version' => (int) $letter->version,
            'official_number' => (string) $letter->official_number,
            'snapshot' => $rendered['snapshot'],
            'pdf_path' => $stored['path'],
            'pdf_sha256' => $stored['sha256'],
            'pdf_size_bytes' => $stored['size_bytes'],
            'page_count' => (int) $rendered['page_count'],
            'renderer' => (string) $rendered['renderer'],
            'generated_at' => $generatedAt,
            'generated_by' => $actor->id,
        ]);
    }

    /**
     * @return array{bytes: string, page_count: int, filename: string}
     */
    public function preview(int $letterId): array
    {
        $letter = $this->findOutgoingTyped($letterId);

        if (! in_array((string) $letter->workflow_status, self::PREVIEW_STATUSES, true)) {
            $this->notFound('A preview is only available for draft, review, or approved typed letters.');
        }

        $prepared = $this->prepare($letter);
        $rendered = $this->renderPreview($prepared, $letter);

        return [
            'bytes' => $rendered['bytes'],
            'page_count' => $rendered['page_count'],
            'filename' => 'preview-letter-'.$letter->id.'.pdf',
        ];
    }

    /**
     * @return array{bytes: string, filename: string, size_bytes: int}
     */
    public function download(int $letterId): array
    {
        $letter = $this->findOutgoingTyped($letterId);

        if (! in_array((string) $letter->workflow_status, self::DOWNLOAD_STATUSES, true)) {
            $this->notFound('A stored document is only available for issued or cancelled letters.');
        }

        $document = $letter->document()->first();

        if ($document === null) {
            $this->notFound('No document is available for this letter.');
        }

        $disk = Storage::disk((string) config('letters.disk'));

        if (! $disk->exists($document->pdf_path)) {
            $this->notFound('The stored letter document is missing.');
        }

        $bytes = $disk->get($document->pdf_path);

        if (! is_string($bytes) || $bytes === '') {
            $this->notFound('The stored letter document could not be read.');
        }

        return [
            'bytes' => $bytes,
            'filename' => $this->filename((string) $document->official_number, (int) $letter->id),
            'size_bytes' => (int) $document->pdf_size_bytes,
        ];
    }

    /**
     * Ringkasan dokumen untuk detail surat; tanpa path, hash, atau snapshot.
     *
     * @return array{available: bool, template_code: ?string, template_version: ?int, page_count: ?int, generated_at: ?string, size_bytes: ?int}
     */
    public function summary(Letter $letter): array
    {
        $document = $letter->relationLoaded('document')
            ? $letter->document
            : $letter->document()->first();

        if ($document === null) {
            return [
                'available' => false,
                'template_code' => null,
                'template_version' => null,
                'page_count' => null,
                'generated_at' => null,
                'size_bytes' => null,
            ];
        }

        return [
            'available' => true,
            'template_code' => $document->template_code,
            'template_version' => $document->template_version,
            'page_count' => $document->page_count,
            'generated_at' => $document->generated_at?->toIso8601String(),
            'size_bytes' => $document->pdf_size_bytes,
        ];
    }

    public function hasDocument(Letter $letter): bool
    {
        if ($letter->relationLoaded('document')) {
            return $letter->document !== null;
        }

        return $letter->document()->exists();
    }

    /**
     * @param  array<string, mixed>  $prepared
     * @return array<string, mixed>
     */
    private function snapshot(
        array $prepared,
        Letter $letter,
        ?string $officialNumber,
        ?Carbon $issuedAt,
        ?User $actor,
    ): array {
        /** @var LetterSettings $settings */
        $settings = $prepared['settings'];
        $profile = $prepared['profile'];
        $subject = $prepared['subject'];

        return [
            'template' => [
                'code' => $prepared['template']['code'],
                'version' => $prepared['template']['version'],
            ],
            'school' => [
                'name' => $settings->school_name,
                'npsn' => $profile?->education_school_npsn,
                'government_line' => $settings->government_line,
                'address_line' => $settings->address_line,
                'contact_line' => $settings->contact_line,
                'place_of_issue' => $settings->place_of_issue,
                'logo_sha256' => $settings->logo_sha256,
            ],
            'signatory' => [
                'name' => $settings->signatory_name,
                'title' => $settings->signatory_title,
                'identifier_label' => $settings->signatory_identifier_label,
                'identifier' => $settings->signatory_identifier,
                'signature_sha256' => $settings->signature_sha256,
                'stamp_sha256' => $settings->stamp_sha256,
            ],
            'letter' => [
                'official_number' => $officialNumber,
                'letter_date' => $letter->letter_date?->format('Y-m-d'),
                'subject' => $letter->subject,
                'recipient' => $letter->recipient,
                'category' => $letter->category,
            ],
            'subject' => $subject['snapshot'],
            'fields' => $subject['fields'],
            'issued_at' => $issuedAt?->toIso8601String(),
            'issued_by' => $actor ? [
                'id' => $actor->id,
                'name' => $actor->name,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function viewData(array $snapshot, LetterSettings $settings): array
    {
        $school = $snapshot['school'];
        $school['school_name'] = $school['name'];
        $school['place_of_issue'] = $school['place_of_issue'] ?: $school['name'];

        return [
            'school' => $school,
            'signatory' => $snapshot['signatory'],
            'letter' => $snapshot['letter'],
            'subject' => $snapshot['subject'],
            'fields' => $snapshot['fields'],
            'logo' => $this->assets->dataUri($settings, 'logo'),
            'signature' => $this->assets->dataUri($settings, 'signature'),
            'stamp' => $this->assets->dataUri($settings, 'stamp'),
            'dates' => [
                'letter' => $this->localizedDate($snapshot['letter']['letter_date'] ?? null),
                'birthdate' => $this->localizedDate($snapshot['subject']['birthdate'] ?? null),
                'effective' => $this->localizedDate($snapshot['subject']['effective_date'] ?? null),
                'start' => $this->localizedDate($snapshot['fields']['start_date'] ?? null),
                'end' => $this->localizedDate($snapshot['fields']['end_date'] ?? null),
            ],
        ];
    }

    private function localizedDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }

        $month = self::MONTHS[(int) $parsed->format('n')] ?? $parsed->format('F');

        return $parsed->format('j').' '.$month.' '.$parsed->format('Y');
    }

    private function filename(string $officialNumber, int $letterId): string
    {
        $base = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', trim($officialNumber));
        $base = trim((string) preg_replace('/[\x00-\x1F]/', '', $base), ' .-');

        if ($base === '') {
            $base = 'letter-'.$letterId;
        }

        if (mb_strlen($base) > 120) {
            $base = mb_substr($base, 0, 120);
        }

        return $base.'.pdf';
    }

    private function findOutgoingTyped(int $letterId): Letter
    {
        $letter = Letter::query()
            ->where('direction', Letter::DIRECTION_OUTGOING)
            ->whereKey($letterId)
            ->first();

        if ($letter === null || $letter->letter_type === null) {
            $this->notFound('Letter document not found.');
        }

        return $letter;
    }

    private function notFound(string $message): never
    {
        abort(response()->json(['message' => $message], 404));
    }
}
