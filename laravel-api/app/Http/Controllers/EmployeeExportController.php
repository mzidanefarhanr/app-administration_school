<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\EmployeeDirectory;
use App\Support\ReferenceLabels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Filter-aware employee CSV export (DEC-24 D8).
 *
 * Requires `employees.view` and `employees.export`. Columns are explicit and
 * operational only: no NIK, NPWP, family card, mother/partner names, grades,
 * or account data. A result set above the configured limit is rejected with
 * 422 instead of being truncated.
 */
class EmployeeExportController extends Controller
{
    private function maxRows(): int
    {
        return (int) config('employees.export_max_rows', 10000);
    }

    /**
     * @var array<string, array{0: string, 1: callable}>
     */
    private array $columns = [];

    public function __construct()
    {
        $this->columns = [
            'name' => ['Name', fn (Employee $e) => $e->name],
            'employee_type' => ['Employee Type', fn (Employee $e) => ReferenceLabels::display('employee-types', $e->employeeType?->name)],
            'employee_status' => ['Employee Status', fn (Employee $e) => ReferenceLabels::display('employee-statuses', $e->employeeStatus?->name)],
            'employment' => ['Employment', fn (Employee $e) => ReferenceLabels::display('employments', $e->employment?->name)],
            'subject' => ['Subject', fn (Employee $e) => ReferenceLabels::display('subjects', $e->subject?->name)],
            'nuptk' => ['NUPTK', fn (Employee $e) => $e->nuptk],
            'gender' => ['Gender', fn (Employee $e) => ReferenceLabels::display('genders', $e->gender?->name)],
            'tmt_employee' => ['Employment Start Date (TMT)', fn (Employee $e) => $e->tmt_employee],
            'exit_date' => ['Exit Date', fn (Employee $e) => $e->exit_date],
            'archived_at' => ['Archived At', fn (Employee $e) => $e->deleted_at],
        ];
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
        ]);

        $query = EmployeeDirectory::base($validated)
            ->with([
                'employeeType:id,name',
                'employeeStatus:id,name',
                'employment:id,name',
                'subject:id,name',
                'gender:id,name',
            ])
            ->orderBy('employees.name')
            ->orderBy('employees.id');

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

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            $this->writeCsvRow($out, array_map(fn ($c) => $c[0], $this->columns));

            $query->chunk(500, function ($employees) use ($out) {
                foreach ($employees as $employee) {
                    $row = [];
                    foreach ($this->columns as [$header, $resolver]) {
                        $row[] = $resolver($employee);
                    }
                    $this->writeCsvRow($out, $row);
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
