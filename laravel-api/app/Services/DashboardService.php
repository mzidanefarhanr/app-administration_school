<?php

namespace App\Services;

use App\Models\AcademicSetting;
use App\Models\ArchiveDocument;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Letter;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TeachingSchedule;
use App\Models\User;
use App\Services\DataQuality\EmployeeCompletenessInspector;
use App\Services\DataQuality\StudentCompletenessInspector;
use Carbon\Carbon;
use Throwable;

class DashboardService
{
    public function __construct(
        private readonly TeachingReportService $teachingReportService,
        private readonly BackupMonitoringService $backupMonitoringService
    ) {}

    /**
     * Mengambil ringkasan metrik dashboard berbasis ability pengguna.
     *
     * @return array<string, mixed>
     */
    public function getOverview(User $user): array
    {
        $todayWib = Carbon::now('Asia/Jakarta')->toDateString();
        $primaryRole = $user->roles()->first()?->slug ?? ($user->typeUser?->name ?? 'User');

        $period = $this->getActivePeriod();
        $activeYear = $period['school_year'];
        $activeSemester = $period['semester'];
        $activeYearId = $activeYear?->id;
        $activeSemesterId = $activeSemester?->id;

        $canViewStudents = $user->can('students.view');
        $canViewEmployees = $user->can('employees.view');
        $canViewLetters = $user->can('letters.view');
        $canViewLeaves = $user->can('leaves.view');
        $canViewTeaching = $user->can('teaching.view');
        $canViewArchives = $user->can('archives.view');
        $canManageBackup = $user->can('backup.manage') || $user->can('users.manage');

        // 1. Academic & Student Overview
        $academicData = [
            'active_school_year' => $activeYear?->name ?? 'Belum Diatur',
            'active_semester' => $activeSemester?->name ?? 'Belum Diatur',
            'is_locked' => (bool) ($activeYear?->locked_at !== null),
            'total_active_students' => $canViewStudents ? Student::query()->count() : null,
            'total_active_rombels' => ($canViewStudents && $activeYearId)
                ? SchoolRombel::query()->where('school_year_id', $activeYearId)->count()
                : null,
        ];

        // 2. Employee / PTK Overview
        $employeeData = null;
        if ($canViewEmployees) {
            $employeeData = [
                'total_teachers' => Employee::query()
                    ->whereNull('exit_date')
                    ->whereHas('employeeType', fn ($q) => $q->where('name', 'Pendidik'))
                    ->count(),
                'total_staff' => Employee::query()
                    ->whereNull('exit_date')
                    ->whereHas('employeeType', fn ($q) => $q->where('name', 'Tenaga Kependidikan'))
                    ->count(),
            ];
        }

        // 3. Data Quality Overview
        $dataQualityData = null;
        if ($canViewStudents || $canViewEmployees) {
            $studentCompleteness = null;
            $employeeCompleteness = null;

            if ($canViewStudents && $activeYearId) {
                try {
                    $inspector = app(StudentCompletenessInspector::class);
                    $summary = $inspector->summary($activeYearId);
                    $studentCompleteness = [
                        'complete_percent' => $summary['records']['complete_percent'] ?? 0.0,
                        'incomplete_records' => $summary['records']['incomplete'] ?? 0,
                        'unresolved_issues' => $summary['reviews']['unresolved'] ?? 0,
                    ];
                } catch (Throwable) {
                    $studentCompleteness = null;
                }
            }

            if ($canViewEmployees) {
                try {
                    $inspector = app(EmployeeCompletenessInspector::class);
                    $summary = $inspector->summary();
                    $employeeCompleteness = [
                        'complete_percent' => $summary['records']['complete_percent'] ?? 0.0,
                        'incomplete_records' => $summary['records']['incomplete'] ?? 0,
                        'unresolved_issues' => $summary['reviews']['unresolved'] ?? 0,
                    ];
                } catch (Throwable) {
                    $employeeCompleteness = null;
                }
            }

            $dataQualityData = [
                'students' => $studentCompleteness,
                'employees' => $employeeCompleteness,
            ];
        }

        // 4. Letters Overview
        $lettersData = null;
        if ($canViewLetters) {
            $lettersData = [
                'incoming_unprocessed_count' => Letter::query()
                    ->where('direction', Letter::DIRECTION_INCOMING)
                    ->where('status', Letter::STATUS_ACTIVE)
                    ->whereDoesntHave('dispositions')
                    ->count(),
                'outgoing_pending_approval_count' => Letter::query()
                    ->where('direction', Letter::DIRECTION_OUTGOING)
                    ->where('status', Letter::STATUS_ACTIVE)
                    ->where('workflow_status', Letter::WORKFLOW_REVIEW)
                    ->count(),
            ];
        }

        // 5. Leaves Overview
        $leavesData = null;
        if ($canViewLeaves) {
            $leavesData = [
                'pending_requests_count' => LeaveRequest::query()
                    ->where('status', LeaveRequest::STATUS_SUBMITTED)
                    ->count(),
                'active_today_count' => LeaveRequest::query()
                    ->where('status', LeaveRequest::STATUS_APPROVED)
                    ->whereDate('start_date', '<=', $todayWib)
                    ->whereDate('end_date', '>=', $todayWib)
                    ->count(),
            ];
        } else {
            // Personal leave stats for user
            $employee = Employee::query()->where('user_id', $user->id)->first();
            $myPending = 0;
            if ($employee) {
                $myPending = LeaveRequest::query()
                    ->where('employee_id', $employee->id)
                    ->where('status', LeaveRequest::STATUS_SUBMITTED)
                    ->count();
            }
            $leavesData = [
                'my_pending_requests_count' => $myPending,
            ];
        }

        // 6. Teaching Overview
        $teachingData = null;
        if ($canViewTeaching && $activeYearId && $activeSemesterId) {
            $isLeadershipOrManage = $user->can('teaching.manage')
                || $user->hasRole('admin-sistem')
                || $user->hasRole('operator-sekolah')
                || $user->hasRole('kepala-sekolah')
                || $user->hasRole('wakil-kesiswaan');

            if ($isLeadershipOrManage) {
                // Rentang 30 hari terakhir s.d. hari ini WIB
                $dateFrom = Carbon::now('Asia/Jakarta')->subDays(30)->toDateString();
                $dateTo = $todayWib;

                try {
                    $report = $this->teachingReportService->missingJournalsReport([
                        'school_year_id' => $activeYearId,
                        'semester_id' => $activeSemesterId,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'tab' => 'summary',
                    ], $user);

                    $summary = $report['data']['summary'] ?? $report['summary'] ?? [];

                    $teachingData = [
                        'view_type' => 'school_wide',
                        'scheduled_teachers_count' => (int) ($summary['total_teachers_with_sessions'] ?? $summary['total_teachers'] ?? 0),
                        'expected_sessions_30d' => (int) ($summary['total_expected_sessions'] ?? $summary['expected_sessions'] ?? 0),
                        'recorded_journals_30d' => (int) ($summary['total_recorded_journals'] ?? $summary['recorded_journals'] ?? 0),
                        'missing_journals_30d' => (int) ($summary['total_missing_journals'] ?? $summary['missing_journals'] ?? 0),
                        'missing_within_grace_30d' => (int) ($summary['total_missing_within_grace'] ?? $summary['missing_within_grace'] ?? 0),
                        'missing_overdue_30d' => (int) ($summary['total_missing_overdue'] ?? $summary['missing_overdue'] ?? 0),
                        'compliance_rate_percent' => (float) ($summary['overall_compliance_percentage'] ?? $summary['compliance_rate'] ?? 0.0),
                    ];
                } catch (Throwable) {
                    $teachingData = [
                        'view_type' => 'school_wide',
                        'scheduled_teachers_count' => 0,
                        'expected_sessions_30d' => 0,
                        'recorded_journals_30d' => 0,
                        'missing_journals_30d' => 0,
                        'missing_within_grace_30d' => 0,
                        'missing_overdue_30d' => 0,
                        'compliance_rate_percent' => 0.0,
                    ];
                }
            } else {
                // Guru personal view
                $employee = Employee::query()->where('user_id', $user->id)->first();
                $dayOfWeek = Carbon::now('Asia/Jakarta')->dayOfWeekIso; // 1 = Monday ... 7 = Sunday

                $todaySessions = 0;
                if ($employee && $dayOfWeek <= 6) {
                    $todaySessions = TeachingSchedule::query()
                        ->where('day_of_week', $dayOfWeek)
                        ->where('status', 'active')
                        ->whereHas('assignment', function ($q) use ($employee, $activeYearId, $activeSemesterId) {
                            $q->where('employee_id', $employee->id)
                                ->where('status', 'active')
                                ->where('school_year_id', $activeYearId)
                                ->where('semester_id', $activeSemesterId);
                        })
                        ->count();
                }

                $dateFrom = Carbon::now('Asia/Jakarta')->subDays(30)->toDateString();
                $dateTo = $todayWib;

                try {
                    $report = $this->teachingReportService->missingJournalsReport([
                        'school_year_id' => $activeYearId,
                        'semester_id' => $activeSemesterId,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'scope' => 'mine',
                        'tab' => 'summary',
                    ], $user);

                    $summary = $report['data']['summary'] ?? $report['summary'] ?? [];

                    $teachingData = [
                        'view_type' => 'teacher_personal',
                        'teacher_name' => $employee?->name ?? $user->name,
                        'today_sessions_count' => $todaySessions,
                        'expected_sessions_30d' => (int) ($summary['total_expected_sessions'] ?? $summary['expected_sessions'] ?? 0),
                        'recorded_journals_30d' => (int) ($summary['total_recorded_journals'] ?? $summary['recorded_journals'] ?? 0),
                        'missing_within_grace' => (int) ($summary['total_missing_within_grace'] ?? $summary['missing_within_grace'] ?? 0),
                        'missing_overdue' => (int) ($summary['total_missing_overdue'] ?? $summary['missing_overdue'] ?? 0),
                        'compliance_rate_percent' => (float) ($summary['overall_compliance_percentage'] ?? $summary['compliance_rate'] ?? 0.0),
                    ];
                } catch (Throwable) {
                    $teachingData = [
                        'view_type' => 'teacher_personal',
                        'teacher_name' => $employee?->name ?? $user->name,
                        'today_sessions_count' => $todaySessions,
                        'expected_sessions_30d' => 0,
                        'recorded_journals_30d' => 0,
                        'missing_within_grace' => 0,
                        'missing_overdue' => 0,
                        'compliance_rate_percent' => 0.0,
                    ];
                }
            }
        }

        // 7. Archives Overview
        $archivesData = null;
        if ($canViewArchives) {
            $thresholdDate = Carbon::now('Asia/Jakarta')->addDays(30)->toDateString();
            $archivesData = [
                'expiring_soon_count' => ArchiveDocument::query()
                    ->whereNull('archived_at')
                    ->whereNotNull('valid_until')
                    ->whereBetween('valid_until', [$todayWib, $thresholdDate])
                    ->count(),
                'expired_count' => ArchiveDocument::query()
                    ->whereNull('archived_at')
                    ->whereNotNull('valid_until')
                    ->whereDate('valid_until', '<', $todayWib)
                    ->count(),
            ];
        }

        // 8. System & Backup Overview
        $systemData = null;
        if ($canManageBackup) {
            try {
                $backupHealth = $this->backupMonitoringService->getHealthStatus();
                $systemData = [
                    'last_backup_at' => $backupHealth['last_backup_at'] ?? null,
                    'backup_status' => $backupHealth['status'] ?? 'healthy',
                    'backup_count' => (int) ($backupHealth['backup_count'] ?? 0),
                    'environment' => config('app.env', 'production'),
                ];
            } catch (Throwable) {
                $systemData = [
                    'last_backup_at' => null,
                    'backup_status' => 'unknown',
                    'backup_count' => 0,
                    'environment' => config('app.env', 'production'),
                ];
            }
        }

        return [
            'academic' => $academicData,
            'employees' => $employeeData,
            'data_quality' => $dataQualityData,
            'letters' => $lettersData,
            'leaves' => $leavesData,
            'teaching' => $teachingData,
            'archives' => $archivesData,
            'system' => $systemData,
            'meta' => [
                'server_time_wib' => Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s'),
                'user_role' => $primaryRole,
                'user_name' => $user->name,
                'can' => [
                    'view_students' => $canViewStudents,
                    'view_employees' => $canViewEmployees,
                    'view_letters' => $canViewLetters,
                    'view_leaves' => $canViewLeaves,
                    'view_teaching' => $canViewTeaching,
                    'view_archives' => $canViewArchives,
                    'manage_backup' => $canManageBackup,
                ],
            ],
        ];
    }

