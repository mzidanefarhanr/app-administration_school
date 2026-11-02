<?php

namespace App\Services\Letters;

use App\Models\Employee;
use App\Models\Letter;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\StudentRombel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Resolusi subjek dan validasi field surat bertipe (Fase 07c, DEC-34).
 *
 * Snapshot hanya memuat identitas yang tercetak pada dokumen; NIK, orang tua,
 * alamat, dan kontak pribadi tidak pernah diambil.
 */
class LetterSubjectService
{
    public function __construct(private readonly LetterTemplateRegistry $templates) {}

    /**
     * @return array{type: string, snapshot: array<string, mixed>, summary: array<string, mixed>, fields: array<string, mixed>}
     */
    public function resolveForLetter(Letter $letter, ?SchoolYear $activeYear): array
    {
        $letterType = (string) $letter->letter_type;

        if (! $this->templates->isKnown($letterType)) {
            $this->invalid('letter_type', 'The letter type is not available.');
        }

        $definition = $this->templates->definition($letterType);

        if ((string) $letter->document_subject_type !== $definition['subject_type']) {
            $this->invalid('document_subject_id', 'The letter subject is not valid for this letter type.');
        }

        $subjectId = (int) ($letter->document_subject_id ?? 0);

        if ($subjectId < 1) {
            $this->invalid('document_subject_id', 'A subject is required for this letter type.');
        }

        $subject = $this->resolveSubject($definition['subject_type'], $subjectId, $activeYear);
        $fields = $this->validateFields($letterType, $letter->document_fields ?? []);

        return $subject + ['fields' => $fields];
    }

