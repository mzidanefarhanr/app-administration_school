<?php

namespace App\Services\DataQuality;

use App\Models\DataQualityReview;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Pemeriksaan kelengkapan pegawai non-archived (DEC-28).
 *
 * Denominator adalah pegawai active (belum keluar pada as_of). Pegawai
 * separated/archived bersifat excluded dan tidak membuat issue.
 */
final class EmployeeCompletenessInspector
{
    public const DOMAIN = 'employees';

    public const SUBJECT_TYPE = 'employee';

    private CarbonImmutable $asOf;

    public function __construct(?CarbonImmutable $asOf = null)
    {
        $this->asOf = ($asOf ?? CarbonImmutable::now('Asia/Jakarta'))->setTimezone('Asia/Jakarta');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return $this->analyze()['summary'];
    }

    public function hasIssue(string $issueKey): bool
    {
        foreach ($this->analyze()['issues'] as $issue) {
            if ($issue['issue_key'] === $issueKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function issues(array $filters): LengthAwarePaginator
    {
        $issues = $this->analyze()['issues'];

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
                    $issue['subject']['nuptk'] ?? '',
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
    private function analyze(): array
    {
        $records = $this->loadRecords();

        $issues = [];
        $fieldStats = [];
        $active = 0;
        $teachers = 0;
        $completeRecords = 0;
        $incompleteRecords = 0;

        foreach (array_keys(EmployeeCompletenessProfile::REQUIRED_FIELDS) as $field) {
            $fieldStats[$field] = [
                'field' => $field,
                'label' => EmployeeCompletenessProfile::label($field),
                'denominator' => 0,
                'missing' => 0,
                'invalid' => 0,
                'complete' => 0,
                'completeness_percent' => null,
            ];
        }

        foreach (array_keys(EmployeeCompletenessProfile::TEACHER_REQUIRED_FIELDS) as $field) {
            $fieldStats[$field] = [
                'field' => $field,
                'label' => EmployeeCompletenessProfile::label($field),
                'denominator' => 0,
                'missing' => 0,
                'invalid' => 0,
                'complete' => 0,
                'completeness_percent' => null,
            ];
        }

        foreach ($records as $record) {
            if (! $record['is_active']) {
                continue;
            }

            $active++;
            $complete = true;

            foreach (array_keys(EmployeeCompletenessProfile::REQUIRED_FIELDS) as $field) {
                $evaluation = $this->evaluateActiveField($record, $field);

                if ($evaluation === null) {
                    continue;
                }

                $complete = false;
                $fieldStats[$field][$evaluation['type']]++;
                $issues[] = $this->issue($record, $evaluation['type'], $field, $evaluation['message']);
            }

            if ($record['is_teacher']) {
                $teachers++;

                if ($this->isBlank($record['nuptk'])) {
                    $complete = false;
                    $fieldStats['nuptk']['missing']++;
                    $issues[] = $this->issue($record, 'missing', 'nuptk', 'NUPTK is missing for a teacher.');
                }

                if ($this->isBlank($record['subject_id'])) {
                    $complete = false;
                    $fieldStats['subject_id']['missing']++;
                    $issues[] = $this->issue($record, 'missing', 'subject_id', 'Subject is missing for a teacher.');
                }
            } elseif (! $this->isBlank($record['subject_id'])) {
                $complete = false;
                $fieldStats['subject_id']['invalid']++;
                $issues[] = $this->issue($record, 'invalid', 'subject_id', 'Subject must be empty for a non-teaching employee.');
            }

            if ($complete) {
                $completeRecords++;
            } else {
                $incompleteRecords++;
            }
        }

        foreach (array_keys(EmployeeCompletenessProfile::REQUIRED_FIELDS) as $field) {
            $fieldStats[$field]['denominator'] = $active;
            $fieldStats[$field]['complete'] = max(0, $active - $fieldStats[$field]['missing'] - $fieldStats[$field]['invalid']);
            $fieldStats[$field]['completeness_percent'] = $this->percent($fieldStats[$field]['complete'], $active);
        }

        $fieldStats['nuptk']['denominator'] = $teachers;
        $fieldStats['nuptk']['complete'] = max(0, $teachers - $fieldStats['nuptk']['missing']);
        $fieldStats['nuptk']['completeness_percent'] = $this->percent($fieldStats['nuptk']['complete'], $teachers);

        $fieldStats['subject_id']['denominator'] = $active;
        $fieldStats['subject_id']['complete'] = max(0, $active - $fieldStats['subject_id']['missing'] - $fieldStats['subject_id']['invalid']);
        $fieldStats['subject_id']['completeness_percent'] = $this->percent($fieldStats['subject_id']['complete'], $active);

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

        return [
            'summary' => [
                'profile_version' => EmployeeCompletenessProfile::VERSION,
                'as_of' => $this->asOf->toIso8601String(),
                'school_year' => null,
                'denominator_basis' => 'active_employees',
                'population' => [
                    'active' => $active,
                    'separated' => count($records) - $active,
                    'archived_excluded' => Employee::onlyTrashed()->count(),
                ],
                'records' => [
                    'complete' => $completeRecords,
                    'incomplete' => $incompleteRecords,
                    'complete_percent' => $this->percent($completeRecords, $active),
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
    private function loadRecords(): array
    {
        $records = [];

        Employee::query()
            ->whereNull('employees.deleted_at')
            ->select([
                'employees.id',
                'employees.version',
                'employees.name',
                'employees.nik',
                'employees.nuptk',
                'employees.employee_type_id',
                'employees.employee_status_id',
                'employees.employment_id',
                'employees.subject_id',
                'employees.gender_id',
                'employees.religion_id',
                'employees.marital_status_id',
                'employees.district_id',
                'employees.tmt_employee',
                'employees.exit_date',
                'employees.date_of_birth',
            ])
            ->with([
                'employeeType:id,name',
                'employeeStatus:id,name',
                'employment:id,name',
                'subject:id,name',
            ])
            ->chunkById(500, function (Collection $chunk) use (&$records): void {
                foreach ($chunk as $employee) {
                    $typeName = $employee->employeeType?->name;

                    $records[$employee->id] = [
                        'id' => (int) $employee->id,
                        'version' => (int) $employee->version,
                        'name' => $employee->name,
                        'nik' => $employee->nik,
                        'nuptk' => $employee->nuptk,
                        'employee_type_id' => $employee->employee_type_id,
                        'employee_status_id' => $employee->employee_status_id,
                        'employment_id' => $employee->employment_id,
                        'subject_id' => $employee->subject_id,
                        'gender_id' => $employee->gender_id,
                        'religion_id' => $employee->religion_id,
                        'marital_status_id' => $employee->marital_status_id,
                        'district_id' => $employee->district_id,
                        'tmt_employee' => $employee->tmt_employee,
                        'date_of_birth' => $employee->date_of_birth,
                        'type_name' => $typeName,
                        'is_teacher' => EmployeeCompletenessProfile::isTeacher($typeName),
                        'is_active' => $this->isActive($employee->exit_date),
                        'employee_type' => EmployeeCompletenessProfile::englishTypeLabel($typeName),
                        'employee_status' => $employee->employeeStatus?->name,
                        'employment' => $employee->employment?->name,
                        'subject' => $employee->subject?->name,
                    ];
                }
            });

        return $records;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{type: string, message: string}|null
     */
    private function evaluateActiveField(array $record, string $field): ?array
    {
        $value = $record[$field] ?? null;
        $label = EmployeeCompletenessProfile::label($field);

        if ($this->isBlank($value)) {
            return ['type' => 'missing', 'message' => $label.' is missing for an active employee.'];
        }

        if ($field === 'nik' && ! preg_match('/^\d{16}$/', (string) $value)) {
            return ['type' => 'invalid', 'message' => 'NIK must be exactly 16 digits.'];
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
                null,
                self::SUBJECT_TYPE,
                $record['id'],
                $record['version']
            ),
            'type' => $type,
            'field' => $field,
            'field_label' => EmployeeCompletenessProfile::label($field),
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
            'nuptk' => $record['nuptk'],
            'employee_type' => $record['employee_type'],
            'employee_status' => $record['employee_status'],
            'employment' => $record['employment'],
            'subject' => $record['subject'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function duplicateIssues(array $records): array
    {
        $issues = [];
        $activeRecords = array_filter($records, fn (array $record): bool => $record['is_active']);

        $nuptkGroups = [];
        foreach ($activeRecords as $record) {
            $value = $record['nuptk'] ?? null;

            if ($this->isBlank($value)) {
                continue;
            }

            $nuptkGroups[trim((string) $value)][] = $record['id'];
        }
        $issues = array_merge($issues, $this->pairsToIssues($nuptkGroups, 'nuptk', $records));

        $nameBirthdateGroups = [];
        foreach ($activeRecords as $record) {
            $name = trim((string) ($record['name'] ?? ''));
            $birthdate = $this->normalizeDateForGrouping($record['date_of_birth'] ?? null);

            if ($name === '' || $birthdate === null) {
                continue;
            }

            $nameBirthdateGroups[mb_strtolower($name).'|'.$birthdate][] = $record['id'];
        }
        $issues = array_merge($issues, $this->pairsToIssues($nameBirthdateGroups, 'name_birthdate', $records));

        $nikGroups = [];
        foreach ($records as $record) {
            $value = $record['nik'] ?? null;

            if ($this->isBlank($value)) {
                continue;
            }

            $nikGroups[trim((string) $value)][] = $record['id'];
        }
        $issues = array_merge($issues, $this->pairsToIssues($nikGroups, 'nik', $records));

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
                null,
                self::SUBJECT_TYPE,
                $subject['id'],
                $subject['version'],
                $related['id'],
                $related['version']
            ),
            'type' => 'duplicate',
            'field' => $field,
            'field_label' => EmployeeCompletenessProfile::label($field),
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
            'nuptk' => 'Possible duplicate: same NUPTK.',
            'nik' => 'Possible duplicate: same NIK.',
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

    private function isActive(?string $exitDate): bool
    {
        if ($this->isBlank($exitDate)) {
            return true;
        }

        try {
            $exit = CarbonImmutable::parse($exitDate, 'Asia/Jakarta')->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        return $exit->greaterThanOrEqualTo($this->asOf->startOfDay());
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
