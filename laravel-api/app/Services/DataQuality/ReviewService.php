<?php

namespace App\Services\DataQuality;

use App\Models\DataQualityReview;
use App\Models\Employee;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Upsert status review dengan validasi issue aktif dan optimistic concurrency
 * (DEC-28). Review tidak mengubah data domain maupun persentase.
 */
final class ReviewService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function reviewStudent(array $data, string $routeIssueKey, int $userId): array
    {
        $schoolYearId = (int) $data['school_year_id'];
        $issueKey = $this->issueKey(
            $data,
            StudentCompletenessInspector::DOMAIN,
            StudentCompletenessInspector::SUBJECT_TYPE,
            $schoolYearId
        );

        $this->assertIssueKey($issueKey, $routeIssueKey);
        $this->assertStudentSubjects($data);

        if (! (new StudentCompletenessInspector)->hasIssue($schoolYearId, $issueKey)) {
            $this->conflict('The issue is out of date. Refresh the list.');
        }

        return $this->persist(
            $issueKey,
            $data,
            $userId,
            StudentCompletenessInspector::DOMAIN,
            StudentCompletenessInspector::SUBJECT_TYPE,
            $schoolYearId
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function reviewEmployee(array $data, string $routeIssueKey, int $userId): array
    {
        $issueKey = $this->issueKey(
            $data,
            EmployeeCompletenessInspector::DOMAIN,
            EmployeeCompletenessInspector::SUBJECT_TYPE,
            null
        );

        $this->assertIssueKey($issueKey, $routeIssueKey);
        $this->assertEmployeeSubjects($data);

        if (! (new EmployeeCompletenessInspector)->hasIssue($issueKey)) {
            $this->conflict('The issue is out of date. Refresh the list.');
        }

        return $this->persist(
            $issueKey,
            $data,
            $userId,
            EmployeeCompletenessInspector::DOMAIN,
            EmployeeCompletenessInspector::SUBJECT_TYPE,
            null
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function issueKey(array $data, string $domain, string $subjectType, ?int $schoolYearId): string
    {
        return IssueKey::make(
            $domain,
            (string) $data['issue_type'],
            (string) $data['field'],
            $schoolYearId,
            $subjectType,
            (int) $data['subject_id'],
            (int) $data['subject_version'],
            isset($data['related_subject_id']) ? (int) $data['related_subject_id'] : null,
            isset($data['related_version']) ? (int) $data['related_version'] : null
        );
    }

    private function assertIssueKey(string $computed, string $routeIssueKey): void
    {
        if (! hash_equals($computed, $routeIssueKey)) {
            $this->conflict('The issue key does not match the submitted issue. Refresh the list.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertStudentSubjects(array $data): void
    {
        $student = Student::withTrashed()->find((int) $data['subject_id']);

        if ($student === null || $student->trashed()) {
            $this->notFound('Student not found.');
        }

        if ((int) $student->version !== (int) $data['subject_version']) {
            $this->conflict('The issue is out of date. Refresh the list.', (int) $student->version);
        }

        if (empty($data['related_subject_id'])) {
            return;
        }

        $related = Student::withTrashed()->find((int) $data['related_subject_id']);

        if ($related === null || $related->trashed()) {
            $this->notFound('Related student not found.');
        }

        if ((int) $related->version !== (int) $data['related_version']) {
            $this->conflict('The issue is out of date. Refresh the list.', (int) $related->version);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertEmployeeSubjects(array $data): void
    {
        $employee = Employee::withTrashed()->find((int) $data['subject_id']);

        if ($employee === null || $employee->trashed()) {
            $this->notFound('Employee not found.');
        }

        if ((int) $employee->version !== (int) $data['subject_version']) {
            $this->conflict('The issue is out of date. Refresh the list.', (int) $employee->version);
        }

        if (empty($data['related_subject_id'])) {
            return;
        }

        $related = Employee::withTrashed()->find((int) $data['related_subject_id']);

        if ($related === null || $related->trashed()) {
            $this->notFound('Related employee not found.');
        }

        if ((int) $related->version !== (int) $data['related_version']) {
            $this->conflict('The issue is out of date. Refresh the list.', (int) $related->version);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function persist(
        string $issueKey,
        array $data,
        int $userId,
        string $domain,
        string $subjectType,
        ?int $schoolYearId
    ): array {
        return DB::transaction(function () use ($issueKey, $data, $userId, $domain, $subjectType, $schoolYearId): array {
            $review = DataQualityReview::query()
                ->where('issue_key', $issueKey)
                ->lockForUpdate()
                ->first();

            $currentVersion = $review ? (int) $review->version : 0;
            $expectedVersion = (int) $data['version'];

            if ($currentVersion !== $expectedVersion) {
                $this->conflict(
                    'Conflict: The review has been updated by another user. Please reload the latest data.',
                    $currentVersion
                );
            }

            $status = (string) $data['status'];
            $reasonCode = $status === DataQualityReview::STATUS_IGNORED
                ? ($data['reason_code'] ?? null)
                : null;
            $reviewedAt = CarbonImmutable::now('Asia/Jakarta');

            if ($review === null) {
                $review = new DataQualityReview([
                    'issue_key' => $issueKey,
                    'domain' => $domain,
                    'issue_type' => (string) $data['issue_type'],
                    'field' => (string) $data['field'],
                    'subject_type' => $subjectType,
                    'school_year_id' => $schoolYearId,
                    'subject_id' => (int) $data['subject_id'],
                    'subject_version' => (int) $data['subject_version'],
                    'related_subject_id' => isset($data['related_subject_id']) ? (int) $data['related_subject_id'] : null,
                    'related_version' => isset($data['related_version']) ? (int) $data['related_version'] : null,
                    'status' => $status,
                    'reason_code' => $reasonCode,
                    'version' => 1,
                    'reviewed_by' => $userId,
                    'reviewed_at' => $reviewedAt,
                ]);
            } else {
                $review->fill([
                    'status' => $status,
                    'reason_code' => $reasonCode,
                    'version' => $currentVersion + 1,
                    'reviewed_by' => $userId,
                    'reviewed_at' => $reviewedAt,
                ]);
            }

            $review->save();

            return [
                'status' => $review->status,
                'reason_code' => $review->reason_code,
                'version' => (int) $review->version,
                'reviewed_at' => $review->reviewed_at?->toIso8601String(),
                'reviewed_by' => (int) $review->reviewed_by,
            ];
        });
    }

    private function notFound(string $message): void
    {
        abort(response()->json(['message' => $message], 404));
    }

    private function conflict(string $message, ?int $currentVersion = null): void
    {
        $payload = ['message' => $message];

        if ($currentVersion !== null) {
            $payload['current_version'] = $currentVersion;
        }

        abort(response()->json($payload, 409));
    }
}