    /**
     * @return array{type: string, snapshot: array<string, mixed>, summary: array<string, mixed>}
     */
    public function resolveSubject(string $subjectType, int $subjectId, ?SchoolYear $activeYear): array
    {
        return match ($subjectType) {
            'student' => $this->resolveActiveStudent($subjectId, $activeYear),
            'student_mutation' => $this->resolveMutation($subjectId),
            'employee' => $this->resolveEmployee($subjectId),
            default => $this->invalid('document_subject_id', 'The letter subject type is not supported.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function validateFields(string $letterType, mixed $fields): array
    {
        if ($fields === null) {
            $fields = [];
        }

        if (! is_array($fields)) {
            $this->invalid('document_fields', 'The document fields must be an array.');
        }

        $allowed = match ($letterType) {
            'student_active' => ['purpose'],
            'student_mutation' => [],
            'employee_duty' => ['duty_description', 'start_date', 'end_date', 'location'],
            default => $this->invalid('letter_type', 'The letter type is not available.'),
        };

        $unknown = array_diff(array_keys($fields), $allowed);

        if ($unknown !== []) {
            $key = (string) reset($unknown);
            $this->invalid("document_fields.{$key}", 'This field is not allowed for the selected letter type.');
        }

        if ($letterType === 'student_mutation') {
            return [];
        }

        if ($letterType === 'student_active') {
            $validator = Validator::make(['document_fields' => $fields], [
                'document_fields.purpose' => [
                    'required',
                    'string',
                    'min:'.(int) config('letters.typed_fields.purpose_min_length'),
                    'max:'.(int) config('letters.typed_fields.purpose_max_length'),
                ],
            ], [
                'document_fields.purpose.required' => 'The purpose is required.',
                'document_fields.purpose.min' => 'The purpose must be at least 5 characters.',
                'document_fields.purpose.max' => 'The purpose may not exceed 500 characters.',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            return [
                'purpose' => trim((string) $fields['purpose']),
            ];
        }

        $validator = Validator::make(['document_fields' => $fields], [
            'document_fields.duty_description' => [
                'required',
                'string',
                'min:'.(int) config('letters.typed_fields.duty_description_min_length'),
                'max:'.(int) config('letters.typed_fields.duty_description_max_length'),
            ],
            'document_fields.start_date' => ['required', 'date_format:Y-m-d'],
            'document_fields.end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:document_fields.start_date'],
            'document_fields.location' => [
                'required',
                'string',
                'min:'.(int) config('letters.typed_fields.location_min_length'),
                'max:'.(int) config('letters.typed_fields.location_max_length'),
            ],
        ], [
            'document_fields.duty_description.required' => 'The duty description is required.',
            'document_fields.duty_description.min' => 'The duty description must be at least 10 characters.',
            'document_fields.duty_description.max' => 'The duty description may not exceed 2000 characters.',
            'document_fields.start_date.required' => 'The start date is required.',
            'document_fields.start_date.date_format' => 'The start date must use the YYYY-MM-DD format.',
            'document_fields.end_date.required' => 'The end date is required.',
            'document_fields.end_date.date_format' => 'The end date must use the YYYY-MM-DD format.',
            'document_fields.end_date.after_or_equal' => 'The end date may not be before the start date.',
            'document_fields.location.required' => 'The location is required.',
            'document_fields.location.min' => 'The location must be at least 5 characters.',
            'document_fields.location.max' => 'The location may not exceed 200 characters.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return [
            'duty_description' => trim((string) $fields['duty_description']),
            'start_date' => (string) $fields['start_date'],
            'end_date' => (string) $fields['end_date'],
            'location' => trim((string) $fields['location']),
        ];
    }

    /**
     * @return array{type: string, id: int, label: string}|null
     */
    public function summaryForLetter(Letter $letter): ?array
    {
        if ($letter->letter_type === null || $letter->document_subject_id === null) {
            return null;
        }

        $id = (int) $letter->document_subject_id;

        return match ((string) $letter->document_subject_type) {
            'student' => $this->studentSummaryForLetter($id),
            'student_mutation' => $this->mutationSummaryForLetter($id),
            'employee' => $this->employeeSummaryForLetter($id),
            default => null,
        };
    }

    /**
     * @return array{type: string, snapshot: array<string, mixed>, summary: array<string, mixed>}
     */
    private function resolveActiveStudent(int $studentId, ?SchoolYear $activeYear): array
    {
        if ($activeYear === null) {
            $this->invalid('document_subject_id', 'An active school year is required before a student can be selected.');
        }

        $student = Student::query()
            ->with('gender:id,name')
            ->find($studentId);

        if ($student === null) {
            $this->invalid('document_subject_id', 'The selected student is not available.');
        }

        $membership = StudentRombel::query()
            ->where('student_id', $student->id)
            ->whereNull('archived_at')
            ->where(fn ($query) => $query->whereNull('student_status_id')->orWhere('student_status_id', 1))
            ->whereNull('latest_student_exit_date')
            ->whereHas('schoolRombel', fn ($query) => $query->where('school_year_id', $activeYear->id))
            ->with('schoolRombel:id,name,school_year_id')
            ->first();

        if ($membership === null) {
            $this->invalid('document_subject_id', 'The selected student does not have an active class membership in the active school year.');
        }

        $snapshot = [
            'name' => $student->name,
            'nis' => $student->nis,
            'nisn' => $student->nisn,
            'birthplace' => $student->birthplace,
            'birthdate' => $this->dateString($student->birthdate),
            'gender' => $student->gender?->name,
            'class' => $membership->schoolRombel?->name,
            'school_year' => $activeYear->name,
        ];

        return [
            'type' => 'student',
            'snapshot' => $snapshot,
            'summary' => [
                'type' => 'student',
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'class' => $membership->schoolRombel?->name,
            ],
        ];
    }

    /**
     * @return array{type: string, snapshot: array<string, mixed>, summary: array<string, mixed>}
     */
    private function resolveMutation(int $mutationId): array
    {
        $mutation = StudentMutation::query()
            ->whereKey($mutationId)
            ->where('type', StudentMutation::TYPE_OUT)
            ->where('status', StudentMutation::STATUS_ACTIVE)
            ->with([
                'student' => fn ($query) => $query->withTrashed()->with('gender:id,name'),
                'school' => fn ($query) => $query->withTrashed(),
                'schoolRombel' => fn ($query) => $query->withTrashed(),
                'schoolYear' => fn ($query) => $query->withTrashed(),
            ])
            ->first();

        if ($mutation === null) {
            $this->invalid('document_subject_id', 'The selected mutation record is not an active mutation-out.');
        }

        $student = $mutation->student;

        if ($student === null || $student->trashed()) {
            $this->invalid('document_subject_id', 'The student for the selected mutation is not available.');
        }

        $destination = $mutation->school?->name ?? $mutation->school_npsn;

        $snapshot = [
            'name' => $student->name,
            'nis' => $student->nis,
            'nisn' => $student->nisn,
            'birthplace' => $student->birthplace,
            'birthdate' => $this->dateString($student->birthdate),
            'gender' => $student->gender?->name,
            'class' => $mutation->schoolRombel?->name,
            'school_year' => $mutation->schoolYear?->name,
            'destination_school' => $destination,
            'destination_npsn' => $mutation->school_npsn,
            'effective_date' => $this->dateString($mutation->effective_date),
            'reason' => $mutation->reason,
        ];

        return [
            'type' => 'student_mutation',
            'snapshot' => $snapshot,
            'summary' => [
                'type' => 'student_mutation',
                'id' => $mutation->id,
                'student_name' => $student->name,
                'effective_date' => $this->dateString($mutation->effective_date),
                'destination_school' => $destination,
            ],
        ];
    }

    /**
     * @return array{type: string, snapshot: array<string, mixed>, summary: array<string, mixed>}
     */
    private function resolveEmployee(int $employeeId): array
    {
        $employee = Employee::query()
            ->with(['employeeType:id,name', 'employeeStatus:id,name'])
            ->find($employeeId);

        if ($employee === null) {
            $this->invalid('document_subject_id', 'The selected employee is not available.');
        }

        if (! $this->employeeIsActive($employee)) {
            $this->invalid('document_subject_id', 'The selected employee is no longer active.');
        }

        $snapshot = [
            'name' => $employee->name,
            'nuptk' => $employee->nuptk,
            'type' => $employee->employeeType?->name,
            'status' => $employee->employeeStatus?->name,
        ];

        return [
            'type' => 'employee',
            'snapshot' => $snapshot,
            'summary' => [
                'type' => 'employee',
                'id' => $employee->id,
                'name' => $employee->name,
                'nuptk' => $employee->nuptk,
            ],
        ];
    }

    /**
     * @return array{type: string, id: int, label: string}|null
     */
    private function studentSummaryForLetter(int $studentId): ?array
    {
        $student = Student::withTrashed()->find($studentId);

        if ($student === null) {
            return null;
        }

        $suffix = $student->nis ? ' — NIS '.$student->nis : '';

        return [
            'type' => 'student',
            'id' => $student->id,
            'label' => trim($student->name.$suffix),
        ];
    }

    /**
     * @return array{type: string, id: int, label: string}|null
     */
    private function mutationSummaryForLetter(int $mutationId): ?array
    {
        $mutation = StudentMutation::query()
            ->with([
                'student' => fn ($query) => $query->withTrashed(),
                'school' => fn ($query) => $query->withTrashed(),
            ])
            ->find($mutationId);

        if ($mutation === null) {
            return null;
        }

        $parts = array_filter([
            $mutation->student?->name,
            $mutation->school?->name ?? $mutation->school_npsn,
            $this->dateString($mutation->effective_date),
        ]);

        return [
            'type' => 'student_mutation',
            'id' => $mutation->id,
            'label' => implode(' → ', $parts),
        ];
    }

    /**
     * @return array{type: string, id: int, label: string}|null
     */
    private function employeeSummaryForLetter(int $employeeId): ?array
    {
        $employee = Employee::withTrashed()->find($employeeId);

        if ($employee === null) {
            return null;
        }

        $suffix = $employee->nuptk ? ' — NUPTK '.$employee->nuptk : '';

        return [
            'type' => 'employee',
            'id' => $employee->id,
            'label' => trim($employee->name.$suffix),
        ];
    }

    private function employeeIsActive(Employee $employee): bool
    {
        if ($employee->exit_date === null || $employee->exit_date === '') {
            return true;
        }

        try {
            return Carbon::parse($employee->exit_date, 'Asia/Jakarta')
                ->startOfDay()
                ->gte(Carbon::now('Asia/Jakarta')->startOfDay());
        } catch (\Throwable) {
            return false;
        }
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return $value instanceof \DateTimeInterface
                ? Carbon::instance($value)->format('Y-m-d')
                : Carbon::parse((string) $value, 'Asia/Jakarta')->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }
}
