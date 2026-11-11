<?php

namespace App\Services\Academic;

use App\Models\Employee;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\StudentMajor;
use App\Models\StudentRombel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class SchoolRombelImportExportService
{
    public const TEMPLATE_COLUMNS_ID = [
        'Nama Rombel' => 'name',
        'Tahun Ajaran' => 'school_year',
        'Tingkat' => 'school_level',
        'Jurusan' => 'student_major',
        'Wali Kelas' => 'homeroom_teacher',
        'NIK Wali Kelas' => 'teacher_nik',
    ];

    public const TEMPLATE_COLUMNS_EN = [
        'Class Name' => 'name',
        'Academic Year' => 'school_year',
        'Level' => 'school_level',
        'Major' => 'student_major',
        'Homeroom Teacher' => 'homeroom_teacher',
        'Teacher NIK' => 'teacher_nik',
    ];

    /**
     * Query daftar rombel terfilter untuk kebutuhan Export.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, SchoolRombel>
     */
    public function queryFilteredRombels(array $filters = []): Collection
    {
        $query = SchoolRombel::query()
            ->with([
                'schoolLevel:id,name',
                'schoolYear:id,name,start_date,end_date,locked_at',
                'studentMajor:id,name',
                'classTeachEmployee:id,name,nik',
                'classTeach:id,name',
            ])
            ->withCount([
                'studentRombels as memberships_count' => fn (Builder $q) => $q->withTrashed(),
                'studentRombels as active_members_count' => fn (Builder $q) => $q
                    ->whereNull('deleted_at')
                    ->whereNull('archived_at')
                    ->where(fn (Builder $inner) => $inner
                        ->where('student_status_id', 1)
                        ->orWhereNull('student_status_id'))
                    ->whereNull('latest_student_exit_date'),
            ]);

        if (! empty($filters['school_year_id'])) {
            $query->where('school_year_id', (int) $filters['school_year_id']);
        }

        if (! empty($filters['school_level_id'])) {
            $query->where('school_level_id', (int) $filters['school_level_id']);
        }

        if (! empty($filters['student_major_id'])) {
            $query->where('student_major_id', (int) $filters['student_major_id']);
        }

        $items = $query
            ->orderByDesc('school_year_id')
            ->orderBy('name')
            ->get();

        $teacherSearch = mb_strtolower(trim((string) ($filters['teacher_search'] ?? '')));
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        return $items->filter(function (SchoolRombel $row) use ($teacherSearch, $search) {
            $teacherName = mb_strtolower((string) ($row->classTeachEmployee?->name ?? $row->classTeach?->name ?? ''));
            if ($teacherSearch !== '' && ! str_contains($teacherName, $teacherSearch)) {
                return false;
            }

            if ($search !== '') {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $row->name,
                    $row->schoolYear?->name,
                    $row->schoolLevel?->name,
                    $row->studentMajor?->name,
                    $row->classTeachEmployee?->name,
                    $row->classTeach?->name,
                ])));
                if (! str_contains($haystack, $search)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * Ekspor daftar rombel ke format Excel (.xlsx).
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportXlsx(array $filters = [], string $lang = 'id'): string
    {
        $isId = strtolower($lang) !== 'en';
        $rows = $this->queryFilteredRombels($filters);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($isId ? 'Rombongan Belajar' : 'Classes');

        $headers = $isId
            ? ['No', 'Nama Rombel', 'Tahun Ajaran', 'Tingkat', 'Jurusan', 'Wali Kelas', 'NIK Wali Kelas', 'Siswa Aktif', 'Total Riwayat Siswa']
            : ['No', 'Class Name', 'Academic Year', 'Level', 'Major', 'Homeroom Teacher', 'Teacher NIK', 'Active Students', 'Total Memberships'];

        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $lastColLetter = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        foreach ($rows as $idx => $rombel) {
            $r = $idx + 2;
            $teacherName = $rombel->classTeachEmployee?->name ?? $rombel->classTeach?->name ?? '-';
            $teacherNik = (string) ($rombel->classTeachEmployee?->nik ?? '');

            $sheet->setCellValue("A{$r}", $idx + 1);
            $sheet->setCellValueExplicit("B{$r}", (string) $rombel->name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$r}", (string) ($rombel->schoolYear?->name ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$r}", (string) ($rombel->schoolLevel?->name ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$r}", (string) ($rombel->studentMajor?->name ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$r}", (string) $teacherName, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("G{$r}", $teacherNik, DataType::TYPE_STRING);
            $sheet->setCellValue("H{$r}", (int) ($rombel->active_members_count ?? 0));
            $sheet->setCellValue("I{$r}", (int) ($rombel->memberships_count ?? 0));
        }

        if ($rows->count() > 0) {
            $lastRow = $rows->count() + 1;
            $sheet->getStyle("A1:{$lastColLetter}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1'],
                    ],
                ],
            ]);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    /**
     * Ekspor daftar rombel ke format CSV (UTF-8 BOM).
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(array $filters = [], string $lang = 'id'): string
    {
        $isId = strtolower($lang) !== 'en';
        $rows = $this->queryFilteredRombels($filters);

        $headers = $isId
            ? ['No', 'Nama Rombel', 'Tahun Ajaran', 'Tingkat', 'Jurusan', 'Wali Kelas', 'NIK Wali Kelas', 'Siswa Aktif', 'Total Riwayat Siswa']
            : ['No', 'Class Name', 'Academic Year', 'Level', 'Major', 'Homeroom Teacher', 'Teacher NIK', 'Active Students', 'Total Memberships'];

        $output = fopen('php://memory', 'r+');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);

        foreach ($rows as $idx => $rombel) {
            $teacherName = $rombel->classTeachEmployee?->name ?? $rombel->classTeach?->name ?? '-';
            $teacherNik = (string) ($rombel->classTeachEmployee?->nik ?? '');

            fputcsv($output, [
                $idx + 1,
                (string) $rombel->name,
                (string) ($rombel->schoolYear?->name ?? '-'),
                (string) ($rombel->schoolLevel?->name ?? '-'),
                (string) ($rombel->studentMajor?->name ?? '-'),
                (string) $teacherName,
                $teacherNik,
                (int) ($rombel->active_members_count ?? 0),
                (int) ($rombel->memberships_count ?? 0),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv ?: '';
    }

    /**
     * Unduh template Excel (.xlsx) untuk impor Rombongan Belajar.
     */
    public function templateXlsx(string $lang = 'id', ?int $defaultSchoolYearId = null): string
    {
        $isId = strtolower($lang) !== 'en';
        $headers = array_keys($isId ? self::TEMPLATE_COLUMNS_ID : self::TEMPLATE_COLUMNS_EN);

        $unlockedYears = SchoolYear::query()
            ->whereNull('locked_at')
            ->orderByDesc('name')
            ->pluck('name', 'id');

        $defaultYearName = ($defaultSchoolYearId && isset($unlockedYears[$defaultSchoolYearId]))
            ? $unlockedYears[$defaultSchoolYearId]
            : ($unlockedYears->first() ?? '2026/2027');

        $levels = SchoolLevel::query()->orderBy('id')->pluck('name')->values()->all();
        $majors = StudentMajor::query()->orderBy('name')->pluck('name')->values()->all();
        $teachers = Employee::query()
            ->eligibleHomeroomTeacher()
            ->orderBy('name')
            ->get(['name', 'nik']);

        $sampleLevel1 = $levels[0] ?? 'X';
        $sampleLevel2 = $levels[1] ?? $sampleLevel1;
        $sampleMajor1 = $majors[0] ?? '';
        $sampleMajor2 = $majors[1] ?? $sampleMajor1;
        $sampleTeacher1 = $teachers->first();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $lastColLetter = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $dummyRows = [
            ["{$sampleLevel1} - 1", $defaultYearName, $sampleLevel1, $sampleMajor1, $sampleTeacher1?->name ?? '', (string) ($sampleTeacher1?->nik ?? '')],
            ["{$sampleLevel2} - 1", $defaultYearName, $sampleLevel2, $sampleMajor2, '', ''],
        ];

        foreach ($dummyRows as $rIdx => $row) {
            $r = $rIdx + 2;
            foreach ($row as $cIdx => $val) {
                $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
                $sheet->setCellValueExplicit("{$colLetter}{$r}", (string) $val, DataType::TYPE_STRING);
            }
        }

        // Sheet 2: Referensi Master Data & Dropdown Validation
        $refSheet = $spreadsheet->createSheet();
        $refSheetTitle = $isId ? 'Referensi' : 'Reference';
        $refSheet->setTitle($refSheetTitle);

        $refHeaders = $isId
            ? ['Tahun Ajaran Terbuka', 'Tingkat', 'Jurusan', 'Nama Guru Wali Kelas Aktif', 'NIK Guru']
            : ['Unlocked Academic Year', 'Level', 'Major', 'Active Homeroom Teacher', 'Teacher NIK'];

        foreach ($refHeaders as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $refSheet->setCellValue("{$colLetter}1", $header);
            $refSheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $refSheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0284C7']],
        ]);

        $yearList = $unlockedYears->values()->all();
        foreach ($yearList as $idx => $yName) {
            $refSheet->setCellValueExplicit('A' . ($idx + 2), (string) $yName, DataType::TYPE_STRING);
        }
        foreach ($levels as $idx => $lName) {
            $refSheet->setCellValueExplicit('B' . ($idx + 2), (string) $lName, DataType::TYPE_STRING);
        }
        foreach ($majors as $idx => $mName) {
            $refSheet->setCellValueExplicit('C' . ($idx + 2), (string) $mName, DataType::TYPE_STRING);
        }
        foreach ($teachers as $idx => $tObj) {
            $refSheet->setCellValueExplicit('D' . ($idx + 2), (string) $tObj->name, DataType::TYPE_STRING);
            $refSheet->setCellValueExplicit('E' . ($idx + 2), (string) ($tObj->nik ?? ''), DataType::TYPE_STRING);
        }

        // Pasang DataValidation dropdown pada Sheet Template (baris 2 s/d 150)
        if (count($yearList) > 0) {
            $this->applyDropdownValidation($sheet, 'B', 2, 150, "'{$refSheetTitle}'!\$A\$2:\$A\$" . (count($yearList) + 1));
        }
        if (count($levels) > 0) {
            $this->applyDropdownValidation($sheet, 'C', 2, 150, "'{$refSheetTitle}'!\$B\$2:\$B\$" . (count($levels) + 1));
        }
        if (count($majors) > 0) {
            $this->applyDropdownValidation($sheet, 'D', 2, 150, "'{$refSheetTitle}'!\$C\$2:\$C\$" . (count($majors) + 1));
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    /**
     * Unduh template CSV untuk impor Rombongan Belajar.
     */
    public function templateCsv(string $lang = 'id', ?int $defaultSchoolYearId = null): string
    {
        $isId = strtolower($lang) !== 'en';
        $headers = array_keys($isId ? self::TEMPLATE_COLUMNS_ID : self::TEMPLATE_COLUMNS_EN);

        $unlockedYears = SchoolYear::query()
            ->whereNull('locked_at')
            ->orderByDesc('name')
            ->pluck('name', 'id');

        $defaultYearName = ($defaultSchoolYearId && isset($unlockedYears[$defaultSchoolYearId]))
            ? $unlockedYears[$defaultSchoolYearId]
            : ($unlockedYears->first() ?? '2026/2027');

        $levels = SchoolLevel::query()->orderBy('id')->pluck('name')->values()->all();
        $majors = StudentMajor::query()->orderBy('name')->pluck('name')->values()->all();
        $sampleTeacher = Employee::query()->eligibleHomeroomTeacher()->orderBy('name')->first(['name', 'nik']);

        $sampleLevel1 = $levels[0] ?? 'X';
        $sampleLevel2 = $levels[1] ?? $sampleLevel1;
        $sampleMajor1 = $majors[0] ?? '';
        $sampleMajor2 = $majors[1] ?? $sampleMajor1;

        $dummyRows = [
            ["{$sampleLevel1} - 1", $defaultYearName, $sampleLevel1, $sampleMajor1, $sampleTeacher?->name ?? '', (string) ($sampleTeacher?->nik ?? '')],
            ["{$sampleLevel2} - 1", $defaultYearName, $sampleLevel2, $sampleMajor2, '', ''],
        ];

        $output = fopen('php://memory', 'r+');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);
        foreach ($dummyRows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv ?: '';
    }

    /**
     * Proses impor berkas Rombongan Belajar (.xlsx, .xls, .csv).
     *
     * @return array<string, mixed>
     */
    public function import(UploadedFile $file, ?int $defaultSchoolYearId = null): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $isExcel = in_array($ext, ['xlsx', 'xls'], true);

        $rows = [];
        if ($isExcel) {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getSheet(0);
            $rows = $sheet->toArray(null, true, false, false);
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            if ($handle !== false) {
                $firstLine = fgets($handle);
                if ($firstLine !== false) {
                    // Hapus UTF-8 BOM jika ada
                    $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
                    $rows[] = str_getcsv($firstLine);
                }
                while (($data = fgetcsv($handle)) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
        }

        if (empty($rows) || count($rows) < 2) {
            throw new RuntimeException('Berkas kosong atau tidak memiliki baris data untuk diimpor.');
        }

        $headerRow = array_map(fn ($h) => trim((string) $h), $rows[0]);
        $headerMap = $this->resolveHeaderMap($headerRow);

        if (! isset($headerMap['name']) || ! isset($headerMap['school_level'])) {
            throw new RuntimeException('Header kolom wajib memuat setidaknya "Nama Rombel" (Class Name) dan "Tingkat" (Level).');
        }

        // Preload kamus referensi agar pencocokan cepat dan bebas N+1
        $yearsById = SchoolYear::withTrashed()->get()->keyBy('id');
        $yearsByName = SchoolYear::withTrashed()
            ->get()
            ->keyBy(fn (SchoolYear $y) => mb_strtolower(trim($y->name)));

        $levelsByName = SchoolLevel::query()
            ->get()
            ->keyBy(fn (SchoolLevel $l) => mb_strtolower(trim($l->name)));

        $majorsByName = StudentMajor::query()
            ->get()
            ->keyBy(fn (StudentMajor $m) => mb_strtolower(trim($m->name)));

        $eligibleTeachers = Employee::query()
            ->eligibleHomeroomTeacher()
            ->get(['id', 'name', 'nik', 'user_id']);

        $teachersByNik = $eligibleTeachers
            ->filter(fn (Employee $e) => ! empty($e->nik))
            ->keyBy(fn (Employee $e) => trim((string) $e->nik));

        $teachersByName = $eligibleTeachers
            ->keyBy(fn (Employee $e) => mb_strtolower(trim($e->name)));

        $createdCount = 0;
        $updatedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                    continue;
                }

                $rowNum = $i + 1;
                $name = trim((string) ($row[$headerMap['name']] ?? ''));
                $yearCell = isset($headerMap['school_year']) ? trim((string) ($row[$headerMap['school_year']] ?? '')) : '';
                $levelCell = trim((string) ($row[$headerMap['school_level']] ?? ''));
                $majorCell = isset($headerMap['student_major']) ? trim((string) ($row[$headerMap['student_major']] ?? '')) : '';
                $teacherCell = isset($headerMap['homeroom_teacher']) ? trim((string) ($row[$headerMap['homeroom_teacher']] ?? '')) : '';
                $teacherNikCell = isset($headerMap['teacher_nik']) ? trim((string) ($row[$headerMap['teacher_nik']] ?? '')) : '';

                if ($name === '') {
                    $errors[] = "Baris {$rowNum}: Nama Rombel wajib diisi.";
                    continue;
                }

                if (mb_strlen($name) > 100) {
                    $errors[] = "Baris {$rowNum} ({$name}): Nama Rombel maksimal 100 karakter.";
                    continue;
                }

                // 1. Resolusi Tahun Ajaran
                $schoolYear = null;
                if ($yearCell !== '' && $yearCell !== '-') {
                    $schoolYear = $yearsByName->get(mb_strtolower($yearCell));
                    if (! $schoolYear) {
                        $errors[] = "Baris {$rowNum} ({$name}): Tahun Ajaran '{$yearCell}' tidak ditemukan.";
                        continue;
                    }
                } elseif ($defaultSchoolYearId !== null) {
                    $schoolYear = $yearsById->get($defaultSchoolYearId);
                }

                if (! $schoolYear || $schoolYear->deleted_at !== null) {
                    $errors[] = "Baris {$rowNum} ({$name}): Tahun Ajaran belum dipilih atau tidak tersedia.";
                    continue;
                }

                if ($schoolYear->locked_at !== null) {
                    $errors[] = "Baris {$rowNum} ({$name}): Tahun Ajaran '{$schoolYear->name}' sedang terkunci.";
                    continue;
                }

                // 2. Resolusi Tingkat (SchoolLevel)
                if ($levelCell === '' || $levelCell === '-') {
                    $errors[] = "Baris {$rowNum} ({$name}): Tingkat kelas wajib diisi.";
                    continue;
                }

                $schoolLevel = $levelsByName->get(mb_strtolower($levelCell));
                if (! $schoolLevel) {
                    $errors[] = "Baris {$rowNum} ({$name}): Tingkat '{$levelCell}' tidak ditemukan pada master tingkat.";
                    continue;
                }

                // 3. Resolusi Jurusan (StudentMajor - opsional)
                $studentMajorId = null;
                if ($majorCell !== '' && $majorCell !== '-') {
                    $major = $majorsByName->get(mb_strtolower($majorCell));
                    if (! $major) {
                        $errors[] = "Baris {$rowNum} ({$name}): Jurusan '{$majorCell}' tidak ditemukan pada master jurusan.";
                        continue;
                    }
                    $studentMajorId = $major->id;
                }

                // 4. Resolusi Wali Kelas (opsional)
                $teacher = null;
                if ($teacherNikCell !== '' && $teacherNikCell !== '-') {
                    $teacher = $teachersByNik->get($teacherNikCell);
                    if (! $teacher) {
                        $errors[] = "Baris {$rowNum} ({$name}): Guru aktif dengan NIK '{$teacherNikCell}' tidak ditemukan.";
                        continue;
                    }
                } elseif ($teacherCell !== '' && $teacherCell !== '-') {
                    $teacher = $teachersByNik->get($teacherCell)
                        ?? $teachersByName->get(mb_strtolower($teacherCell));

                    if (! $teacher && preg_match('/\((\d{16})\)/', $teacherCell, $matches)) {
                        $teacher = $teachersByNik->get($matches[1]);
                    }

                    if (! $teacher) {
                        $errors[] = "Baris {$rowNum} ({$name}): Wali Kelas '{$teacherCell}' tidak ditemukan dalam daftar pendidik aktif.";
                        continue;
                    }
                }

                // 5. Cek apakah Rombel sudah ada pada Tahun Ajaran tersebut (case-insensitive)
                $existing = SchoolRombel::withTrashed()
                    ->where('school_year_id', $schoolYear->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                        $existing->update([
                            'name' => $name,
                            'school_level_id' => $schoolLevel->id,
                            'student_major_id' => $studentMajorId,
                            'class_teach_employee_id' => $teacher?->id,
                            'class_teach_id' => $teacher?->user_id,
                            'version' => (int) $existing->version + 1,
                        ]);
                        $createdCount++;
                        continue;
                    }

                    $hasHistory = StudentRombel::withTrashed()
                        ->where('school_rombel', $existing->id)
                        ->exists();

                    $levelChanged = (int) $existing->school_level_id !== (int) $schoolLevel->id;
                    $majorChanged = (int) ($existing->student_major_id ?? 0) !== (int) ($studentMajorId ?? 0);

                    if ($hasHistory && ($levelChanged || $majorChanged)) {
                        $errors[] = "Baris {$rowNum} ({$name}): Tingkat/Jurusan tidak dapat diubah karena rombel sudah memiliki riwayat siswa.";
                        continue;
                    }

                    $existing->update([
                        'name' => $name,
                        'school_level_id' => $schoolLevel->id,
                        'student_major_id' => $studentMajorId,
                        'class_teach_employee_id' => $teacher?->id,
                        'class_teach_id' => $teacher?->user_id,
                        'version' => (int) $existing->version + 1,
                    ]);
                    $updatedCount++;
                } else {
                    SchoolRombel::create([
                        'version' => 1,
                        'name' => $name,
                        'school_year_id' => $schoolYear->id,
                        'school_level_id' => $schoolLevel->id,
                        'student_major_id' => $studentMajorId,
                        'class_teach_employee_id' => $teacher?->id,
                        'class_teach_id' => $teacher?->user_id,
                    ]);
                    $createdCount++;
                }
            }

            if ($createdCount === 0 && $updatedCount === 0 && ! empty($errors)) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'Tidak ada data rombel yang dapat diimpor. Periksa kembali pesan kesalahan di bawah.',
                    'created_count' => 0,
                    'updated_count' => 0,
                    'errors' => $errors,
                ];
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $totalProcessed = $createdCount + $updatedCount;
        $summaryParts = [];
        if ($createdCount > 0) {
            $summaryParts[] = "{$createdCount} ditambahkan";
        }
        if ($updatedCount > 0) {
            $summaryParts[] = "{$updatedCount} diperbarui";
        }
        $summaryText = ! empty($summaryParts) ? ' (' . implode(', ', $summaryParts) . ')' : '';

        return [
            'success' => true,
            'message' => "Berhasil memproses {$totalProcessed} rombongan belajar{$summaryText}." . (count($errors) > 0 ? ' Beberapa baris dilewati karena tidak valid.' : ''),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Petakan baris header ke kunci internal.
     *
     * @param  array<int, string>  $headerRow
     * @return array<string, int>
     */
    private function resolveHeaderMap(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $idx => $h) {
            $clean = trim($h);
            $norm = mb_strtolower($clean);

            if (isset(self::TEMPLATE_COLUMNS_ID[$clean])) {
                $map[self::TEMPLATE_COLUMNS_ID[$clean]] = $idx;
            } elseif (isset(self::TEMPLATE_COLUMNS_EN[$clean])) {
                $map[self::TEMPLATE_COLUMNS_EN[$clean]] = $idx;
            } elseif (in_array($norm, ['nama rombel', 'nama kelas', 'class name', 'rombel', 'kelas', 'nama', 'name'], true)) {
                $map['name'] = $idx;
            } elseif (str_contains($norm, 'tahun') || str_contains($norm, 'academic year') || $norm === 'year') {
                $map['school_year'] = $idx;
            } elseif (str_contains($norm, 'tingkat') || str_contains($norm, 'level')) {
                $map['school_level'] = $idx;
            } elseif (str_contains($norm, 'jurusan') || str_contains($norm, 'major')) {
                $map['student_major'] = $idx;
            } elseif (str_contains($norm, 'nik')) {
                $map['teacher_nik'] = $idx;
            } elseif (str_contains($norm, 'wali') || str_contains($norm, 'teacher') || str_contains($norm, 'guru')) {
                $map['homeroom_teacher'] = $idx;
            }
        }

        return $map;
    }

    private function applyDropdownValidation($sheet, string $colLetter, int $startRow, int $endRow, string $formula): void
    {
        for ($r = $startRow; $r <= $endRow; $r++) {
            $validation = $sheet->getCell("{$colLetter}{$r}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1($formula);
        }
    }
}
