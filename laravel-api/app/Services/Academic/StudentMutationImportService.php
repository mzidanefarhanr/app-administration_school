<?php

namespace App\Services\Academic;

use App\Models\EducationSchool;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\StudentRombel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class StudentMutationImportService
{
    public const COLUMNS_EN = [
        'Mutation Type' => 'type',
        'Student NIS' => 'nis',
        'Student Name' => 'name',
        'Class' => 'class_name',
        'Effective Date' => 'effective_date',
        'School NPSN' => 'school_npsn',
        'Reason' => 'reason',
    ];

    public const COLUMNS_ID = [
        'Tipe Mutasi' => 'type',
        'NIS Siswa' => 'nis',
        'Nama Siswa' => 'name',
        'Kelas' => 'class_name',
        'Tanggal Berlaku' => 'effective_date',
        'NPSN Sekolah' => 'school_npsn',
        'Alasan' => 'reason',
    ];

    public static function templateHeaders(string $lang = 'en'): array
    {
        return strtolower($lang) === 'id'
            ? array_keys(self::COLUMNS_ID)
            : array_keys(self::COLUMNS_EN);
    }

    /**
     * Download template Excel (.xlsx) untuk impor mutasi siswa.
     */
    public function templateXlsx(string $lang = 'en'): string
    {
        $isId = strtolower($lang) === 'id';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        $headers = static::templateHeaders($lang);
        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Header styling
        $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Contoh baris dummy (1 mutasi masuk, 1 mutasi keluar)
        $dummyRows = $isId ? [
            ['Masuk', '9595', 'KAKA VIRGIAWAN', 'X - 1', '7/22/2024', '20107400', 'Pindahan dari MAS AL KHAIRIYAH'],
            ['Keluar', '9303', 'ANDRA YULIA CANDRA', 'X - 1', '8/22/2025', '20107400', 'SMA MANUNGGAL BAKTI PARIAMAN'],
        ] : [
            ['in', '9595', 'KAKA VIRGIAWAN', 'X - 1', '7/22/2024', '20107400', 'Transfer from MAS AL KHAIRIYAH'],
            ['out', '9303', 'ANDRA YULIA CANDRA', 'X - 1', '8/22/2025', '20107400', 'SMA MANUNGGAL BAKTI PARIAMAN'],
        ];

        foreach ($dummyRows as $rowIdx => $row) {
            $r = $rowIdx + 2;
            foreach ($row as $colIdx => $val) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 1) . $r, $val);
            }
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return (string) ob_get_clean();
    }

    /**
     * Download template CSV.
     */
    public function templateCsv(string $lang = 'en'): string
    {
        $headers = static::templateHeaders($lang);
        $isId = strtolower($lang) === 'id';

        $dummyRows = $isId ? [
            ['Masuk', '9595', 'KAKA VIRGIAWAN', 'X - 1', '7/22/2024', '20107400', 'Pindahan dari MAS AL KHAIRIYAH'],
            ['Keluar', '9303', 'ANDRA YULIA CANDRA', 'X - 1', '8/22/2025', '20107400', 'SMA MANUNGGAL BAKTI PARIAMAN'],
        ] : [
            ['in', '9595', 'KAKA VIRGIAWAN', 'X - 1', '7/22/2024', '20107400', 'Transfer from MAS AL KHAIRIYAH'],
            ['out', '9303', 'ANDRA YULIA CANDRA', 'X - 1', '8/22/2025', '20107400', 'SMA MANUNGGAL BAKTI PARIAMAN'],
        ];

        $output = fopen('php://memory', 'r+');
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
     * Parse and import mutation rows from uploaded file.
     *
     * @return array<string, mixed>
     */
    public function import(UploadedFile $file, User $actor, int $schoolYearId): array
    {
        $schoolYear = SchoolYear::findOrFail($schoolYearId);
        $ext = strtolower($file->getClientOriginalExtension());
        $isExcel = in_array($ext, ['xlsx', 'xls'], true);

        $rows = [];
        if ($isExcel) {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, false, false);
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            if ($handle !== false) {
                while (($data = fgetcsv($handle)) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
        }

        if (empty($rows) || count($rows) < 2) {
            throw new RuntimeException('File kosong atau tidak memiliki baris data.');
        }

        $headerRow = array_map(fn ($h) => trim((string) $h), $rows[0]);
        $headerMap = [];
        foreach ($headerRow as $idx => $h) {
            $norm = strtolower($h);
            if (isset(self::COLUMNS_EN[$h])) {
                $headerMap[self::COLUMNS_EN[$h]] = $idx;
            } elseif (isset(self::COLUMNS_ID[$h])) {
                $headerMap[self::COLUMNS_ID[$h]] = $idx;
            } elseif (str_contains($norm, 'tipe') || str_contains($norm, 'type')) {
                $headerMap['type'] = $idx;
            } elseif (str_contains($norm, 'nis')) {
                $headerMap['nis'] = $idx;
            } elseif (str_contains($norm, 'nama') || str_contains($norm, 'name')) {
                $headerMap['name'] = $idx;
            } elseif (str_contains($norm, 'kelas') || str_contains($norm, 'class') || str_contains($norm, 'rombel')) {
                $headerMap['class_name'] = $idx;
            } elseif (str_contains($norm, 'tanggal') || str_contains($norm, 'date')) {
                $headerMap['effective_date'] = $idx;
            } elseif (str_contains($norm, 'npsn') || str_contains($norm, 'sekolah') || str_contains($norm, 'school')) {
                $headerMap['school_npsn'] = $idx;
            } elseif (str_contains($norm, 'alasan') || str_contains($norm, 'reason')) {
                $headerMap['reason'] = $idx;
            }
        }

        if (! isset($headerMap['type']) || ! isset($headerMap['nis'])) {
            throw new RuntimeException('Header kolom harus memuat setidaknya Tipe Mutasi dan NIS Siswa.');
        }

        $successCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                    continue;
                }

                $rowNum = $i + 1;
                $rawType = trim((string) ($row[$headerMap['type']] ?? ''));
                $nis = trim((string) ($row[$headerMap['nis']] ?? ''));
                $className = isset($headerMap['class_name']) ? trim((string) ($row[$headerMap['class_name']] ?? '')) : '';
                $rawDate = isset($headerMap['effective_date']) ? trim((string) ($row[$headerMap['effective_date']] ?? '')) : '';
                $schoolNpsn = isset($headerMap['school_npsn']) ? trim((string) ($row[$headerMap['school_npsn']] ?? '')) : '';
                $reason = isset($headerMap['reason']) ? trim((string) ($row[$headerMap['reason']] ?? '')) : '';

                $type = (strtolower($rawType) === 'masuk' || strtolower($rawType) === 'in') ? 'in' : ((strtolower($rawType) === 'keluar' || strtolower($rawType) === 'out') ? 'out' : null);
                if (! $type) {
                    $errors[] = "Baris {$rowNum}: Tipe mutasi harus bernilai 'Masuk' atau 'Keluar'.";
                    continue;
                }

                $student = Student::where('nis', $nis)->first();
                if (! $student) {
                    $errors[] = "Baris {$rowNum}: Siswa dengan NIS '{$nis}' tidak ditemukan.";
                    continue;
                }

                $effectiveDate = $this->parseDate($rawDate) ?: Carbon::now()->format('Y-m-d');

                // Cari Rombel
                $schoolRombel = null;
                if ($className !== '') {
                    $schoolRombel = SchoolRombel::where('school_year_id', $schoolYear->id)
                        ->where('name', $className)
                        ->first();
                }

                if (! $schoolRombel) {
                    // Cari rombel aktif siswa
                    $currentMembership = StudentRombel::where('student_id', $student->id)
                        ->whereNull('deleted_at')
                        ->where('student_status_id', 1)
                        ->first();
                    $schoolRombel = $currentMembership ? SchoolRombel::find($currentMembership->school_rombel) : null;
                }

                if (! $schoolRombel) {
                    $schoolRombel = SchoolRombel::where('school_year_id', $schoolYear->id)->first();
                }

                if ($type === 'in') {
                    // Buat/aktifkan keanggotaan rombel
                    $membership = StudentRombel::create([
                        'student_id' => $student->id,
                        'student_nik' => $student->nik,
                        'school_rombel' => $schoolRombel ? $schoolRombel->id : 1,
                        'student_status_id' => 1, // Aktif
                        'student_entry_id' => 2, // Pindahan
                        'latest_student_entry_date' => $effectiveDate,
                        'mutation_education_school_id' => $schoolNpsn ?: null,
                        'version' => 1,
                    ]);

                    StudentMutation::create([
                        'school_year_id' => $schoolYear->id,
                        'student_id' => $student->id,
                        'school_rombel_id' => $schoolRombel ? $schoolRombel->id : 1,
                        'student_rombel_id' => $membership->id,
                        'type' => StudentMutation::TYPE_IN,
                        'effective_date' => $effectiveDate,
                        'status' => StudentMutation::STATUS_ACTIVE,
                        'school_npsn' => $schoolNpsn ?: null,
                        'reason' => $reason ?: 'Mutasi Masuk dari Impor',
                        'version' => 1,
                        'created_by' => $actor->id,
                    ]);
                } else {
                    // Mutasi Keluar
                    $activeMembership = StudentRombel::where('student_id', $student->id)
                        ->whereNull('deleted_at')
                        ->where('student_status_id', 1)
                        ->first();

                    if ($activeMembership) {
                        $activeMembership->student_status_id = 2; // Mutasi Keluar
                        $activeMembership->latest_student_exit_date = $effectiveDate;
                        $activeMembership->latest_student_exit_school_id = $schoolNpsn ?: null;
                        $activeMembership->version += 1;
                        $activeMembership->save();
                    }

                    StudentMutation::create([
                        'school_year_id' => $schoolYear->id,
                        'student_id' => $student->id,
                        'school_rombel_id' => $schoolRombel ? $schoolRombel->id : ($activeMembership ? $activeMembership->school_rombel : 1),
                        'student_rombel_id' => $activeMembership ? $activeMembership->id : null,
                        'type' => StudentMutation::TYPE_OUT,
                        'effective_date' => $effectiveDate,
                        'status' => StudentMutation::STATUS_ACTIVE,
                        'school_npsn' => $schoolNpsn ?: null,
                        'reason' => $reason ?: 'Mutasi Keluar dari Impor',
                        'version' => 1,
                        'created_by' => $actor->id,
                    ]);
                }

                $successCount++;
            }

            if (! empty($errors) && $successCount === 0) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Gagal mengimpor mutasi siswa.',
                    'success_count' => 0,
                    'errors' => $errors,
                ];
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'success' => true,
            'message' => "Berhasil mengimpor {$successCount} data mutasi siswa.",
            'success_count' => $successCount,
            'errors' => $errors,
        ];
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Serial number Excel
        if (is_numeric($raw) && (float) $raw > 1000) {
            try {
                $unix = ((float) $raw - 25569) * 86400;
                return gmdate('Y-m-d', (int) $unix);
            } catch (\Throwable) {
            }
        }

        // M/D/YYYY
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $raw, $m)) {
            $month = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $day = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $year = $m[3];
            return "{$year}-{$month}-{$day}";
        }

        // YYYY-MM-DD
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $raw, $m)) {
            $year = $m[1];
            $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $day = str_pad($m[3], 2, '0', STR_PAD_LEFT);
            return "{$year}-{$month}-{$day}";
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
