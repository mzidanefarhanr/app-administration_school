<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\EmployeeDirectory;
use App\Support\EmployeeExportColumns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Filter-aware employee CSV export (DEC-24 D8, DEC-26 D1/D2).
 *
 * Requires `employees.view` and `employees.export`. The default set is the 10
 * operational columns from 04b. Sensitive personal columns are only available
 * to callers with `employees.manage`; the default never includes them. A result
 * set above the configured limit is rejected with 422 instead of being
 * truncated.
 */
class EmployeeExportController extends Controller
{
    private function maxRows(): int
    {
        return (int) config('employees.export_max_rows', 10000);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('employees.view');
        $this->authorize('employees.export');

        $canSensitive = $request->user()?->can('employees.manage') ?? false;
        $columns = [];

        foreach (EmployeeExportColumns::definitions() as $key => $definition) {
            if ($definition['sensitive'] && ! $canSensitive) {
                continue;
            }

            $columns[] = [
                'key' => $key,
                'header' => $definition['header'],
                'sensitive' => $definition['sensitive'],
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee export options retrieved successfully.',
            'data' => [
                'default' => EmployeeExportColumns::defaultKeys(),
                'columns' => $columns,
                'sensitive_allowed' => $canSensitive,
            ],
        ], 200);
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $this->authorize('employees.view');
        $this->authorize('employees.export');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'employee_type_id' => ['nullable', 'integer', 'exists:employee_types,id'],
            'employee_status_id' => ['nullable', 'integer', 'exists:employee_statuses,id'],
            'employment_id' => ['nullable', 'integer', 'exists:employments,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'columns' => ['nullable', 'string', 'max:500'],
        ]);

        $keys = $this->resolveColumns($validated['columns'] ?? null, $request);

        $query = EmployeeDirectory::base($validated)
            ->with(EmployeeExportColumns::relations())
            ->orderBy('employees.name')
            ->orderBy('employees.id');

        $select = EmployeeExportColumns::selectColumns($keys);
        if ($select !== []) {
            $query->addSelect($select);
        }

        $maxRows = $this->maxRows();
        $total = (clone $query)->count('employees.id');

        if ($total > $maxRows) {
            return response()->json([
                'message' => 'The export matches '.$total.' rows, which exceeds the limit of '
                    .$maxRows.'. Please narrow your filters (for example by employee type, '
                    .'status, or search) and try again.',
            ], 422);
        }

        $filename = 'employees_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query, $keys) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            $this->writeCsvRow($out, EmployeeExportColumns::headers($keys));

            $query->chunk(500, function ($employees) use ($out, $keys) {
                foreach ($employees as $employee) {
                    /** @var Employee $employee */
                    $this->writeCsvRow($out, EmployeeExportColumns::resolveRow($employee, $keys));
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function resolveColumns(?string $raw, Request $request): array
    {
        if ($raw === null || trim($raw) === '') {
            return EmployeeExportColumns::defaultKeys();
        }

        $keys = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $key) => $key !== ''
        )));

        if ($keys === []) {
            return EmployeeExportColumns::defaultKeys();
        }

        $unknown = array_values(array_diff($keys, EmployeeExportColumns::allKeys()));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'columns' => ['Unknown export column(s): '.implode(', ', $unknown).'.'],
            ]);
        }

        $sensitive = array_intersect($keys, EmployeeExportColumns::sensitiveKeys());
        if ($sensitive !== [] && ! ($request->user()?->can('employees.manage') ?? false)) {
            abort(403, 'Sensitive export columns require employees.manage.');
        }

        return EmployeeExportColumns::order($keys);
    }

    /**
     * @param  resource  $out
     * @param  array<int, mixed>  $row
     */
    private function writeCsvRow($out, array $row): void
    {
        fputcsv($out, array_map([$this, 'sanitizeCell'], $row), ',', '"', '\\');
    }

    private function sanitizeCell($value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            $value = "'".$value;
        }

        return $value;
    }
}
