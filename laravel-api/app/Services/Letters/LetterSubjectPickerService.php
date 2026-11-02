<?php

namespace App\Services\Letters;

use App\Models\Employee;
use App\Models\Student;
use App\Models\StudentMutation;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Subject picker untuk surat bertipe (Fase 07c, DEC-34).
 *
 * Hanya identitas minimum yang dibutuhkan form; NIK, alamat, kontak, dan
 * alasan mutasi tidak pernah dikembalikan.
 */
class LetterSubjectPickerService
{
    public function __construct(private readonly LetterSettingsService $settings) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function students(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? config('letters.subject_picker.default_per_page'));
        $schoolYear = $this->settings->context()['school_year'];

        if ($schoolYear === null) {
            return $this->emptyPage($search, $perPage);
        }

        $query = Student::query()
            ->select(['id', 'name', 'nis', 'nisn'])
            ->whereHas('studentRombels', fn (Builder $membership) => $this->activeMembership($membership, (int) $schoolYear->id))
            ->with([
                'studentRombels' => fn (Builder $membership) => $this->activeMembership($membership, (int) $schoolYear->id)
                    ->with('schoolRombel:id,name'),
            ])
            ->orderBy('name')
            ->orderBy('id');

        if ($search !== '') {
            $pattern = $this->likePattern($search);

            $query->where(function (Builder $sub) use ($pattern) {
                $sub->where('name', 'like', $pattern)
                    ->orWhere('nis', 'like', $pattern)
                    ->orWhere('nisn', 'like', $pattern);
            });
        }

        $paginator = $query->paginate($perPage);

        return [
            'items' => collect($paginator->items())
                ->map(fn (Student $student) => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'nis' => $student->nis,
                    'nisn' => $student->nisn,
                    'class' => $student->studentRombels->first()?->schoolRombel?->name,
                ])
                ->values()
                ->all(),
            'meta' => $this->meta($paginator),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function mutations(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? config('letters.subject_picker.default_per_page'));

        $query = StudentMutation::query()
            ->where('type', StudentMutation::TYPE_OUT)
            ->where('status', StudentMutation::STATUS_ACTIVE)
            ->whereHas('student', fn (Builder $student) => $student->whereNull('students.deleted_at'))
            ->with([
                'student' => fn (Builder $student) => $student->withTrashed()->select(['id', 'name', 'nis', 'deleted_at']),
                'school' => fn (Builder $school) => $school->withTrashed()->select(['id', 'npsn', 'name', 'deleted_at']),
            ])
            ->orderByDesc('effective_date')
            ->orderByDesc('id');

        if ($search !== '') {
            $pattern = $this->likePattern($search);

            $query->where(function (Builder $sub) use ($pattern) {
                $sub->whereHas('student', fn (Builder $student) => $student->where('name', 'like', $pattern))
                    ->orWhereHas('school', fn (Builder $school) => $school
                        ->where('name', 'like', $pattern)
                        ->orWhere('npsn', 'like', $pattern))
                    ->orWhere('school_npsn', 'like', $pattern);
            });
        }

        $paginator = $query->paginate($perPage);

        return [
            'items' => collect($paginator->items())
                ->map(fn (StudentMutation $mutation) => [
                    'id' => $mutation->id,
                    'student_name' => $mutation->student?->name,
                    'student_nis' => $mutation->student?->nis,
                    'effective_date' => $mutation->effective_date?->format('Y-m-d'),
                    'destination_school' => $mutation->school?->name ?? $mutation->school_npsn,
                    'destination_npsn' => $mutation->school_npsn,
                ])
                ->values()
                ->all(),
            'meta' => $this->meta($paginator),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function employees(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? config('letters.subject_picker.default_per_page'));
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $query = Employee::query()
            ->with('employeeType:id,name')
            ->where(fn (Builder $sub) => $sub
                ->whereNull('exit_date')
                ->orWhereDate('exit_date', '>=', $today))
            ->orderBy('name')
            ->orderBy('id');

        if ($search !== '') {
            $pattern = $this->likePattern($search);

            $query->where(function (Builder $sub) use ($pattern) {
                $sub->where('name', 'like', $pattern)
                    ->orWhere('nuptk', 'like', $pattern);
            });
        }

        $paginator = $query->paginate($perPage);

        return [
            'items' => collect($paginator->items())
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'nuptk' => $employee->nuptk,
                    'employee_type' => $employee->employeeType?->name,
                ])
                ->values()
                ->all(),
            'meta' => $this->meta($paginator),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ];
    }

    private function activeMembership(Builder $query, int $schoolYearId): Builder
    {
        return $query
            ->whereNull('archived_at')
            ->where(fn (Builder $status) => $status->whereNull('student_status_id')->orWhere('student_status_id', 1))
            ->whereNull('latest_student_exit_date')
            ->whereHas('schoolRombel', fn (Builder $rombel) => $rombel->where('school_year_id', $schoolYearId));
    }

    /**
     * @return array<string, int>
     */
    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPage(string $search, int $perPage): array
    {
        return [
            'items' => [],
            'meta' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
            ],
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ];
    }

    private function likePattern(string $search): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        return '%'.$escaped.'%';
    }
}
