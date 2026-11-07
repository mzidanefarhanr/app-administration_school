<?php

namespace App\Services\Academic;

use App\Models\AcademicSetting;
use App\Models\EducationSchool;
use App\Models\SchoolProfile;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentMutation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StudentMutationReportService
{
    /**
     * Nama-nama bulan dalam Bahasa Indonesia.
     */
    private const INDONESIAN_MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * Bangun data terstruktur untuk laporan mutasi siswa sesuai layout PDF resmi.
     *
     * @return array<string, mixed>
     */
    public function getReportData(int $schoolYearId, ?int $month = null, ?int $year = null): array
    {
        $schoolYear = SchoolYear::findOrFail($schoolYearId);

        // Jika bulan/tahun tidak dispesifikasi, default ke bulan/tahun saat ini
        $now = Carbon::now();
        $targetMonth = $month ?: (int) $now->format('n');
        $targetYear = $year ?: (int) $now->format('Y');

        $monthName = self::INDONESIAN_MONTHS[$targetMonth] ?? 'November';
        $periodLabel = "{$monthName} {$targetYear}";

        // Profil Sekolah
        $profileData = $this->getSchoolProfileData();

        // 1. Data Mutasi Masuk
        $inQuery = StudentMutation::query()
            ->where('school_year_id', $schoolYear->id)
            ->where('type', StudentMutation::TYPE_IN)
            ->where('status', StudentMutation::STATUS_ACTIVE)
            ->with([
                'student:id,name,nis,gender_id',
                'student.gender:id,name',
                'schoolRombel:id,name',
                'school:id,npsn,name',
            ]);

        if ($targetMonth && $targetYear) {
            $inQuery->whereYear('effective_date', $targetYear)
                ->whereMonth('effective_date', $targetMonth);
        }

        $mutationsInRaw = $inQuery->orderBy('effective_date')->orderBy('id')->get();
        $mutationsIn = [];
        $seq = 1;
        foreach ($mutationsInRaw as $m) {
            $genderName = (string) ($m->student?->gender?->name ?? '');
            $isFemale = str_starts_with(strtoupper($genderName), 'P') || strtoupper($genderName) === 'FEMALE';
            $mutationsIn[] = [
                'no' => $seq++,
                'nis' => (string) ($m->student?->nis ?? '-'),
                'name' => (string) ($m->student?->name ?? '-'),
                'gender' => $isFemale ? 'P' : 'L',
                'class_name' => (string) ($m->schoolRombel?->name ?? '-'),
                'from_school' => (string) ($m->school?->name ?? $m->school_npsn ?? '-'),
                'date' => $this->formatIndonesianDate($m->effective_date),
                'raw_date' => $m->effective_date ? Carbon::parse($m->effective_date)->format('Y-m-d') : null,
            ];
        }

        // 2. Data Mutasi Keluar
        $outQuery = StudentMutation::query()
            ->where('school_year_id', $schoolYear->id)
            ->where('type', StudentMutation::TYPE_OUT)
            ->where('status', StudentMutation::STATUS_ACTIVE)
            ->with([
                'student:id,name,nis,gender_id',
                'student.gender:id,name',
                'schoolRombel:id,name',
                'school:id,npsn,name',
            ]);

        if ($targetMonth && $targetYear) {
            $outQuery->whereYear('effective_date', $targetYear)
                ->whereMonth('effective_date', $targetMonth);
        }

        $mutationsOutRaw = $outQuery->orderBy('effective_date')->orderBy('id')->get();
        $mutationsOut = [];
        $seq = 1;
        foreach ($mutationsOutRaw as $m) {
            $genderName = (string) ($m->student?->gender?->name ?? '');
            $isFemale = str_starts_with(strtoupper($genderName), 'P') || strtoupper($genderName) === 'FEMALE';
            $reason = (string) ($m->school?->name ?? $m->reason ?? 'MENGUNDURKAN DIRI');
            $mutationsOut[] = [
                'no' => $seq++,
                'nis' => (string) ($m->student?->nis ?? '-'),
                'name' => (string) ($m->student?->name ?? '-'),
                'gender' => $isFemale ? 'P' : 'L',
                'class_name' => (string) ($m->schoolRombel?->name ?? '-'),
                'reason' => $reason,
                'date' => $this->formatIndonesianDate($m->effective_date),
                'raw_date' => $m->effective_date ? Carbon::parse($m->effective_date)->format('Y-m-d') : null,
            ];
        }

        // 3. Rekapitulasi Rombel (Keadaan Awal & Akhir Bulan)
        $rombels = SchoolRombel::query()
            ->where('school_year_id', $schoolYear->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($rombels->isEmpty()) {
            // Fallback ke seluruh rombel yang ada jika tahun belum memiliki rombel terikat
            $rombels = SchoolRombel::query()->orderBy('name')->get(['id', 'name']);
        }

        // Data keanggotaan aktif saat ini per rombel & gender
        $activeRows = DB::table('student_rombels')
            ->join('students', 'student_rombels.student_id', '=', 'students.id')
            ->leftJoin('genders', 'students.gender_id', '=', 'genders.id')
            ->whereIn('student_rombels.school_rombel', $rombels->pluck('id'))
            ->whereNull('student_rombels.deleted_at')
            ->whereNull('students.deleted_at')
            ->where('student_rombels.student_status_id', 1) // Status Aktif
            ->select(
                'student_rombels.school_rombel as rombel_id',
                'genders.name as gender_name',
                DB::raw('count(*) as count')
            )
            ->groupBy('student_rombels.school_rombel', 'genders.name')
            ->get();

        $rombelActiveCounts = [];
        foreach ($activeRows as $row) {
            $g = strtoupper((string) $row->gender_name);
            $key = (str_starts_with($g, 'P') || $g === 'FEMALE') ? 'P' : 'L';
            $rombelActiveCounts[$row->rombel_id][$key] = (int) $row->count;
        }

        // Mutasi Masuk per rombel & gender pada periode ini
        $inRombelCounts = [];
        foreach ($mutationsIn as $rowIn) {
            $cName = $rowIn['class_name'];
            $g = $rowIn['gender'];
            $inRombelCounts[$cName][$g] = ($inRombelCounts[$cName][$g] ?? 0) + 1;
        }

        // Mutasi Keluar per rombel & gender pada periode ini
        $outRombelCounts = [];
        foreach ($mutationsOut as $rowOut) {
            $cName = $rowOut['class_name'];
            $g = $rowOut['gender'];
            $outRombelCounts[$cName][$g] = ($outRombelCounts[$cName][$g] ?? 0) + 1;
        }

        $rombelsStart = [];
        $rombelsEnd = [];

        $totAwalL = 0;
        $totAwalP = 0;
        $totAkhirL = 0;
        $totAkhirP = 0;

        $rIndex = 1;
        foreach ($rombels as $r) {
            $rName = $r->name;
            $curL = (int) ($rombelActiveCounts[$r->id]['L'] ?? 0);
            $curP = (int) ($rombelActiveCounts[$r->id]['P'] ?? 0);

            $inL = (int) ($inRombelCounts[$rName]['L'] ?? 0);
            $inP = (int) ($inRombelCounts[$rName]['P'] ?? 0);

            $outL = (int) ($outRombelCounts[$rName]['L'] ?? 0);
            $outP = (int) ($outRombelCounts[$rName]['P'] ?? 0);

            // Akhir = Sekarang (atau setelah mutasi)
            $endL = $curL;
            $endP = $curP;
            $endTotal = $endL + $endP;

            // Awal = Akhir - Masuk + Keluar
            $startL = max(0, $endL - $inL + $outL);
            $startP = max(0, $endP - $inP + $outP);
            $startTotal = $startL + $startP;

            $totAwalL += $startL;
            $totAwalP += $startP;
            $totAkhirL += $endL;
            $totAkhirP += $endP;

            $rombelsStart[] = [
                'no' => $rIndex++,
                'rombel_id' => $r->id,
                'rombel_name' => $rName,
                'male_count' => $startL,
                'female_count' => $startP,
                'total' => $startTotal,
            ];

            $rombelsEnd[] = [
                'rombel_id' => $r->id,
                'rombel_name' => $rName,
                'male_count' => $endL,
                'female_count' => $endP,
                'total' => $endTotal,
            ];
        }

        // Summary Total Mutasi Masuk & Keluar
        $inTotalL = 0;
        $inTotalP = 0;
        foreach ($mutationsIn as $mi) {
            if ($mi['gender'] === 'P') {
                $inTotalP++;
            } else {
                $inTotalL++;
            }
        }

        $outTotalL = 0;
        $outTotalP = 0;
        foreach ($mutationsOut as $mo) {
            if ($mo['gender'] === 'P') {
                $outTotalP++;
            } else {
                $outTotalL++;
            }
        }

        return [
            'school_year' => [
                'id' => $schoolYear->id,
                'name' => $schoolYear->name,
            ],
            'period' => [
                'month' => $targetMonth,
                'year' => $targetYear,
                'month_name' => $monthName,
                'label' => $periodLabel,
            ],
            'school_profile' => $profileData,
            'mutations_in' => $mutationsIn,
            'mutations_out' => $mutationsOut,
            'rombels_start' => $rombelsStart,
            'rombels_end' => $rombelsEnd,
            'summary_in' => [
                'male_count' => $inTotalL,
                'female_count' => $inTotalP,
                'total' => $inTotalL + $inTotalP,
            ],
            'summary_out' => [
                'male_count' => $outTotalL,
                'female_count' => $outTotalP,
                'total' => $outTotalL + $outTotalP,
            ],
            'totals_start' => [
                'male_count' => $totAwalL,
                'female_count' => $totAwalP,
                'total' => $totAwalL + $totAwalP,
            ],
            'totals_end' => [
                'male_count' => $totAkhirL,
                'female_count' => $totAkhirP,
                'total' => $totAkhirL + $totAkhirP,
            ],
            'signature' => [
                'city_date' => 'Jakarta, ' . Carbon::now()->isoFormat('D MMMM Y'),
                'title' => 'Kepala ' . ($profileData['school_name'] ?? 'SMA YAPPENDA') . ',',
                'principal_name' => $profileData['principal_name'] ?? 'Wahyu Dawam Budi Utomo, M.Pd.',
            ],
        ];
    }

    /**
     * Hasilkan file Excel (.xlsx) dengan struktur dan tata letak identik dengan PDF resmi.
     */
    public function exportExcel(int $schoolYearId, ?int $month = null, ?int $year = null): string
    {
        $data = $this->getReportData($schoolYearId, $month, $year);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Mutasi Siswa');

        // Style helper
        $centerStyle = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]];
        $boldCenter = ['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]];
        $headerBg = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFEFEF']]];
        $thinBorder = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '888888'],
                ],
            ],
        ];

        // 1. KOP JUDUL
        $schoolName = $data['school_profile']['school_name'] ?? 'SMA YAPPENDA';
        $schoolYearName = $data['school_year']['name'] ?? '2025/2026';
        $periodLabel = $data['period']['label'] ?? 'November 2026';

        $sheet->mergeCells('A2:O2');
        $sheet->setCellValue('A2', "DATA MUTASI SISWA {$schoolName}");
        $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 14], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

        $sheet->mergeCells('A3:O3');
        $sheet->setCellValue('A3', "TAHUN PELAJARAN {$schoolYearName}");
        $sheet->getStyle('A3')->applyFromArray(['font' => ['bold' => true, 'size' => 12], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

        $sheet->setCellValue('A5', "Bulan / Tahun : {$periodLabel}");
        $sheet->getStyle('A5')->applyFromArray(['font' => ['bold' => true, 'size' => 10]]);

        // 2. TABEL MASUK & KELUAR (Header)
        // MASUK (Kolom A s.d G)
        $sheet->mergeCells('A6:G6');
        $sheet->setCellValue('A6', 'MASUK');
        $sheet->getStyle('A6')->applyFromArray($boldCenter)->applyFromArray($headerBg);

        $sheet->mergeCells('A7:B7');
        $sheet->setCellValue('A7', 'Nomor');
        $sheet->setCellValue('A8', 'Urut');
        $sheet->setCellValue('B8', 'Induk');

        $sheet->mergeCells('C7:C8');
        $sheet->setCellValue('C7', 'Nama Siswa');

        $sheet->mergeCells('D7:D8');
        $sheet->setCellValue('D7', 'L/P');

        $sheet->mergeCells('E7:E8');
        $sheet->setCellValue('E7', 'Kelas');

        $sheet->mergeCells('F7:F8');
        $sheet->setCellValue('F7', 'Pindahan Dari');

        $sheet->mergeCells('G7:G8');
        $sheet->setCellValue('G7', 'Tanggal');

        $sheet->getStyle('A7:G8')->applyFromArray($boldCenter)->applyFromArray($headerBg);

        // KELUAR (Kolom I s.d O) - Kolom H adalah jarak / pemisah
        $sheet->mergeCells('I6:O6');
        $sheet->setCellValue('I6', 'KELUAR');
        $sheet->getStyle('I6')->applyFromArray($boldCenter)->applyFromArray($headerBg);

        $sheet->mergeCells('I7:J7');
        $sheet->setCellValue('I7', 'Nomor');
        $sheet->setCellValue('I8', 'Urut');
        $sheet->setCellValue('J8', 'Induk');

        $sheet->mergeCells('K7:K8');
        $sheet->setCellValue('K7', 'Nama Siswa');

        $sheet->mergeCells('L7:L8');
        $sheet->setCellValue('L7', 'L/P');

        $sheet->mergeCells('M7:M8');
        $sheet->setCellValue('M7', 'Kelas');

        $sheet->mergeCells('N7:N8');
        $sheet->setCellValue('N7', 'Alasan Keluar');

        $sheet->mergeCells('O7:O8');
        $sheet->setCellValue('O7', 'Tanggal');

        $sheet->getStyle('I7:O8')->applyFromArray($boldCenter)->applyFromArray($headerBg);

        // 3. TABEL MASUK & KELUAR (Data Baris)
        $mutationsIn = $data['mutations_in'];
        $mutationsOut = $data['mutations_out'];
        $maxCount = max(count($mutationsIn), count($mutationsOut), 5); // Minimal 5 baris agar proporsional

        $startRow = 9;
        for ($i = 0; $i < $maxCount; $i++) {
            $currRow = $startRow + $i;

            // Masuk
            if (isset($mutationsIn[$i])) {
                $rowIn = $mutationsIn[$i];
                $sheet->setCellValue("A{$currRow}", $rowIn['no']);
                $sheet->setCellValue("B{$currRow}", $rowIn['nis']);
                $sheet->setCellValue("C{$currRow}", $rowIn['name']);
                $sheet->setCellValue("D{$currRow}", $rowIn['gender']);
                $sheet->setCellValue("E{$currRow}", $rowIn['class_name']);
                $sheet->setCellValue("F{$currRow}", $rowIn['from_school']);
                $sheet->setCellValue("G{$currRow}", $rowIn['date']);
            }

            // Keluar
            if (isset($mutationsOut[$i])) {
                $rowOut = $mutationsOut[$i];
                $sheet->setCellValue("I{$currRow}", $rowOut['no']);
                $sheet->setCellValue("J{$currRow}", $rowOut['nis']);
                $sheet->setCellValue("K{$currRow}", $rowOut['name']);
                $sheet->setCellValue("L{$currRow}", $rowOut['gender']);
                $sheet->setCellValue("M{$currRow}", $rowOut['class_name']);
                $sheet->setCellValue("N{$currRow}", $rowOut['reason']);
                $sheet->setCellValue("O{$currRow}", $rowOut['date']);
            }
        }

        $endTable1Row = $startRow + $maxCount - 1;
        $sheet->getStyle("A6:G{$endTable1Row}")->applyFromArray($thinBorder);
        $sheet->getStyle("I6:O{$endTable1Row}")->applyFromArray($thinBorder);
        $sheet->getStyle("A9:B{$endTable1Row}")->applyFromArray($centerStyle);
        $sheet->getStyle("D9:E{$endTable1Row}")->applyFromArray($centerStyle);
        $sheet->getStyle("G9:G{$endTable1Row}")->applyFromArray($centerStyle);
        $sheet->getStyle("I9:J{$endTable1Row}")->applyFromArray($centerStyle);
        $sheet->getStyle("L9:M{$endTable1Row}")->applyFromArray($centerStyle);
        $sheet->getStyle("O9:O{$endTable1Row}")->applyFromArray($centerStyle);

        // 4. REKAPITULASI BAWAH
        $recapStartRow = $endTable1Row + 3;

        // Block 1: KEADAAN AWAL BULAN (A s.d E)
        $sheet->mergeCells("A{$recapStartRow}:E{$recapStartRow}");
        $sheet->setCellValue("A{$recapStartRow}", 'KEADAAN AWAL BULAN');
        $sheet->getStyle("A{$recapStartRow}")->applyFromArray($boldCenter)->applyFromArray($headerBg);

        $hRow1 = $recapStartRow + 1;
        $hRow2 = $recapStartRow + 2;

        $sheet->mergeCells("A{$hRow1}:A{$hRow2}");
        $sheet->setCellValue("A{$hRow1}", 'No');

        $sheet->mergeCells("B{$hRow1}:B{$hRow2}");
        $sheet->setCellValue("B{$hRow1}", 'Rombel');

        $sheet->mergeCells("C{$hRow1}:D{$hRow1}");
        $sheet->setCellValue("C{$hRow1}", 'Kelamin');
        $sheet->setCellValue("C{$hRow2}", 'L');
        $sheet->setCellValue("D{$hRow2}", 'P');

        $sheet->mergeCells("E{$hRow1}:E{$hRow2}");
        $sheet->setCellValue("E{$hRow1}", 'Jumlah');

        $sheet->getStyle("A{$hRow1}:E{$hRow2}")->applyFromArray($boldCenter)->applyFromArray($headerBg);

        // Block 2: KEADAAN AKHIR BULAN (G s.d J)
        $sheet->mergeCells("G{$recapStartRow}:J{$recapStartRow}");
        $sheet->setCellValue("G{$recapStartRow}", 'KEADAAN AKHIR BULAN');
        $sheet->getStyle("G{$recapStartRow}")->applyFromArray($boldCenter)->applyFromArray($headerBg);

        $sheet->mergeCells("G{$hRow1}:G{$hRow2}");
        $sheet->setCellValue("G{$hRow1}", 'Rombel');

        $sheet->mergeCells("H{$hRow1}:I{$hRow1}");
        $sheet->setCellValue("H{$hRow1}", 'Kelamin');
        $sheet->setCellValue("H{$hRow2}", 'L');
        $sheet->setCellValue("I{$hRow2}", 'P');

        $sheet->mergeCells("J{$hRow1}:J{$hRow2}");
        $sheet->setCellValue("J{$hRow1}", 'Jumlah');

        $sheet->getStyle("G{$hRow1}:J{$hRow2}")->applyFromArray($boldCenter)->applyFromArray($headerBg);

        // Block 3: MUTASI MASUK (L s.d N)
        $sheet->mergeCells("L{$recapStartRow}:N{$recapStartRow}");
        $sheet->setCellValue("L{$recapStartRow}", 'MUTASI MASUK');
        $sheet->getStyle("L{$recapStartRow}")->applyFromArray($boldCenter)->applyFromArray($headerBg);

        $sheet->mergeCells("L{$hRow1}:M{$hRow1}");
        $sheet->setCellValue("L{$hRow1}", 'Kelamin');
        $sheet->setCellValue("L{$hRow2}", 'L');
        $sheet->setCellValue("M{$hRow2}", 'P');

        $sheet->mergeCells("N{$hRow1}:N{$hRow2}");
        $sheet->setCellValue("N{$hRow1}", 'Jumlah');

        $sheet->getStyle("L{$hRow1}:N{$hRow2}")->applyFromArray($boldCenter)->applyFromArray($headerBg);

        // Block 4: MUTASI KELUAR (P s.d R)
        $sheet->mergeCells("P{$recapStartRow}:R{$recapStartRow}");
        $sheet->setCellValue("P{$recapStartRow}", 'MUTASI KELUAR');
        $sheet->getStyle("P{$recapStartRow}")->applyFromArray($boldCenter)->applyFromArray($headerBg);

        $sheet->mergeCells("P{$hRow1}:Q{$hRow1}");
        $sheet->setCellValue("P{$hRow1}", 'Kelamin');
        $sheet->setCellValue("P{$hRow2}", 'L');
        $sheet->setCellValue("Q{$hRow2}", 'P');

        $sheet->mergeCells("R{$hRow1}:R{$hRow2}");
        $sheet->setCellValue("R{$hRow1}", 'Jumlah');

        $sheet->getStyle("P{$hRow1}:R{$hRow2}")->applyFromArray($boldCenter)->applyFromArray($headerBg);

        // Isi Rombel & Rekapitulasi
        $rombelsStart = $data['rombels_start'];
        $rombelsEnd = $data['rombels_end'];
        $rDataStart = $recapStartRow + 3;

        $rCount = count($rombelsStart);
        for ($i = 0; $i < $rCount; $i++) {
            $rRow = $rDataStart + $i;
            $rs = $rombelsStart[$i];
            $re = $rombelsEnd[$i];

            // Awal
            $sheet->setCellValue("A{$rRow}", $rs['no']);
            $sheet->setCellValue("B{$rRow}", $rs['rombel_name']);
            $sheet->setCellValue("C{$rRow}", $rs['male_count']);
            $sheet->setCellValue("D{$rRow}", $rs['female_count']);
            $sheet->setCellValue("E{$rRow}", $rs['total']);

            // Akhir
            $sheet->setCellValue("G{$rRow}", $re['rombel_name']);
            $sheet->setCellValue("H{$rRow}", $re['male_count']);
            $sheet->setCellValue("I{$rRow}", $re['female_count']);
            $sheet->setCellValue("J{$rRow}", $re['total']);

            // Mutasi Masuk & Keluar baris 1 (hanya pada baris pertama di blok mutasi)
            if ($i === 0) {
                $sheet->setCellValue("L{$rRow}", $data['summary_in']['male_count']);
                $sheet->setCellValue("M{$rRow}", $data['summary_in']['female_count']);
                $sheet->setCellValue("N{$rRow}", $data['summary_in']['total']);

                $sheet->setCellValue("P{$rRow}", $data['summary_out']['male_count']);
                $sheet->setCellValue("Q{$rRow}", $data['summary_out']['female_count']);
                $sheet->setCellValue("R{$rRow}", $data['summary_out']['total']);
            }
        }

        // Baris Total Jumlah Rombel
        $totalRow = $rDataStart + $rCount;
        $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'Jumlah');
        $sheet->setCellValue("C{$totalRow}", $data['totals_start']['male_count']);
        $sheet->setCellValue("D{$totalRow}", $data['totals_start']['female_count']);
        $sheet->setCellValue("E{$totalRow}", $data['totals_start']['total']);

        $sheet->setCellValue("G{$totalRow}", 'Jumlah');
        $sheet->setCellValue("H{$totalRow}", $data['totals_end']['male_count']);
        $sheet->setCellValue("I{$totalRow}", $data['totals_end']['female_count']);
        $sheet->setCellValue("J{$totalRow}", $data['totals_end']['total']);

        $sheet->getStyle("A{$totalRow}:E{$totalRow}")->applyFromArray(['font' => ['bold' => true]]);
        $sheet->getStyle("G{$totalRow}:J{$totalRow}")->applyFromArray(['font' => ['bold' => true]]);

        $sheet->getStyle("A{$recapStartRow}:E{$totalRow}")->applyFromArray($thinBorder);
        $sheet->getStyle("G{$recapStartRow}:J{$totalRow}")->applyFromArray($thinBorder);
        $sheet->getStyle("L{$recapStartRow}:N" . ($rDataStart))->applyFromArray($thinBorder);
        $sheet->getStyle("P{$recapStartRow}:R" . ($rDataStart))->applyFromArray($thinBorder);

        $sheet->getStyle("A{$rDataStart}:A{$totalRow}")->applyFromArray($centerStyle);
        $sheet->getStyle("C{$rDataStart}:E{$totalRow}")->applyFromArray($centerStyle);
        $sheet->getStyle("H{$rDataStart}:J{$totalRow}")->applyFromArray($centerStyle);
        $sheet->getStyle("L{$rDataStart}:N{$rDataStart}")->applyFromArray($centerStyle);
        $sheet->getStyle("P{$rDataStart}:R{$rDataStart}")->applyFromArray($centerStyle);

        // 5. TANDA TANGAN
        $signRow = $totalRow + 3;
        $sigData = $data['signature'];

        $sheet->setCellValue("M{$signRow}", $sigData['city_date']);
        $sheet->setCellValue("M" . ($signRow + 1), $sigData['title']);
        $sheet->setCellValue("M" . ($signRow + 4), $sigData['principal_name']);
        $sheet->getStyle("M" . ($signRow + 4))->applyFromArray(['font' => ['bold' => true, 'underline' => true]]);

        // Auto width
        foreach (range('A', 'R') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return (string) ob_get_clean();
    }

    /**
     * Helper tanggal Bahasa Indonesia (e.g. "22 Juli 2024").
     */
    private function formatIndonesianDate(?string $date): string
    {
        if (! $date) {
            return '-';
        }

        try {
            $c = Carbon::parse($date);
            $day = $c->format('j');
            $m = (int) $c->format('n');
            $year = $c->format('Y');
            $monthName = self::INDONESIAN_MONTHS[$m] ?? $c->format('F');

            return "{$day} {$monthName} {$year}";
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    /**
     * Ambil identitas profil sekolah.
     *
     * @return array<string, string>
     */
    private function getSchoolProfileData(): array
    {
        $profile = SchoolProfile::with(['educationSchool.district.regency', 'principal'])->first();

        $schoolName = 'SMA YAPPENDA';
        $npsn = '20107400';
        $address = 'Jl. Swasembada Timur V No. 10 RT. 006 RW. 010';
        $district = 'Tanjung Priok';
        $regency = 'Jakarta Utara';
        $principalName = 'Wahyu Dawam Budi Utomo, M.Pd.';

        if ($profile) {
            if ($profile->educationSchool?->name) {
                $schoolName = preg_replace('/^SMAS\s+/i', 'SMA ', (string) $profile->educationSchool->name);
            }
            if ($profile->educationSchool?->npsn) {
                $npsn = (string) $profile->educationSchool->npsn;
            }
            if ($profile->educationSchool?->address) {
                $address = (string) $profile->educationSchool->address;
            }
            if ($profile->educationSchool?->district?->name) {
                $district = (string) $profile->educationSchool->district->name;
            }
            if ($profile->educationSchool?->district?->regency?->name) {
                $regency = preg_replace('/^KOTA ADM\.\s+/i', '', (string) $profile->educationSchool->district->regency->name);
            }
            if ($profile->principal?->name) {
                $principalName = (string) $profile->principal->name;
            }
        }

        return [
            'school_name' => $schoolName,
            'npsn' => $npsn,
            'address' => $address,
            'district' => $district,
            'regency' => $regency,
            'principal_name' => $principalName,
        ];
    }
}
