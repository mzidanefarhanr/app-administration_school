<?php

namespace App\Services\Reports;

use App\Models\AcademicSetting;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\Religion;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\StudentMutation;
use App\Models\StudentRombel;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolStatisticsReportService
{
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
     * Mengambil tren statistik multi-tahun (default 5 tahun ajaran terakhir).
     */
    public function getMultiYearTrends(int $limitYears = 5): array
    {
        $limitYears = max(1, min(10, $limitYears));

        $schoolYears = SchoolYear::query()
            ->orderByDesc('start_date')
            ->take($limitYears)
            ->get()
            ->reverse()
            ->values();

        $yearsData = [];
        $labels = [];
        $maleSeries = [];
        $femaleSeries = [];
        $totalStudentsSeries = [];
        $incomingMutationSeries = [];
        $outgoingMutationSeries = [];
        $teachersSeries = [];
        $staffSeries = [];

        $teacherTypeId = EmployeeType::where('name', 'Pendidik')->value('id');
        $staffTypeId = EmployeeType::where('name', 'Tenaga Kependidikan')->value('id');

        // Total PTK aktif saat ini sebagai baseline referensi
        $currentTeachers = Employee::query()
            ->whereNull('deleted_at')
            ->whereNull('exit_date')
            ->when($teacherTypeId, fn ($q) => $q->where('employee_type_id', $teacherTypeId))
            ->count();

        $currentStaff = Employee::query()
            ->whereNull('deleted_at')
            ->whereNull('exit_date')
            ->when($staffTypeId, fn ($q) => $q->where('employee_type_id', $staffTypeId))
            ->count();

        foreach ($schoolYears as $sy) {
            $yearId = $sy->id;
            $labels[] = $sy->name;

            // 1. Siswa aktif & gender pada tahun ajaran ini
            $studentRombels = StudentRombel::query()
                ->whereNull('student_rombels.deleted_at')
                ->whereNull('student_rombels.archived_at')
                ->whereNull('student_rombels.latest_student_exit_date')
                ->where(fn ($q) => $q->whereNull('student_status_id')->orWhere('student_status_id', 1))
                ->whereHas('schoolRombel', fn ($q) => $q->where('school_year_id', $yearId)->whereNull('deleted_at'))
                ->with(['student.gender'])
                ->get();

            $male = 0;
            $female = 0;
            $seenStudentIds = [];

            foreach ($studentRombels as $sr) {
                $student = $sr->student;
                if (! $student || $student->deleted_at !== null) {
                    continue;
                }

                if (isset($seenStudentIds[$student->id])) {
                    continue;
                }
                $seenStudentIds[$student->id] = true;

                $gName = strtolower((string) ($student->gender?->name ?? ''));
                if (str_contains($gName, 'laki') || str_contains($gName, 'male') || $gName === 'l') {
                    $male++;
                } else {
                    $female++;
                }
            }

            $totalStudents = $male + $female;
            $maleSeries[] = $male;
            $femaleSeries[] = $female;
            $totalStudentsSeries[] = $totalStudents;

            // 2. Rombel
            $totalRombels = SchoolRombel::query()
                ->where('school_year_id', $yearId)
                ->whereNull('deleted_at')
                ->count();

            // 3. Mutasi Masuk & Keluar pada tahun ajaran ini
            $incomingMutations = StudentMutation::query()
                ->where('school_year_id', $yearId)
                ->where('type', StudentMutation::TYPE_IN)
                ->where('status', StudentMutation::STATUS_ACTIVE)
                ->count();

            $outgoingMutations = StudentMutation::query()
                ->where('school_year_id', $yearId)
                ->where('type', StudentMutation::TYPE_OUT)
                ->where('status', StudentMutation::STATUS_ACTIVE)
                ->count();

            $incomingMutationSeries[] = $incomingMutations;
            $outgoingMutationSeries[] = $outgoingMutations;

            // 4. Perkiraan PTK
            $teachersSeries[] = $currentTeachers;
            $staffSeries[] = $currentStaff;

            $yearsData[] = [
                'school_year_id' => $sy->id,
                'school_year_name' => $sy->name,
                'start_date' => $sy->start_date?->toDateString() ?? $sy->start_date,
                'end_date' => $sy->end_date?->toDateString() ?? $sy->end_date,
                'is_locked' => $sy->locked_at !== null,
                'total_students' => $totalStudents,
                'male_students' => $male,
                'female_students' => $female,
                'total_rombels' => $totalRombels,
                'incoming_mutations' => $incomingMutations,
                'outgoing_mutations' => $outgoingMutations,
                'total_teachers' => $currentTeachers,
                'total_staff' => $currentStaff,
            ];
        }

        return [
            'years' => $yearsData,
            'charts' => [
                'labels' => $labels,
                'students' => [
                    'male' => $maleSeries,
                    'female' => $femaleSeries,
                    'total' => $totalStudentsSeries,
                ],
                'mutations' => [
                    'incoming' => $incomingMutationSeries,
                    'outgoing' => $outgoingMutationSeries,
                ],
                'employees' => [
                    'teachers' => $teachersSeries,
                    'staff' => $staffSeries,
                ],
            ],
        ];
    }

    /**
     * Mengambil statistik demografi siswa pada tahun ajaran tertentu.
     */
    public function getDemographics(?int $schoolYearId = null): array
    {
        $yearId = $this->resolveSchoolYearId($schoolYearId);
        $schoolYear = $yearId ? SchoolYear::find($yearId) : null;

        if (! $yearId) {
            return [
                'school_year' => null,
                'gender' => ['male' => 0, 'female' => 0, 'total' => 0, 'male_percent' => 0, 'female_percent' => 0],
                'religion' => [],
                'levels' => [],
                'rombels' => [],
                'age_groups' => [],
            ];
        }

        $studentRombels = StudentRombel::query()
            ->whereNull('student_rombels.deleted_at')
            ->whereNull('student_rombels.archived_at')
            ->whereNull('student_rombels.latest_student_exit_date')
            ->where(fn ($q) => $q->whereNull('student_status_id')->orWhere('student_status_id', 1))
            ->whereHas('schoolRombel', fn ($q) => $q->where('school_year_id', $yearId)->whereNull('deleted_at'))
            ->with(['student.gender', 'student.religion', 'schoolRombel.schoolLevel'])
            ->get();

        $male = 0;
        $female = 0;
        $religionCounts = [];
        $levelCounts = [];
        $rombelStats = [];
        $ageGroups = [
            '< 15' => 0,
            '15' => 0,
            '16' => 0,
            '17' => 0,
            '18' => 0,
            '> 18' => 0,
            'Tidak Diketahui' => 0,
        ];

        $seenStudentIds = [];

        foreach ($studentRombels as $sr) {
            $student = $sr->student;
            if (! $student || $student->deleted_at !== null) {
                continue;
            }

            $rombel = $sr->schoolRombel;
            $rombelName = $rombel?->name ?? 'Tanpa Rombel';
            $levelName = $rombel?->schoolLevel?->name ?? 'Lainnya';

            if (! isset($rombelStats[$rombelName])) {
                $rombelStats[$rombelName] = [
                    'rombel_id' => $rombel?->id,
                    'name' => $rombelName,
                    'level' => $levelName,
                    'male' => 0,
                    'female' => 0,
                    'total' => 0,
                ];
            }

            $gName = strtolower((string) ($student->gender?->name ?? ''));
            $isMale = str_contains($gName, 'laki') || str_contains($gName, 'male') || $gName === 'l';

            if ($isMale) {
                $rombelStats[$rombelName]['male']++;
            } else {
                $rombelStats[$rombelName]['female']++;
            }
            $rombelStats[$rombelName]['total']++;

            // Hitung agregat unik per siswa
            if (isset($seenStudentIds[$student->id])) {
                continue;
            }
            $seenStudentIds[$student->id] = true;

            if ($isMale) {
                $male++;
            } else {
                $female++;
            }

            // Agama
            $rName = $student->religion?->name ?? 'Lainnya / Tidak Diisi';
            $religionCounts[$rName] = ($religionCounts[$rName] ?? 0) + 1;

            // Tingkat kelas
            $levelCounts[$levelName] = ($levelCounts[$levelName] ?? 0) + 1;

            // Umur
            $age = $student->calculated_age;
            if ($age === null) {
                $ageGroups['Tidak Diketahui']++;
            } elseif ($age < 15) {
                $ageGroups['< 15']++;
            } elseif ($age === 15) {
                $ageGroups['15']++;
            } elseif ($age === 16) {
                $ageGroups['16']++;
            } elseif ($age === 17) {
                $ageGroups['17']++;
            } elseif ($age === 18) {
                $ageGroups['18']++;
            } else {
                $ageGroups['> 18']++;
            }
        }

        $totalStudents = $male + $female;
        $malePercent = $totalStudents > 0 ? round(($male / $totalStudents) * 100, 1) : 0.0;
        $femalePercent = $totalStudents > 0 ? round(($female / $totalStudents) * 100, 1) : 0.0;

        // Susun dataset religion
        $religionList = [];
        foreach ($religionCounts as $rName => $cnt) {
            $religionList[] = [
                'name' => $rName,
                'count' => $cnt,
                'percentage' => $totalStudents > 0 ? round(($cnt / $totalStudents) * 100, 1) : 0.0,
            ];
        }

        // Susun dataset levels
        $levelList = [];
        foreach ($levelCounts as $lName => $cnt) {
            $levelList[] = [
                'name' => $lName,
                'count' => $cnt,
                'percentage' => $totalStudents > 0 ? round(($cnt / $totalStudents) * 100, 1) : 0.0,
            ];
        }

        return [
            'school_year' => [
                'id' => $schoolYear?->id,
                'name' => $schoolYear?->name,
                'is_locked' => $schoolYear?->locked_at !== null,
            ],
            'gender' => [
                'male' => $male,
                'female' => $female,
                'total' => $totalStudents,
                'male_percent' => $malePercent,
                'female_percent' => $femalePercent,
            ],
            'religion' => $religionList,
            'levels' => $levelList,
            'rombels' => array_values($rombelStats),
            'age_groups' => $ageGroups,
        ];
    }

    /**
     * Mengambil ringkasan kesiapan & kelengkapan data Dapodik untuk siswa dan PTK.
     */
    public function getDapodikReadiness(?int $schoolYearId = null): array
    {
        $yearId = $this->resolveSchoolYearId($schoolYearId);
        $schoolYear = $yearId ? SchoolYear::find($yearId) : null;

        if (! $yearId) {
            return [
                'school_year' => null,
                'students' => [
                    'total' => 0,
                    'readiness_percent' => 0.0,
                    'valid_nik' => 0,
                    'valid_nisn' => 0,
                    'has_kk' => 0,
                    'has_mother_name' => 0,
                    'has_birth_data' => 0,
                ],
                'employees' => [
                    'total' => 0,
                    'readiness_percent' => 0.0,
                    'valid_nik' => 0,
                    'teachers_with_nuptk' => 0,
                    'teachers_with_subject' => 0,
                ],
            ];
        }

        // 1. Siswa
        $studentRombels = StudentRombel::query()
            ->whereNull('student_rombels.deleted_at')
            ->whereNull('student_rombels.archived_at')
            ->whereNull('student_rombels.latest_student_exit_date')
            ->where(fn ($q) => $q->whereNull('student_status_id')->orWhere('student_status_id', 1))
            ->whereHas('schoolRombel', fn ($q) => $q->where('school_year_id', $yearId)->whereNull('deleted_at'))
            ->with('student')
            ->get();

        $seenStudentIds = [];
        $totalStudents = 0;
        $validNikCount = 0;
        $validNisnCount = 0;
        $hasKkCount = 0;
        $hasMotherNameCount = 0;
        $hasBirthDataCount = 0;
        $fullyReadyStudents = 0;

        foreach ($studentRombels as $sr) {
            $student = $sr->student;
            if (! $student || $student->deleted_at !== null) {
                continue;
            }

            if (isset($seenStudentIds[$student->id])) {
                continue;
            }
            $seenStudentIds[$student->id] = true;
            $totalStudents++;

            $nikValid = ! empty($student->nik) && preg_match('/^\d{16}$/', (string) $student->nik);
            $nisnValid = ! empty($student->nisn) && preg_match('/^\d{10}$/', (string) $student->nisn);
            $kkValid = ! empty($student->kk);
            $motherValid = ! empty($student->mother_name);
            $birthValid = ! empty($student->birthplace) && ! empty($student->birthdate);

            if ($nikValid) {
                $validNikCount++;
            }
            if ($nisnValid) {
                $validNisnCount++;
            }
            if ($kkValid) {
                $hasKkCount++;
            }
            if ($motherValid) {
                $hasMotherNameCount++;
            }
            if ($birthValid) {
                $hasBirthDataCount++;
            }

            if ($nikValid && $nisnValid && $motherValid && $birthValid) {
                $fullyReadyStudents++;
            }
        }

        $studentReadiness = $totalStudents > 0
            ? round(($fullyReadyStudents / $totalStudents) * 100, 1)
            : 0.0;

        // 2. Pegawai (PTK)
        $teacherTypeId = EmployeeType::where('name', 'Pendidik')->value('id');

        $activeEmployees = Employee::query()
            ->whereNull('deleted_at')
            ->whereNull('exit_date')
            ->get();

        $totalEmployees = $activeEmployees->count();
        $employeeValidNik = 0;
        $teachersTotal = 0;
        $teachersWithNuptk = 0;
        $teachersWithSubject = 0;
        $fullyReadyEmployees = 0;

        foreach ($activeEmployees as $emp) {
            $nikValid = ! empty($emp->nik) && preg_match('/^\d{16}$/', (string) $emp->nik);
            if ($nikValid) {
                $employeeValidNik++;
            }

            $isTeacher = $teacherTypeId && (int) $emp->employee_type_id === (int) $teacherTypeId;
            if ($isTeacher) {
                $teachersTotal++;
                $hasNuptk = ! empty($emp->nuptk);
                $hasSubject = ! empty($emp->subject_id);

                if ($hasNuptk) {
                    $teachersWithNuptk++;
                }
                if ($hasSubject) {
                    $teachersWithSubject++;
                }

                if ($nikValid && $hasNuptk && $hasSubject) {
                    $fullyReadyEmployees++;
                }
            } else {
                if ($nikValid) {
                    $fullyReadyEmployees++;
                }
            }
        }

        $employeeReadiness = $totalEmployees > 0
            ? round(($fullyReadyEmployees / $totalEmployees) * 100, 1)
            : 0.0;

        return [
            'school_year' => [
                'id' => $schoolYear?->id,
                'name' => $schoolYear?->name,
                'is_locked' => $schoolYear?->locked_at !== null,
            ],
            'students' => [
                'total' => $totalStudents,
                'fully_ready' => $fullyReadyStudents,
                'readiness_percent' => $studentReadiness,
                'valid_nik' => $validNikCount,
                'invalid_or_missing_nik' => $totalStudents - $validNikCount,
                'valid_nisn' => $validNisnCount,
                'invalid_or_missing_nisn' => $totalStudents - $validNisnCount,
                'has_kk' => $hasKkCount,
                'missing_kk' => $totalStudents - $hasKkCount,
                'has_mother_name' => $hasMotherNameCount,
                'missing_mother_name' => $totalStudents - $hasMotherNameCount,
                'has_birth_data' => $hasBirthDataCount,
                'missing_birth_data' => $totalStudents - $hasBirthDataCount,
            ],
            'employees' => [
                'total' => $totalEmployees,
                'fully_ready' => $fullyReadyEmployees,
                'readiness_percent' => $employeeReadiness,
                'valid_nik' => $employeeValidNik,
                'invalid_or_missing_nik' => $totalEmployees - $employeeValidNik,
                'teachers_total' => $teachersTotal,
                'teachers_with_nuptk' => $teachersWithNuptk,
                'teachers_missing_nuptk' => $teachersTotal - $teachersWithNuptk,
                'teachers_with_subject' => $teachersWithSubject,
                'teachers_missing_subject' => $teachersTotal - $teachersWithSubject,
            ],
        ];
    }

    /**
     * Ekspor CSV statistik multi-tahun dengan BOM UTF-8.
     */
    public function exportMultiYearTrendsCsv(int $limitYears = 5): StreamedResponse
    {
        $trends = $this->getMultiYearTrends($limitYears);
        $filename = 'rekap_statistik_sekolah_'.Carbon::now('Asia/Jakarta')->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-cache, private',
        ];

        return new StreamedResponse(function () use ($trends) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM
            fwrite($handle, "\xEF\xBB\xBF");

            // Header kolom
            fputcsv($handle, [
                'Tahun Ajaran',
                'Status Periode',
                'Tanggal Mulai',
                'Tanggal Selesai',
                'Total Siswa Aktif',
                'Siswa Laki-laki',
                'Siswa Perempuan',
                'Total Rombel',
                'Mutasi Masuk',
                'Mutasi Keluar',
                'Total Guru (Pendidik)',
                'Total Tendik',
            ]);

            foreach ($trends['years'] as $row) {
                fputcsv($handle, [
                    $row['school_year_name'],
                    $row['is_locked'] ? 'Terkunci' : 'Aktif',
                    $row['start_date'],
                    $row['end_date'],
                    $row['total_students'],
                    $row['male_students'],
                    $row['female_students'],
                    $row['total_rombels'],
                    $row['incoming_mutations'],
                    $row['outgoing_mutations'],
                    $row['total_teachers'],
                    $row['total_staff'],
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
