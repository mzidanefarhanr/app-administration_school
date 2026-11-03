<?php

namespace App\Services\Reports;

use App\Models\AcademicSetting;
use App\Models\SchoolYear;
use App\Models\StudentRombel;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DapodikReconciliationService
{
    public const STATUS_IDENTICAL = 'MATCHED_IDENTICAL';

    public const STATUS_DIFF = 'MATCHED_DIFF';

    public const STATUS_LOCAL_ONLY = 'LOCAL_ONLY';

    public const STATUS_DAPODIK_ONLY = 'DAPODIK_ONLY';

    /**
     * Resolusi ID tahun ajaran aktif atau default.
     */
    public function resolveSchoolYearId(?int $schoolYearId = null): ?int
    {
        if ($schoolYearId) {
            return $schoolYearId;
        }

        $setting = AcademicSetting::find(AcademicSetting::SINGLETON_ID);
        if ($setting?->active_school_year_id) {
            return (int) $setting->active_school_year_id;
        }

        return SchoolYear::orderByDesc('start_date')->value('id');
    }

    /**
     * Melakukan komparasi rekonsiliasi antara berkas CSV Dapodik dan database sekolah lokal.
     *
     * @throws ValidationException
     */
    public function reconcileCsv(UploadedFile $file, ?int $schoolYearId = null): array
    {
        $yearId = $this->resolveSchoolYearId($schoolYearId);
        $schoolYear = $yearId ? SchoolYear::find($yearId) : null;

        $path = $file->getRealPath();
        if (! $path || ! file_exists($path)) {
            throw ValidationException::withMessages(['file' => 'Berkas CSV tidak dapat dibaca dari sistem.']);
        }

        // 1. Deteksi delimiter & baca header
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'Gagal membuka berkas CSV.']);
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'Berkas CSV kosong.']);
        }

        // Cek delimiter yang dominan
        $delimiters = [',', ';', "\t"];
        $delimiter = ',';
        $maxCount = 0;
        foreach ($delimiters as $delim) {
            $cnt = substr_count($firstLine, $delim);
            if ($cnt > $maxCount) {
                $maxCount = $cnt;
                $delimiter = $delim;
            }
        }

        rewind($handle);
        $headerRow = fgetcsv($handle, 0, $delimiter);
        if (! $headerRow || empty(array_filter($headerRow))) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'Baris judul (header) CSV tidak valid.']);
        }

        // Pemetaan indeks kolom header
        $headerMap = [
            'nisn' => null,
            'nik' => null,
            'name' => null,
            'rombel' => null,
            'gender' => null,
        ];

        foreach ($headerRow as $idx => $rawHeader) {
            $h = strtolower(trim((string) $rawHeader));
            $h = preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-', '.'], '_', $h));

            if (str_contains($h, 'nisn')) {
                $headerMap['nisn'] = $idx;
            } elseif (str_contains($h, 'nik') || str_contains($h, 'ktp')) {
                $headerMap['nik'] = $idx;
            } elseif (str_contains($h, 'nama') || $h === 'name') {
                $headerMap['name'] = $idx;
            } elseif (str_contains($h, 'rombel') || str_contains($h, 'rombongan') || str_contains($h, 'kelas')) {
                $headerMap['rombel'] = $idx;
            } elseif (str_contains($h, 'jk') || str_contains($h, 'gender') || str_contains($h, 'kelamin')) {
                $headerMap['gender'] = $idx;
            }
        }

        if ($headerMap['name'] === null && $headerMap['nisn'] === null) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'Berkas CSV tidak memuat kolom Nama atau NISN yang dikenali. Pastikan berkas berasal dari ekspor Dapodik atau memiliki header Nama/NISN.',
            ]);
        }

        // 2. Baca baris data (maksimal 2.000 baris)
        $dapodikRows = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }

            $rowNum++;
            if ($rowNum > 2001) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => 'Ukuran berkas melebihi batas 2.000 baris data.']);
            }

            $name = $headerMap['name'] !== null ? trim((string) ($row[$headerMap['name']] ?? '')) : '';
            $nisn = $headerMap['nisn'] !== null ? trim((string) ($row[$headerMap['nisn']] ?? '')) : '';
            $nik = $headerMap['nik'] !== null ? trim((string) ($row[$headerMap['nik']] ?? '')) : '';
            $rombel = $headerMap['rombel'] !== null ? trim((string) ($row[$headerMap['rombel']] ?? '')) : '';
            $gender = $headerMap['gender'] !== null ? trim((string) ($row[$headerMap['gender']] ?? '')) : '';

            $dapodikRows[] = [
                'row_index' => $rowNum,
                'name' => $name,
                'nisn' => $nisn,
                'nik' => $nik,
                'rombel' => $rombel,
                'gender' => $gender,
            ];
        }
        fclose($handle);

        // 3. Muat data siswa lokal pada tahun ajaran aktif
        $localStudentRombels = StudentRombel::query()
            ->whereNull('student_rombels.deleted_at')
            ->whereNull('student_rombels.archived_at')
            ->whereNull('student_rombels.latest_student_exit_date')
            ->where(fn ($q) => $q->whereNull('student_status_id')->orWhere('student_status_id', 1))
            ->whereHas('schoolRombel', fn ($q) => $q->where('school_year_id', $yearId)->whereNull('deleted_at'))
            ->with(['student.gender', 'schoolRombel'])
            ->get();

        $localStudentsById = [];
        $localByNisn = [];
        $localByNik = [];

        foreach ($localStudentRombels as $sr) {
            $student = $sr->student;
            if (! $student || $student->deleted_at !== null) {
                continue;
            }

            $entry = [
                'student_id' => $student->id,
                'name' => (string) $student->name,
                'nis' => (string) $student->nis,
                'nisn' => (string) $student->nisn,
                'nik' => (string) $student->nik,
                'gender' => (string) ($student->gender?->name ?? ''),
                'rombel_id' => $sr->schoolRombel?->id,
                'rombel_name' => (string) ($sr->schoolRombel?->name ?? ''),
            ];

            $localStudentsById[$student->id] = $entry;

            $cleanNisn = preg_replace('/\D/', '', (string) $student->nisn);
            if (! empty($cleanNisn)) {
                $localByNisn[$cleanNisn] = $student->id;
            }

            $cleanNik = preg_replace('/\D/', '', (string) $student->nik);
            if (! empty($cleanNik)) {
                $localByNik[$cleanNik] = $student->id;
            }
        }

        // 4. Pencocokan Dua Arah (Two-pass matching)
        $matchedLocalStudentIds = [];
        $matchedDapodikIndices = [];
        $items = [];

        $totalIdentical = 0;
        $totalDiff = 0;

        foreach ($dapodikRows as $dIndex => $dRow) {
            $cleanDapodikNisn = preg_replace('/\D/', '', $dRow['nisn']);
            $cleanDapodikNik = preg_replace('/\D/', '', $dRow['nik']);

            $matchedLocalId = null;

            // Pass 1: Pencocokan NISN
            if (! empty($cleanDapodikNisn) && isset($localByNisn[$cleanDapodikNisn])) {
                $matchedLocalId = $localByNisn[$cleanDapodikNisn];
            }

            // Pass 2: Pencocokan NIK
            if (! $matchedLocalId && ! empty($cleanDapodikNik) && isset($localByNik[$cleanDapodikNik])) {
                $matchedLocalId = $localByNik[$cleanDapodikNik];
            }

            if ($matchedLocalId && isset($localStudentsById[$matchedLocalId])) {
                $local = $localStudentsById[$matchedLocalId];
                $matchedLocalStudentIds[$matchedLocalId] = true;
                $matchedDapodikIndices[$dIndex] = true;

                // Hitung perbedaan atribut
                $differences = [];

                // Perbedaan nama (case-insensitive & whitespace normalized)
                $normLocalName = strtolower(preg_replace('/\s+/', ' ', trim($local['name'])));
                $normDapodikName = strtolower(preg_replace('/\s+/', ' ', trim($dRow['name'])));
                if ($normLocalName !== $normDapodikName) {
                    $differences[] = [
                        'field' => 'name',
                        'label' => 'Nama Siswa',
                        'local' => $local['name'],
                        'dapodik' => $dRow['name'],
                    ];
                }

                // Perbedaan rombel
                if (! empty($dRow['rombel'])) {
                    $normLocalRombel = strtolower(preg_replace('/\s+/', '', $local['rombel_name']));
                    $normDapodikRombel = strtolower(preg_replace('/\s+/', '', $dRow['rombel']));
                    if ($normLocalRombel !== $normDapodikRombel) {
                        $differences[] = [
                            'field' => 'rombel',
                            'label' => 'Rombongan Belajar',
                            'local' => $local['rombel_name'],
                            'dapodik' => $dRow['rombel'],
                        ];
                    }
                }

                // Perbedaan NISN (jika dicocokkan via NIK)
                if (! empty($dRow['nisn']) && $cleanDapodikNisn !== preg_replace('/\D/', '', $local['nisn'])) {
                    $differences[] = [
                        'field' => 'nisn',
                        'label' => 'NISN',
                        'local' => $local['nisn'],
                        'dapodik' => $dRow['nisn'],
                    ];
                }

                // Perbedaan NIK (jika dicocokkan via NISN)
                if (! empty($dRow['nik']) && $cleanDapodikNik !== preg_replace('/\D/', '', $local['nik'])) {
                    $differences[] = [
                        'field' => 'nik',
                        'label' => 'NIK',
                        'local' => $local['nik'],
                        'dapodik' => $dRow['nik'],
                    ];
                }

                $isIdentical = empty($differences);
                if ($isIdentical) {
                    $totalIdentical++;
                    $status = self::STATUS_IDENTICAL;
                    $statusLabel = 'Cocok Identik';
                } else {
                    $totalDiff++;
                    $status = self::STATUS_DIFF;
                    $statusLabel = 'Beda Data';
                }

                $items[] = [
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'student_id' => $local['student_id'],
                    'local_name' => $local['name'],
                    'dapodik_name' => $dRow['name'],
                    'local_nisn' => $local['nisn'],
                    'dapodik_nisn' => $dRow['nisn'],
                    'local_nik' => $local['nik'],
                    'dapodik_nik' => $dRow['nik'],
                    'local_rombel' => $local['rombel_name'],
                    'dapodik_rombel' => $dRow['rombel'],
                    'differences' => $differences,
                ];
            }
        }

        // Unmatched Dapodik rows -> DAPODIK_ONLY
        $totalDapodikOnly = 0;
        foreach ($dapodikRows as $dIndex => $dRow) {
            if (! isset($matchedDapodikIndices[$dIndex])) {
                $totalDapodikOnly++;
                $items[] = [
                    'status' => self::STATUS_DAPODIK_ONLY,
                    'status_label' => 'Hanya di Dapodik',
                    'student_id' => null,
                    'local_name' => '-',
                    'dapodik_name' => $dRow['name'],
                    'local_nisn' => '-',
                    'dapodik_nisn' => $dRow['nisn'],
                    'local_nik' => '-',
                    'dapodik_nik' => $dRow['nik'],
                    'local_rombel' => '-',
                    'dapodik_rombel' => $dRow['rombel'],
                    'differences' => [
                        [
                            'field' => 'record',
                            'label' => 'Status Data',
                            'local' => 'Tidak Terdata di Sekolah',
                            'dapodik' => 'Terdaftar di Berkas Dapodik',
                        ],
                    ],
                ];
            }
        }

        // Unmatched Local students -> LOCAL_ONLY
        $totalLocalOnly = 0;
        foreach ($localStudentsById as $sId => $local) {
            if (! isset($matchedLocalStudentIds[$sId])) {
                $totalLocalOnly++;
                $items[] = [
                    'status' => self::STATUS_LOCAL_ONLY,
                    'status_label' => 'Hanya di Lokal',
                    'student_id' => $local['student_id'],
                    'local_name' => $local['name'],
                    'dapodik_name' => '-',
                    'local_nisn' => $local['nisn'],
                    'dapodik_nisn' => '-',
                    'local_nik' => $local['nik'],
                    'dapodik_nik' => '-',
                    'local_rombel' => $local['rombel_name'],
                    'dapodik_rombel' => '-',
                    'differences' => [
                        [
                            'field' => 'record',
                            'label' => 'Status Data',
                            'local' => 'Aktif di Sekolah Lokal',
                            'dapodik' => 'Tidak Ditemukan di Berkas Dapodik',
                        ],
                    ],
                ];
            }
        }

        // Urutkan: MATCHED_DIFF dulu, lalu LOCAL_ONLY, lalu DAPODIK_ONLY, lalu MATCHED_IDENTICAL
        $statusOrder = [
            self::STATUS_DIFF => 1,
            self::STATUS_LOCAL_ONLY => 2,
            self::STATUS_DAPODIK_ONLY => 3,
            self::STATUS_IDENTICAL => 4,
        ];

        usort($items, function ($a, $b) use ($statusOrder) {
            $orderA = $statusOrder[$a['status']] ?? 99;
            $orderB = $statusOrder[$b['status']] ?? 99;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return strcasecmp((string) ($a['local_name'] ?: $a['dapodik_name']), (string) ($b['local_name'] ?: $b['dapodik_name']));
        });

        $totalMatched = $totalIdentical + $totalDiff;

        return [
            'summary' => [
                'school_year' => [
                    'id' => $schoolYear?->id,
                    'name' => $schoolYear?->name,
                ],
                'total_dapodik_rows' => count($dapodikRows),
                'total_local_students' => count($localStudentsById),
                'total_matched' => $totalMatched,
                'total_identical' => $totalIdentical,
                'total_diff' => $totalDiff,
                'total_local_only' => $totalLocalOnly,
                'total_dapodik_only' => $totalDapodikOnly,
            ],
            'items' => $items,
        ];
    }

    /**
     * Ekspor CSV hasil rekonsiliasi Dapodik dengan BOM UTF-8.
     */
    public function exportReconciliationFromUpload(UploadedFile $file, ?int $schoolYearId = null): StreamedResponse
    {
        $result = $this->reconcileCsv($file, $schoolYearId);
        $filename = 'hasil_rekonsiliasi_dapodik_'.Carbon::now('Asia/Jakarta')->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-cache, private',
        ];

        return new StreamedResponse(function () use ($result) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($handle, [
                'No',
                'Status Rekonsiliasi',
                'Nama (Sistem Lokal)',
                'Nama (Berkas Dapodik)',
                'NISN (Lokal)',
                'NISN (Dapodik)',
                'NIK (Lokal)',
                'NIK (Dapodik)',
                'Rombel (Lokal)',
                'Rombel (Dapodik)',
                'Keterangan Perbedaan',
            ]);

            $idx = 1;
            foreach ($result['items'] as $item) {
                $diffText = '';
                if (! empty($item['differences'])) {
                    $parts = [];
                    foreach ($item['differences'] as $d) {
                        $parts[] = ($d['label'] ?? $d['field']).': Lokal="'.$d['local'].'" vs Dapodik="'.$d['dapodik'].'"';
                    }
                    $diffText = implode('; ', $parts);
                }

                // Format identifier angka dengan kutip untuk mencegah Excel memotong angka nol
                $localNisn = $item['local_nisn'] !== '-' ? '="'.$item['local_nisn'].'"' : '-';
                $dapodikNisn = $item['dapodik_nisn'] !== '-' ? '="'.$item['dapodik_nisn'].'"' : '-';
                $localNik = $item['local_nik'] !== '-' ? '="'.$item['local_nik'].'"' : '-';
                $dapodikNik = $item['dapodik_nik'] !== '-' ? '="'.$item['dapodik_nik'].'"' : '-';

                fputcsv($handle, [
                    $idx++,
                    $item['status_label'],
                    $item['local_name'],
                    $item['dapodik_name'],
                    $localNisn,
                    $dapodikNisn,
                    $localNik,
                    $dapodikNik,
                    $item['local_rombel'],
                    $item['dapodik_rombel'],
                    $diffText,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
