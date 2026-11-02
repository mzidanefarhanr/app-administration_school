<?php

namespace App\Services\Letters;

use App\Models\AcademicSetting;
use App\Models\LetterSettings;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kop, penandatangan, dan readiness surat bertipe (Fase 07c, DEC-34).
 *
 * Resolusi mengikuti academic_settings.active_school_year_id →
 * school_profiles.school_year_id → letter_settings. Update memakai
 * optimistic locking dan aset ditulis oleh LetterSettingsAssetService.
 */
class LetterSettingsService
{
    public function __construct(
        private readonly LetterSettingsAssetService $assets,
        private readonly LetterTemplateRegistry $templates,
    ) {}

    /**
     * @return array{setting: AcademicSetting|null, school_year: SchoolYear|null, profile: SchoolProfile|null, settings: LetterSettings|null}
     */
    public function context(): array
    {
        $setting = AcademicSetting::query()->first();
        $schoolYear = $setting?->activeSchoolYear;

        if ($schoolYear?->trashed()) {
            $schoolYear = null;
        }

        $profile = $schoolYear
            ? SchoolProfile::query()
                ->with([
                    'educationSchool:id,npsn,name,address',
                    'principal:id,name',
                ])
                ->where('school_year_id', $schoolYear->id)
                ->first()
            : null;

        $settings = $profile
            ? LetterSettings::query()
                ->with('updatedBy:id,name')
                ->where('school_profile_id', $profile->id)
                ->first()
            : null;

        return [
            'setting' => $setting,
            'school_year' => $schoolYear,
            'profile' => $profile,
            'settings' => $settings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $actor): array
    {
        $this->templates->assertValid();

        $context = $this->context();
        $settings = $context['settings'];

        return [
            'active_school_year' => $context['school_year'] ? [
                'id' => $context['school_year']->id,
                'name' => $context['school_year']->name,
            ] : null,
            'school_profile' => $context['profile'] ? [
                'id' => $context['profile']->id,
                'npsn' => $context['profile']->education_school_npsn,
                'school_name' => $context['profile']->educationSchool?->name,
            ] : null,
            'settings' => $settings ? $this->serializeSettings($settings) : null,
            'readiness' => $this->readiness($context),
            'suggestions' => $this->suggestions($context['profile']),
            'permissions' => [
                'can_manage_header' => $actor->hasAbility('letters.manage'),
                'can_manage_signatory' => $actor->hasAbility('letters.sign'),
            ],
        ];
    }

    /**
     * @param  array{setting: AcademicSetting|null, school_year: SchoolYear|null, profile: SchoolProfile|null, settings: LetterSettings|null}|null  $context
     * @return array{active_year: bool, profile: bool, header: bool, signatory: bool, signature: bool, template: bool, ready: bool, missing: array<int, string>}
     */
    public function readiness(?array $context = null): array
    {
        $context ??= $this->context();
        $settings = $context['settings'];

        $header = $settings !== null
            && $this->filled($settings->school_name)
            && $this->filled($settings->address_line);

        $signatory = $settings !== null
            && $this->filled($settings->signatory_name)
            && $this->filled($settings->signatory_title);

        $signature = $settings !== null && $this->filled($settings->signature_path);

        $missing = [];

        if ($context['school_year'] === null) {
            $missing[] = 'active_school_year';
        }

        if ($context['profile'] === null) {
            $missing[] = 'school_profile';
        }

        if (! $header) {
            $missing[] = 'header';
        }

        if (! $signatory) {
            $missing[] = 'signatory';
        }

        if (! $signature) {
            $missing[] = 'signature';
        }

        return [
            'active_year' => $context['school_year'] !== null,
            'profile' => $context['profile'] !== null,
            'header' => $header,
            'signatory' => $signatory,
            'signature' => $signature,
            'template' => true,
            'ready' => $missing === [],
            'missing' => $missing,
        ];
    }

    /**
     * Validasi readiness untuk submit/issue/preview surat bertipe.
     *
     * @return array{setting: AcademicSetting|null, school_year: SchoolYear|null, profile: SchoolProfile|null, settings: LetterSettings|null}
     */
    public function assertReady(): array
    {
        $this->templates->assertValid();

        $context = $this->context();
        $readiness = $this->readiness($context);

        if (! $readiness['ready']) {
            abort(response()->json([
                'message' => 'Letter settings are incomplete. Complete the active school year, profile, header, signatory, and signature first.',
                'errors' => [
                    'letter_settings' => ['Letter settings are incomplete for typed letters.'],
                ],
                'missing' => $readiness['missing'],
            ], 422));
        }

        return $context;
    }

    public function activeSchoolName(): ?string
    {
        $name = $this->context()['profile']?->educationSchool?->name;

        if ($this->filled($name)) {
            return trim((string) $name);
        }

        $fallback = SchoolProfile::query()
            ->with('educationSchool:id,npsn,name')
            ->orderBy('id')
            ->first()
            ?->educationSchool
            ?->name;

        return $this->filled($fallback) ? trim((string) $fallback) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateHeader(array $data, int $expectedVersion, User $actor): array
    {
        $context = $this->requireContext();
        $profileId = (int) $context['profile']->id;

        $logoFile = $data['logo'] ?? null;
        $logoFile = $logoFile instanceof UploadedFile ? $logoFile : null;
        $removeLogo = (bool) ($data['remove_logo'] ?? false);

        $stored = $logoFile !== null
            ? $this->assets->store($logoFile, 'logo', $profileId)
            : null;

        $oldLogoPath = null;

        try {
            DB::transaction(function () use (
                $data, $expectedVersion, $actor, $profileId, $stored, $removeLogo, &$oldLogoPath
            ) {
                $settings = LetterSettings::query()
                    ->where('school_profile_id', $profileId)
                    ->lockForUpdate()
                    ->first();

                $this->assertSettingsVersion($settings, $expectedVersion);

                if ($settings === null) {
                    $settings = new LetterSettings([
                        'school_profile_id' => $profileId,
                        'version' => 1,
                    ]);
                }

                $fill = [];

                foreach (['government_line', 'school_name', 'address_line', 'contact_line', 'place_of_issue'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $fill[$field] = $this->nullableTrimmed($data[$field]);
                    }
                }

                if ($stored !== null) {
                    $oldLogoPath = $settings->logo_path;
                    $fill['logo_path'] = $stored['path'];
                    $fill['logo_mime'] = $stored['mime'];
                    $fill['logo_size_bytes'] = $stored['size_bytes'];
                    $fill['logo_sha256'] = $stored['sha256'];
                } elseif ($removeLogo) {
                    $oldLogoPath = $settings->logo_path;
                    $fill['logo_path'] = null;
                    $fill['logo_mime'] = null;
                    $fill['logo_size_bytes'] = null;
                    $fill['logo_sha256'] = null;
                }

                $settings->fill($fill);

                if ($settings->exists && $settings->getDirty() === []) {
                    return;
                }

                if ($settings->exists) {
                    $settings->version = $settings->version + 1;
                }

                $settings->updated_by = $actor->id;
                $settings->save();
            });
        } catch (\Throwable $exception) {
            if ($stored !== null) {
                $this->assets->discard($stored['path']);
            }

            throw $exception;
        }

        if ($stored !== null && $oldLogoPath !== null && $oldLogoPath !== $stored['path']) {
            $this->assets->discard($oldLogoPath);
        }

        return $this->show($actor);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateSignatory(array $data, int $expectedVersion, User $actor): array
    {
        $context = $this->requireContext();
        $profileId = (int) $context['profile']->id;

        $signatureFile = $data['signature'] ?? null;
        $signatureFile = $signatureFile instanceof UploadedFile ? $signatureFile : null;

        $stampFile = $data['stamp'] ?? null;
        $stampFile = $stampFile instanceof UploadedFile ? $stampFile : null;

        $removeStamp = (bool) ($data['remove_stamp'] ?? false);

        $storedSignature = $signatureFile !== null
            ? $this->assets->store($signatureFile, 'signature', $profileId)
            : null;

        $storedStamp = $stampFile !== null
            ? $this->assets->store($stampFile, 'stamp', $profileId)
            : null;

        $oldSignaturePath = null;
        $oldStampPath = null;

        try {
            DB::transaction(function () use (
                $data,
                $expectedVersion,
                $actor,
                $profileId,
                $storedSignature,
                $storedStamp,
                $removeStamp,
                &$oldSignaturePath,
                &$oldStampPath
            ) {
                $settings = LetterSettings::query()
                    ->where('school_profile_id', $profileId)
                    ->lockForUpdate()
                    ->first();

                $this->assertSettingsVersion($settings, $expectedVersion);

                if ($settings === null) {
                    $settings = new LetterSettings([
                        'school_profile_id' => $profileId,
                        'version' => 1,
                    ]);
                }

                $fill = [];

                foreach ([
                    'signatory_name',
                    'signatory_title',
                    'signatory_identifier_label',
                    'signatory_identifier',
                ] as $field) {
                    if (array_key_exists($field, $data)) {
                        $fill[$field] = $this->nullableTrimmed($data[$field]);
                    }
                }

                if ($storedSignature !== null) {
                    $oldSignaturePath = $settings->signature_path;
                    $fill['signature_path'] = $storedSignature['path'];
                    $fill['signature_mime'] = $storedSignature['mime'];
                    $fill['signature_size_bytes'] = $storedSignature['size_bytes'];
                    $fill['signature_sha256'] = $storedSignature['sha256'];
                }

                if ($storedStamp !== null) {
                    $oldStampPath = $settings->stamp_path;
                    $fill['stamp_path'] = $storedStamp['path'];
                    $fill['stamp_mime'] = $storedStamp['mime'];
                    $fill['stamp_size_bytes'] = $storedStamp['size_bytes'];
                    $fill['stamp_sha256'] = $storedStamp['sha256'];
                } elseif ($removeStamp) {
                    $oldStampPath = $settings->stamp_path;
                    $fill['stamp_path'] = null;
                    $fill['stamp_mime'] = null;
                    $fill['stamp_size_bytes'] = null;
                    $fill['stamp_sha256'] = null;
                }

                $settings->fill($fill);

                if (! $this->filled($settings->signature_path)) {
                    throw ValidationException::withMessages([
                        'signature' => ['A signature image is required before signatory settings can be saved.'],
                    ]);
                }

                if ($settings->exists && $settings->getDirty() === []) {
                    return;
                }

                if ($settings->exists) {
                    $settings->version = $settings->version + 1;
                }

                $settings->updated_by = $actor->id;
                $settings->save();
            });
        } catch (\Throwable $exception) {
            if ($storedSignature !== null) {
                $this->assets->discard($storedSignature['path']);
            }

            if ($storedStamp !== null) {
                $this->assets->discard($storedStamp['path']);
            }

            throw $exception;
        }

        if ($storedSignature !== null && $oldSignaturePath !== null && $oldSignaturePath !== $storedSignature['path']) {
            $this->assets->discard($oldSignaturePath);
        }

        if ($storedStamp !== null && $oldStampPath !== null && $oldStampPath !== $storedStamp['path']) {
            $this->assets->discard($oldStampPath);
        }

        return $this->show($actor);
    }

    /**
     * @return array{setting: AcademicSetting|null, school_year: SchoolYear|null, profile: SchoolProfile|null, settings: LetterSettings|null}
     */
    private function requireContext(): array
    {
        $context = $this->context();
        $missing = [];

        if ($context['school_year'] === null) {
            $missing[] = 'active_school_year';
        }

        if ($context['profile'] === null) {
            $missing[] = 'school_profile';
        }

        if ($missing !== []) {
            abort(response()->json([
                'message' => 'An active school year and school profile are required before letter settings can be saved.',
                'errors' => [
                    'letter_settings' => ['An active school year and school profile are required.'],
                ],
                'missing' => $missing,
            ], 422));
        }

        return $context;
    }

    private function assertSettingsVersion(?LetterSettings $settings, int $expectedVersion): void
    {
        $current = $settings?->version ?? 0;

        if ($current !== $expectedVersion) {
            abort(response()->json([
                'message' => 'Conflict: These letter settings were changed by another request. Reload the latest version and try again.',
                'errors' => [
                    'expected_version' => ['These letter settings were changed by another request.'],
                ],
                'current_versions' => [
                    'settings_version' => $current,
                ],
            ], 409));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSettings(LetterSettings $settings): array
    {
        return [
            'version' => $settings->version,
            'government_line' => $settings->government_line,
            'school_name' => $settings->school_name,
            'address_line' => $settings->address_line,
            'contact_line' => $settings->contact_line,
            'place_of_issue' => $settings->place_of_issue,
            'signatory_name' => $settings->signatory_name,
            'signatory_title' => $settings->signatory_title,
            'signatory_identifier_label' => $settings->signatory_identifier_label,
            'signatory_identifier' => $settings->signatory_identifier,
            'logo_available' => $this->filled($settings->logo_path),
            'signature_available' => $this->filled($settings->signature_path),
            'stamp_available' => $this->filled($settings->stamp_path),
            'updated_at' => $settings->updated_at?->toIso8601String(),
            'updated_by' => $settings->updated_by ? [
                'id' => $settings->updated_by,
                'name' => $settings->updatedBy?->name,
            ] : null,
        ];
    }

    /**
     * @return array{school_name: string|null, address_line: string|null, signatory_name: string|null, signatory_title: string, contact_line: string|null}
     */
    private function suggestions(?SchoolProfile $profile): array
    {
        $contact = $profile?->email ?: $profile?->website;

        return [
            'school_name' => $this->nullableTrimmed($profile?->educationSchool?->name),
            'address_line' => $this->nullableTrimmed($profile?->educationSchool?->address),
            'signatory_name' => $this->nullableTrimmed($profile?->principal?->name),
            'signatory_title' => 'Kepala Sekolah',
            'contact_line' => $this->nullableTrimmed($contact),
        ];
    }

    private function filled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