    /**
     * Mengambil daftar tugas operasional yang membutuhkan aksi pengguna.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPendingTasks(User $user): array
    {
        $tasks = [];
        $todayWib = Carbon::now('Asia/Jakarta')->toDateString();

        $canApproveLetters = $user->can('letters.approve')
            || $user->hasRole('admin-sistem')
            || $user->hasRole('kepala-sekolah')
            || $user->hasRole('wakil-kesiswaan');

        $canManageLetters = $user->can('letters.manage')
            || $user->hasRole('admin-sistem')
            || $user->hasRole('operator-sekolah');

        $canApproveLeaves = $user->can('leaves.approve')
            || $user->hasRole('admin-sistem')
            || $user->hasRole('kepala-sekolah')
            || $user->hasRole('wakil-kesiswaan');

        $canViewTeaching = $user->can('teaching.view');

        $canManageTeaching = $user->can('teaching.manage')
            || $user->hasRole('admin-sistem')
            || $user->hasRole('operator-sekolah');

        $canViewArchives = $user->can('archives.view');

        // 1. Surat Keluar Menunggu Approval
        if ($canApproveLetters) {
            $pendingOutgoing = Letter::query()
                ->where('direction', Letter::DIRECTION_OUTGOING)
                ->where('status', Letter::STATUS_ACTIVE)
                ->where('workflow_status', Letter::WORKFLOW_REVIEW)
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($pendingOutgoing as $letter) {
                $tasks[] = [
                    'id' => 'letter-out-'.$letter->id,
                    'domain' => 'letters',
                    'category' => 'Surat Keluar Menunggu Approval',
                    'title' => $letter->letter_number ? 'No. '.$letter->letter_number.' · '.$letter->subject : $letter->subject,
                    'description' => 'Tujuan: '.($letter->recipient ?? '-'),
                    'date' => $letter->letter_date ?? $letter->created_at?->toDateString(),
                    'urgency' => 'high',
                    'action_url' => '/public/letters/outgoing',
                ];
            }
        }

        // 2. Surat Masuk Belum Didisposisi
        if ($canManageLetters) {
            $unprocessedIncoming = Letter::query()
                ->where('direction', Letter::DIRECTION_INCOMING)
                ->where('status', Letter::STATUS_ACTIVE)
                ->whereDoesntHave('dispositions')
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($unprocessedIncoming as $letter) {
                $tasks[] = [
                    'id' => 'letter-in-'.$letter->id,
                    'domain' => 'letters',
                    'category' => 'Surat Masuk Belum Disposisi',
                    'title' => ($letter->incoming_number ? 'No. '.$letter->incoming_number.' · ' : '').$letter->subject,
                    'description' => 'Pengirim: '.($letter->sender_name ?? '-'),
                    'date' => $letter->letter_date ?? $letter->created_at?->toDateString(),
                    'urgency' => 'warn',
                    'action_url' => '/public/letters/incoming',
                ];
            }
        }

        // 3. Permohonan Izin Menunggu Keputusan
        if ($canApproveLeaves) {
            $pendingLeaves = LeaveRequest::query()
                ->where('status', LeaveRequest::STATUS_SUBMITTED)
                ->with(['student', 'employee'])
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($pendingLeaves as $leave) {
                $name = $leave->student?->name ?? $leave->employee?->name ?? 'Pemohon';
                $tasks[] = [
                    'id' => 'leave-'.$leave->id,
                    'domain' => 'leaves',
                    'category' => 'Permohonan Izin Menunggu Keputusan',
                    'title' => strtoupper($leave->type).' · '.$name,
                    'description' => 'Tanggal: '.$leave->start_date.' s.d. '.$leave->end_date,
                    'date' => $leave->submitted_at ? substr($leave->submitted_at, 0, 10) : $leave->created_at?->toDateString(),
                    'urgency' => 'high',
                    'action_url' => '/public/leaves',
                ];
            }
        }

        // 4. Jurnal Mengajar Belum Diisi
        if ($canViewTeaching) {
            $period = $this->getActivePeriod();
            $yearId = $period['school_year']?->id;
            $semesterId = $period['semester']?->id;

            if ($yearId && $semesterId) {
                $employee = Employee::query()->where('user_id', $user->id)->first();
                $isTeacherOnly = ! $canManageTeaching && $employee !== null;

                if ($isTeacherOnly) {
                    // Ambil sesi missing guru sendiri dalam grace window
                    try {
                        $dateFrom = Carbon::now('Asia/Jakarta')->subDays(7)->toDateString();
                        $report = $this->teachingReportService->missingJournalsReport([
                            'school_year_id' => $yearId,
                            'semester_id' => $semesterId,
                            'date_from' => $dateFrom,
                            'date_to' => $todayWib,
                            'status' => 'missing_grace',
                            'scope' => 'mine',
                            'tab' => 'drilldown',
                            'per_page' => 5,
                        ], $user);

                        $drilldown = $report['data']['drilldown']['data'] ?? $report['drilldown']['data'] ?? [];
                        foreach ($drilldown as $session) {
                            $rombelName = $session['school_rombel_name'] ?? $session['rombel_name'] ?? 'Rombel';
                            $subjName = $session['subject_name'] ?? 'Mapel';
                            $periodStart = $session['period_start'] ?? 1;
                            $sessionDate = $session['session_date'] ?? $todayWib;
                            $graceDeadline = $session['grace_deadline_date'] ?? '-';
                            $scheduleId = $session['teaching_schedule_id'] ?? 0;

                            $tasks[] = [
                                'id' => 'teaching-session-'.$scheduleId.'-'.$sessionDate,
                                'domain' => 'teaching',
                                'category' => 'Jurnal Mengajar Belum Diisi',
                                'title' => $rombelName.' · '.$subjName.' (Jam Ke-'.$periodStart.')',
                                'description' => 'Sesi '.$sessionDate.' · Batas Grace: '.$graceDeadline,
                                'date' => $sessionDate,
                                'urgency' => 'warn',
                                'action_url' => '/public/teaching/journals',
                            ];
                        }
                    } catch (Throwable) {
                        // ignore error in tasks collection
                    }
                } elseif ($canManageTeaching) {
                    // Operator overview: overdue missing sessions
                    try {
                        $dateFrom = Carbon::now('Asia/Jakarta')->subDays(30)->toDateString();
                        $report = $this->teachingReportService->missingJournalsReport([
                            'school_year_id' => $yearId,
                            'semester_id' => $semesterId,
                            'date_from' => $dateFrom,
                            'date_to' => $todayWib,
                            'status' => 'missing_overdue',
                            'tab' => 'drilldown',
                            'per_page' => 5,
                        ], $user);

                        $drilldown = $report['data']['drilldown']['data'] ?? $report['drilldown']['data'] ?? [];
                        foreach ($drilldown as $session) {
                            $teacherName = $session['teacher_name'] ?? $session['effective_teacher_name'] ?? 'Guru';
                            $rombelName = $session['school_rombel_name'] ?? $session['rombel_name'] ?? 'Rombel';
                            $subjName = $session['subject_name'] ?? 'Mapel';
                            $sessionDate = $session['session_date'] ?? $todayWib;
                            $scheduleId = $session['teaching_schedule_id'] ?? 0;

                            $tasks[] = [
                                'id' => 'teaching-overdue-'.$scheduleId.'-'.$sessionDate,
                                'domain' => 'teaching',
                                'category' => 'Jurnal Mengajar Terlewat (Overdue)',
                                'title' => $teacherName.' · '.$rombelName.' · '.$subjName,
                                'description' => 'Sesi '.$sessionDate.' (Memerlukan koreksi operator)',
                                'date' => $sessionDate,
                                'urgency' => 'high',
                                'action_url' => '/public/teaching/reports',
                            ];
                        }
                    } catch (Throwable) {
                        // ignore error
                    }
                }
            }
        }

        // 5. Dokumen Arsip Kedaluwarsa
        if ($canViewArchives) {
            $expiredDocs = ArchiveDocument::query()
                ->whereNull('archived_at')
                ->whereNotNull('valid_until')
                ->whereDate('valid_until', '<', $todayWib)
                ->orderBy('valid_until')
                ->limit(3)
                ->get();

            foreach ($expiredDocs as $doc) {
                $tasks[] = [
                    'id' => 'archive-expired-'.$doc->id,
                    'domain' => 'archives',
                    'category' => 'Dokumen Arsip Kedaluwarsa',
                    'title' => $doc->title,
                    'description' => 'Batas berlaku: '.$doc->valid_until.' (Sudah lewat)',
                    'date' => $doc->valid_until,
                    'urgency' => 'warn',
                    'action_url' => '/public/archives',
                ];
            }
        }

        // Urutkan berdasarkan bobot urgensi: high (3) > warn (2) > info (1), lalu tanggal desc
        $urgencyWeight = ['high' => 3, 'warn' => 2, 'info' => 1];
        usort($tasks, function ($a, $b) use ($urgencyWeight) {
            $wA = $urgencyWeight[$a['urgency']] ?? 0;
            $wB = $urgencyWeight[$b['urgency']] ?? 0;
            if ($wA !== $wB) {
                return $wB <=> $wA;
            }

            return strcmp($b['date'], $a['date']);
        });

        return array_slice($tasks, 0, 15);
    }

    /**
     * Mengambil kumpulan data statistik untuk visualisasi grafik operasional.
     *
     * @return array<string, mixed>
     */
    public function getCharts(User $user): array
    {
        $canViewStudents = $user->can('students.view');
        $canViewTeaching = $user->can('teaching.view');

        $studentsByLevel = null;
        if ($canViewStudents) {
            $period = $this->getActivePeriod();
            $yearId = $period['school_year']?->id;

            if ($yearId) {
                // Distribusi siswa per rombel/tingkat dan gender
                $rombels = SchoolRombel::query()
                    ->where('school_year_id', $yearId)
                    ->with(['studentRombels.student.gender'])
                    ->get();

                $labels = [];
                $maleCounts = [];
                $femaleCounts = [];
                $totalCounts = [];

                foreach ($rombels as $rombel) {
                    $labels[] = $rombel->name;
                    $m = 0;
                    $f = 0;
                    foreach ($rombel->studentRombels as $sr) {
                        $student = $sr->student;
                        if ($student && $sr->latest_student_exit_date === null) {
                            $genderName = strtolower((string) ($student->gender?->name ?? ''));
                            if (str_contains($genderName, 'laki') || str_contains($genderName, 'male') || $genderName === 'l') {
                                $m++;
                            } else {
                                $f++;
                            }
                        }
                    }
                    $maleCounts[] = $m;
                    $femaleCounts[] = $f;
                    $totalCounts[] = $m + $f;
                }

                $studentsByLevel = [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Laki-laki',
                            'data' => $maleCounts,
                        ],
                        [
                            'label' => 'Perempuan',
                            'data' => $femaleCounts,
                        ],
                        [
                            'label' => 'Total',
                            'data' => $totalCounts,
                        ],
                    ],
                ];
            }
        }

        $teachingComplianceTrend = null;
        if ($canViewTeaching) {
            $period = $this->getActivePeriod();
            $yearId = $period['school_year']?->id;
            $semesterId = $period['semester']?->id;

            if ($yearId && $semesterId) {
                // Tren kepatuhan per bulan (4 bulan terakhir)
                $months = [];
                $complianceRates = [];
                $recordedCounts = [];

                for ($i = 3; $i >= 0; $i--) {
                    $dt = Carbon::now('Asia/Jakarta')->subMonths($i);
                    $startOfMonth = $dt->copy()->startOfMonth()->toDateString();
                    $endOfMonth = $dt->copy()->endOfMonth()->toDateString();
                    $todayWib = Carbon::now('Asia/Jakarta')->toDateString();

                    // Batasi dateTo ke hari ini jika bulan berjalan
                    if ($endOfMonth > $todayWib) {
                        $endOfMonth = $todayWib;
                    }

                    $monthLabel = $dt->isoFormat('MMM YYYY');
                    $months[] = $monthLabel;

                    try {
                        $report = $this->teachingReportService->missingJournalsReport([
                            'school_year_id' => $yearId,
                            'semester_id' => $semesterId,
                            'date_from' => $startOfMonth,
                            'date_to' => $endOfMonth,
                            'tab' => 'summary',
                        ], $user);

                        $summary = $report['data']['summary'] ?? $report['summary'] ?? [];
                        $complianceRates[] = (float) ($summary['overall_compliance_percentage'] ?? $summary['compliance_rate'] ?? 0.0);
                        $recordedCounts[] = (int) ($summary['total_recorded_journals'] ?? $summary['recorded_journals'] ?? 0);
                    } catch (Throwable) {
                        $complianceRates[] = 0.0;
                        $recordedCounts[] = 0;
                    }
                }

                $teachingComplianceTrend = [
                    'labels' => $months,
                    'datasets' => [
                        [
                            'label' => 'Kepatuhan (%)',
                            'data' => $complianceRates,
                        ],
                        [
                            'label' => 'Jurnal Terisi',
                            'data' => $recordedCounts,
                        ],
                    ],
                ];
            }
        }

        return [
            'students_by_level' => $studentsByLevel,
            'teaching_compliance_trend' => $teachingComplianceTrend,
        ];
    }

    /**
     * Mendapatkan tahun ajaran dan semester aktif.
     *
     * @return array{school_year: ?SchoolYear, semester: ?Semester}
     */
    private function getActivePeriod(): array
    {
        $setting = AcademicSetting::query()->find(AcademicSetting::SINGLETON_ID);

        $schoolYear = null;
        $semester = null;

        if ($setting) {
            $schoolYear = $setting->activeSchoolYear;
            $semester = $setting->activeSemester;
        }

        if (! $schoolYear) {
            $schoolYear = SchoolYear::query()->orderByDesc('name')->first();
        }

        if (! $semester) {
            $semester = Semester::query()->orderBy('id')->first();
        }

        return [
            'school_year' => $schoolYear,
            'semester' => $semester,
        ];
    }
}
