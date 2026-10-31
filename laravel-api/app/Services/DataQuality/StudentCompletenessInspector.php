<?php

namespace App\Services\DataQuality;

use App\Models\DataQualityReview;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Support\StudentDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Pemeriksaan kelengkapan siswa berbasis penempatan tahun ajaran (DEC-28).
 *
 * Denominator adalah siswa placed non-archived. Siswa unplaced tanpa NIK
 * dilaporkan sebagai blocked dan tidak menurunkan persentase.
 */
final class StudentCompletenessInspector
{
    public const DOMAIN = 'students';

    public const SUBJECT_TYPE = 'student';

    private CarbonImmutable $asOf;

    public function __construct(?CarbonImmutable $asOf = null)
    {
        $this->asOf = ($asOf ?? CarbonImmutable::now('Asia/Jakarta'))->setTimezone('Asia/Jakarta');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $schoolYearId): array
    {
        return $this->analyze($schoolYearId)['summary'];
    }

    public function hasIssue(int $schoolYearId, string $issueKey): bool
    {
        foreach ($this->analyze($schoolYearId)['issues'] as $issue) {
            if ($issue['issue_key'] === $issueKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function issues(int $schoolYearId, array $filters): LengthAwarePaginator
    {
        $issues = $this->analyze($schoolYearId)['issues'];

        $type = $filters['type'] ?? null;
        $field = $filters['field'] ?? null;
        $status = $filters['status'] ?? 'open';
        $search = trim((string) ($filters['search'] ?? ''));

        $issues = array_values(array_filter($issues, function (array $issue) use ($type, $field, $status, $search): bool {
            if ($type !== null && $type !== '' && $issue['type'] !== $type) {
                return false;
            }

            if ($field !== null && $field !== '' && $issue['field'] !== $field) {
                return false;
            }

            if ($status !== 'all' && $issue['review']['status'] !== $status) {
                return false;
            }

            if ($search !== '') {
                $haystacks = [
                    $issue['subject']['name'] ?? '',
                    $issue['subject']['nis'] ?? '',
                    $issue['subject']['nisn'] ?? '',
                ];

                $matched = false;
                foreach ($haystacks as $value) {
                    if (stripos((string) $value, $search) !== false) {
                        $matched = true;
                        break;
                    }
                }

                if (! $matched) {
                    return false;
                }
            }

            return true;
        }));

        $this->sortIssues($issues);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? config('data_quality.default_per_page', 20));
        $total = count($issues);
        $items = array_slice($issues, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator($items, $total, $perPage, $page);
    }

    /**
     * @return array{summary: array<string, mixed>, issues: array<int, array<string, mixed>>}
     */
    private function analyze(int $schoolYearId): array
    {
        $records = $this->loadRecords($schoolYearId);
        $schoolYear = SchoolYear::query()->findOrFail($schoolYearId);

        $issues = [];
        $fieldStats = [];
        $placed = 0;
        $completeRecords = 0;
        $incompleteRecords = 0;
        $notEvaluated = 0;

        foreach (array_keys(StudentCompletenessProfile::REQUIRED_FIELDS) as $field) {
            $fieldStats[$field] = [
                'field' => $field,
                'label' => StudentCompletenessProfile::label($field),
                'denominator' => 0,
                'missing' => 0,
                'invalid' => 0,
                'complete' => 0,
                'completeness_percent' => null,
            ];
        }

        $fieldStats['nik']['blocked'] = ['missing' => 0, 'invalid' => 0];

        foreach ($records as $record) {
            if ($record['placement'] === 'unplaced') {
                $evaluation = $this->evaluateUnplacedNik($record);

                if ($evaluation === null) {
                    $notEvaluated++;

                    continue;
                }

                $issues[] = $this->issue($record, 'blocked', 'nik', $evaluation['message']);
                $fieldStats['nik']['blocked'][$evaluation['type']]++;

                continue;
            }

            $placed++;
            $complete = true;

            foreach (array_keys(StudentCompletenessProfile::REQUIRED_FIELDS) as $field) {
                $evaluation = $this->evaluatePlacedField($record, $field);

                if ($evaluation === null) {
                    continue;
                }

                $complete = false;
                $fieldStats[$field][$evaluation['type']]++;
                $issues[] = $this->issue($record, $evaluation['type'], $field, $evaluation['message']);
            }

            if ($complete) {
                $completeRecords++;
            } else {
                $incompleteRecords++;
            }
        }

        foreach ($fieldStats as $field => $stats) {
            $fieldStats[$field]['denominator'] = $placed;
            $fieldStats[$field]['complete'] = max(0, $placed - $stats['missing'] - $stats['invalid']);
            $fieldStats[$field]['completeness_percent'] = $this->percent(
                $fieldStats[$field]['complete'],
                $placed
            );
        }

        $duplicateIssues = $this->duplicateIssues($records);
        $issues = array_merge($issues, $duplicateIssues);
        $this->attachReviews($issues);
        $this->sortIssues($issues);

        $affectedSubjects = [];
        foreach ($duplicateIssues as $issue) {
            $affectedSubjects[$issue['subject']['id']] = true;
            $affectedSubjects[$issue['related']['id']] = true;
        }

        $reviewCounts = ['open' => 0, 'acknowledged' => 0, 'ignored' => 0];
        foreach ($issues as $issue) {
            $status = $issue['review']['status'];
            if (array_key_exists($status, $reviewCounts)) {
                $reviewCounts[$status]++;
            }
        }
        $reviewCounts['unresolved'] = $reviewCounts['open'] + $reviewCounts['acknowledged'];

        $activeStudents = count($records);
        $unplaced = $activeStudents - $placed;

        return [
            'summary' => [
                'profile_version' => StudentCompletenessProfile::VERSION,
                'as_of' => $this->asOf->toIso8601String(),
                'school_year' => [
                    'id' => (int) $schoolYear->id,
                    'name' => $schoolYear->name,
                ],
                'denominator_basis' => 'placed_students',
                'population' => [
                    'active_students' => $activeStudents,
                    'placed' => $placed,
                    'unplaced' => $unplaced,
                    'not_evaluated' => $notEvaluated,
                    'archived_excluded' => Student::onlyTrashed()->count(),
                ],
                'records' => [
                    'complete' => $completeRecords,
                    'incomplete' => $incompleteRecords,
                    'complete_percent' => $this->percent($completeRecords, $placed),
                ],
                'fields' => array_values($fieldStats),
                'duplicates' => [
                    'candidate_pairs' => count($duplicateIssues),
                    'affected_subjects' => count($affectedSubjects),
                ],
                'reviews' => $reviewCounts,
            ],
            'issues' => $issues,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRecords(int $schoolYearId): array
    {
        $records = [];

        Student::query()
            ->whereNull('students.deleted_at')
            ->select([
                'students.id',
                'students.version',
                'students.name',
                'students.nis',
                'students.nisn',
                'students.nik',
                'students.gender_id',
                'students.birthplace',
                'students.birthdate',
                'students.religion_id',
            ])
            ->with('gender:id,name')
            ->chunkById(500, function (Collection $chunk) use (&$records, $schoolYearId): void {
                $memberships = StudentDirectory::membershipsFor($chunk, $schoolYearId);

                foreach ($chunk as $student) {
                    $membership = $memberships[$student->id] ?? null;

                    $records[$student->id] = [
                        'id' => (int) $student->id,
                        'version' => (int) $student->version,
                        'name' => $student->name,
                        'nis' => $student->nis,
                        'nisn' => $student->nisn,
                        'nik' => $student->nik,
                        'gender_id' => $student->gender_id,
                        'gender' => $student->gender?->name,
                        'birthplace' => $student->birthplace,
                        'birthdate' => $student->birthdate,
                        'religion_id' => $student->religion_id,
                        'placement' => $membership ? 'placed' : 'unplaced',
                        'rombel' => $membership?->schoolRombel
                            ? [
                                'id' => (int) $membership->schoolRombel->id,
                                'name' => $membership->schoolRombel->name,
                            ]
                            : null,
                        'school_year_id' => $schoolYearId,
                    ];
                }
            });

        return $records;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{type: string, message: string}|null
     */
    private function evaluatePlacedField(array $record, string $field): ?array
    {
        $value = $record[$field] ?? null;
        $label = StudentCompletenessProfile::label($field);

        if ($this->isBlank($value)) {
            return ['type' => 'missing', 'message' => $label.' is missing for a placed student.'];
        }

        if ($field === 'nik' && ! preg_match('/^\d{16}$/', (string) $value)) {
            return ['type' => 'invalid', 'message' => 'NIK must be exactly 16 digits.'];
        }

        if ($field === 'nisn' && ! preg_match('/^\d{10}$/', (string) $value)) {
            return ['type' => 'invalid', 'message' => 'NISN must be exactly 10 digits.'];
        }

        if ($field === 'birthdate') {
            $message = $this->birthdateInvalidMessage((string) $value);

            if ($message !== null) {
                return ['type' => 'invalid', 'message' => $message];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{type: string, message: string}|null
     */
    private function evaluateUnplacedNik(array $record): ?array
    {
        $value = $record['nik'] ?? null;

        if ($this->isBlank($value)) {
            return [
                'type' => 'missing',
                'message' => 'NIK is missing and the student is not placed in the selected school year.',
            ];
        }

        if (! preg_match('/^\d{16}$/', (string) $value)) {
            return [
                'type' => 'invalid',
                'message' => 'NIK must be exactly 16 digits and the student is not placed in the selected school year.',
            ];
        }

        return null;
    }

    private function birthdateInvalidMessage(string $value): ?string
    {
        try {
            $birthdate = CarbonImmutable::parse($value, 'Asia/Jakarta')->startOfDay();
        } catch (\Throwable) {
            return 'Birthdate must be a valid date.';
        }

        if ($birthdate->greaterThan($this->asOf->startOfDay())) {
            return 'Birthdate cannot be in the future.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function issue(array $record, string $type, string $field, string $message): array
    {
        return [
            'issue_key' => IssueKey::make(
                self::DOMAIN,
                $type,
                $field,
                $record['school_year_id'],
                self::SUBJECT_TYPE,
                $record['id'],
                $record['version']
            ),
            'type' => $type,
            'field' => $field,
            'field_label' => StudentCompletenessProfile::label($field),
            'message' => $message,
            'subject' => $this->subject($record),
            'related' => null,
            'review' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function subject(array $record): array
    {
        return [
            'type' => self::SUBJECT_TYPE,
            'id' => $record['id'],
            'version' => $record['version'],
            'name' => $record['name'],
            'nis' => $record['nis'],
            'nisn' => $record['nisn'],
            'placement' => $record['placement'],
            'gender' => $record['gender'],
            'rombel' => $record['rombel'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function duplicateIssues(array $records): array
    {
        $issues = [];

        $nameBirthdateGroups = [];
        foreach ($records as $record) {
            $name = trim((string) ($record['name'] ?? ''));
            $birthdate = $this->normalizeDateForGrouping($record['birthdate'] ?? null);

            if ($name === '' || $birthdate === null) {
                continue;
            }

            $nameBirthdateGroups[mb_strtolower($name).'|'.$birthdate][] = $record['id'];
        }
        $issues = array_merge($issues, $this->pairsToIssues($nameBirthdateGroups, 'name_birthdate', $records));

        foreach (['nik', 'nis', 'nisn'] as $field) {
            $groups = [];
            foreach ($records as $record) {
                $value = $record[$field] ?? null;

                if ($this->isBlank($value)) {
                    continue;
                }

                $groups[trim((string) $value)][] = $record['id'];
            }

            $issues = array_merge($issues, $this->pairsToIssues($groups, $field, $records));
        }

        return $issues;
    }

    /**
     * @param  array<string, array<int, int>>  $groups
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function pairsToIssues(array $groups, string $field, array $records): array
    {
        $issues = [];

        foreach ($groups as $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            sort($ids);
            $count = count($ids);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $left = $records[$ids[$i]] ?? null;
                    $right = $records[$ids[$j]] ?? null;

                    if ($left === null || $right === null) {
                        continue;
                    }

                    $issues[] = $this->duplicateIssue($left, $right, $field);
                }
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $related
     * @return array<string, mixed>
     */
    private function duplicateIssue(array $subject, array $related, string $field): array
    {
        return [
            'issue_key' => IssueKey::make(
                self::DOMAIN,
                'duplicate',
                $field,
                $subject['school_year_id'],
                self::SUBJECT_TYPE,
                $subject['id'],
                $subject['version'],
                $related['id'],
                $related['version']
            ),
            'type' => 'duplicate',
            'field' => $field,
            'field_label' => StudentCompletenessProfile::label($field),
            'message' => $this->duplicateMessage($field),
            'subject' => $this->subject($subject),
            'related' => $this->subject($related),
            'review' => null,
        ];
    }

    private function duplicateMessage(string $field): string
    {
        return match ($field) {
            'name_birthdate' => 'Possible duplicate: same name and birthdate.',
            'nik' => 'Possible duplicate: same NIK.',
            'nis' => 'Possible duplicate: same NIS.',
            'nisn' => 'Possible duplicate: same NISN.',
            default => 'Possible duplicate record.',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     */
    private function attachReviews(array &$issues): void
    {
        if ($issues === []) {
            return;
        }

        $reviews = [];
        $keys = array_values(array_unique(array_column($issues, 'issue_key')));

        foreach (array_chunk($keys, 500) as $chunk) {
            foreach (DataQualityReview::query()->whereIn('issue_key', $chunk)->get() as $review) {
                $reviews[$review->issue_key] = $review;
            }
        }

        foreach ($issues as &$issue) {
            $review = $reviews[$issue['issue_key']] ?? null;

            $issue['review'] = [
                'status' => $review?->status ?? DataQualityReview::STATUS_OPEN,
                'reason_code' => $review?->reason_code,
                'version' => $review ? (int) $review->version : 0,
                'reviewed_at' => $review?->reviewed_at?->toIso8601String(),
            ];
        }
        unset($issue);
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     */
    private function sortIssues(array &$issues): void
    {
        usort($issues, function (array $a, array $b): int {
            $left = [
                (string) ($a['subject']['name'] ?? ''),
                (string) $a['field'],
                (int) $a['subject']['id'],
                (string) $a['type'],
                (int) ($a['related']['id'] ?? 0),
            ];
            $right = [
                (string) ($b['subject']['name'] ?? ''),
                (string) $b['field'],
                (int) $b['subject']['id'],
                (string) $b['type'],
                (int) ($b['related']['id'] ?? 0),
            ];

            return $left <=> $right;
        });
    }

    private function normalizeDateForGrouping(?string $value): ?string
    {
        if ($this->isBlank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'Asia/Jakarta')->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function percent(int $complete, int $denominator): ?float
    {
        return $denominator === 0 ? null : round($complete / $denominator * 100, 1);
    }
}
