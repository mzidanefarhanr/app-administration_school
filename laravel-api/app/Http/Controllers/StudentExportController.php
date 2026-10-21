<?php

namespace App\Http\Controllers;

use App\Models\SchoolRombel;
use App\Models\Student;
use App\Models\StudentRombel;
use App\Support\StudentDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor siswa (CSV) terlindungi — DEC-14, DEC-22.
 *
 * Izin (server): `students.view` wajib, `students.export` wajib untuk unduhan.
 * Filter memakai definisi bersama dengan daftar (StudentDirectory): tahun ajaran
 * wajib, pencarian, rombel, dan placement. Ekspor mencakup seluruh hasil filter,
 * bukan halaman aktif. `unplaced` menghasilkan kolom rombel kosong (tanpa mengarang).
 *
 * Batas baris: hasil melebihi `students.export_max_rows` DITOLAK 422, bukan dipotong.
 * Kolom eksplisit; identitas sebagai teks (nol awal dipertahankan); formula injection
 * dinetralkan. Kolom sensitif baru tidak ditambahkan.
 */
class StudentExportController extends Controller
{
    private function maxRows(): int
    {
        return (int) config('students.export_max_rows', 10000);
    }

    /**
     * Kolom eksplisit pada CSV (urutan & header English).
     *
     * @var array<string, array{0: string, 1: callable}>
     */
    private array $columns = [];

    public function __construct()
    {
        $this->columns = [
            'name' => ['Name', fn (Student $s) => $s->name],
            'gender' => ['Gender', fn (Student $s) => $s->gender?->name],
            'birthplace' => ['Birthplace', fn (Student $s) => $s->birthplace],
            'birthdate' => ['Birthdate', fn (Student $s) => $s->birthdate],
            'nik' => ['NIK', fn (Student $s) => $s->nik],
            'nis' => ['NIS', fn (Student $s) => $s->nis],
            'nisn' => ['NISN', fn (Student $s) => $s->nisn],
            'school_rombel' => ['School Rombel', fn (Student $s, ?StudentRombel $m) => $m?->schoolRombel?->name],
            'student_entry' => ['Student Entry', fn (Student $s, ?StudentRombel $m) => $m?->studentEntry?->name],
            'student_status' => ['Student Status', fn (Student $s, ?StudentRombel $m) => $m?->studentStatus?->name],
        ];
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $this->authorize('students.view');
        $this->authorize('students.export');

        $validated = $request->validate([
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'placement' => ['nullable', 'in:all,placed,unplaced'],
            'rombel' => ['nullable', 'integer', 'exists:school_rombels,id'],
        ], [
            'school_year_id.required' => 'A school year is required to export students.',
            'placement.in' => 'The placement filter must be one of: all, placed, unplaced.',
        ]);

        $yearId = (int) $validated['school_year_id'];
        // Default `placed` mempertahankan perilaku ekspor per tahun ajaran (DEC-22).
        $placement = $validated['placement'] ?? 'placed';
        $rombelId = isset($validated['rombel']) ? (int) $validated['rombel'] : null;

        if ($rombelId !== null && ! SchoolRombel::where('id', $rombelId)->where('school_year_id', $yearId)->exists()) {
            throw ValidationException::withMessages([
                'rombel' => ['The selected rombel does not belong to the selected school year.'],
            ]);
        }

        if ($rombelId !== null && $placement === 'unplaced') {
            throw ValidationException::withMessages([
                'placement' => ['The unplaced filter cannot be combined with a rombel filter.'],
            ]);
        }

        $query = StudentDirectory::place(StudentDirectory::base([
            'school_year_id' => $yearId,
            'search' => $validated['search'] ?? null,
            'rombel' => $rombelId,
        ]), $yearId, $placement)
            ->with('gender:id,name')
            ->orderBy('students.name')
            ->orderBy('students.id');

        $maxRows = $this->maxRows();
        $total = (clone $query)->count('students.id');
        if ($total > $maxRows) {
            return response()->json([
                'message' => 'The export matches '.$total.' rows, which exceeds the limit of '
                    .$maxRows.'. Please narrow your filters (for example by school year, '
                    .'rombel, or search) and try again.',
            ], 422);
        }

        $filename = 'students_'.$yearId.'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query, $yearId) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            $this->writeCsvRow($out, array_map(fn ($c) => $c[0], $this->columns));

            $query->chunk(500, function ($students) use ($out, $yearId) {
                $memberships = StudentDirectory::membershipsFor($students, $yearId);

                foreach ($students as $student) {
                    $membership = $memberships[$student->id] ?? null;
                    $row = [];
                    foreach ($this->columns as [$header, $resolver]) {
                        $row[] = $resolver($student, $membership);
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
     * Tulis satu baris CSV: nilai sebagai teks, escape RFC 4180, cegah formula injection.
     *
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
