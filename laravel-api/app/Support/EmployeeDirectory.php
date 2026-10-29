<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared filter definition for the employee directory and its export (DEC-24).
 *
 * - Archived rows never appear in the active list or export.
 * - Search covers name and NUPTK only; NIK/NPWP/KK are never searchable.
 * - Filters are additive and validated by the controller before use.
 */
class EmployeeDirectory
{
    public const SORTS = ['id', 'name', 'tmt_employee'];

    public const PER_PAGE = [10, 20, 50, 100];

    /**
     * @var array<int, string>
     */
    public const FILTER_FIELDS = ['employee_type_id', 'employee_status_id', 'employment_id', 'subject_id'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function base(array $filters): Builder
    {
        $query = Employee::query()
            ->whereNull('employees.deleted_at')
            ->select(self::columns());

        self::applyFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function archivedBase(array $filters): Builder
    {
        $query = Employee::onlyTrashed()
            ->select(self::columns());

        self::applyFilters($query, $filters);

        return $query;
    }

    /**
     * @return array<int, string>
     */
    private static function columns(): array
    {
        return [
            'employees.id',
            'employees.version',
            'employees.name',
            'employees.nuptk',
            'employees.user_id',
            'employees.employee_type_id',
            'employees.employee_status_id',
            'employees.employment_id',
            'employees.subject_id',
            'employees.gender_id',
            'employees.tmt_employee',
            'employees.exit_date',
            'employees.deleted_at',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('employees.name', 'like', "%{$search}%")
                    ->orWhere('employees.nuptk', 'like', "%{$search}%");
            });
        }

        foreach (self::FILTER_FIELDS as $field) {
            if (! empty($filters[$field])) {
                $query->where('employees.'.$field, (int) $filters[$field]);
            }
        }
    }
}
