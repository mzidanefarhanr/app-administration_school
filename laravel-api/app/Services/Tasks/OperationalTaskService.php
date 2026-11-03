<?php

namespace App\Services\Tasks;

use App\Models\AcademicSetting;
use App\Models\ArchiveDocument;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Letter;
use App\Models\LetterDisposition;
use App\Models\OperatorReminder;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\BackupMonitoringService;
use App\Services\TeachingReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class OperationalTaskService
{
    public function __construct(
        protected TeachingReportService $teachingReportService,
        protected BackupMonitoringService $backupMonitoringService,
    ) {}

    /**
     * Mengambil daftar tugas dan pengingat operasional terfilter.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function getTasks(User $user, array $filters = []): array
    {
        $tab = $filters['tab'] ?? 'all'; // all, system, manual, completed
        $domain = $filters['domain'] ?? null;
        $urgency = $filters['urgency'] ?? null;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));

        $allTasks = collect();

        // 1. Ambil Tugas Sistem (bila tab != manual dan tab != completed)
        if ($tab !== 'manual' && $tab !== 'completed') {
            $systemTasks = $this->collectSystemTasks($user);
            $allTasks = $allTasks->concat($systemTasks);
        }

        // 2. Ambil Pengingat Manual Operator
        $manualReminders = $this->collectManualReminders($user, $tab);
        $allTasks = $allTasks->concat($manualReminders);

        // 3. Ambil Riwayat Dismissal Tugas Sistem (hanya untuk tab 'completed')
        if ($tab === 'completed') {
            $dismissedTasks = $this->collectDismissedSystemTasks($user);
            $allTasks = $allTasks->concat($dismissedTasks);
        }

        // Filter berdasarkan Domain
        if ($domain && $domain !== 'all') {
            $allTasks = $allTasks->filter(fn ($t) => ($t['domain'] ?? null) === $domain);
        }

        // Filter berdasarkan Urgency
        if ($urgency && $urgency !== 'all') {
            $allTasks = $allTasks->filter(fn ($t) => ($t['urgency'] ?? null) === $urgency);
        }

        // Filter Pencarian Teks
        if ($search !== null && $search !== '') {
            $keyword = mb_strtolower($search);
            $allTasks = $allTasks->filter(function ($t) use ($keyword) {
                return str_contains(mb_strtolower($t['title'] ?? ''), $keyword)
                    || str_contains(mb_strtolower($t['description'] ?? ''), $keyword)
                    || str_contains(mb_strtolower($t['category'] ?? ''), $keyword);
            });
        }

        // Urutkan: High > Warn > Info, lalu tanggal tenggat/waktu terbaru
        $allTasks = $allTasks->sort(function ($a, $b) {
            $urgencyWeight = ['high' => 3, 'warn' => 2, 'info' => 1];
            $wA = $urgencyWeight[$a['urgency'] ?? 'info'] ?? 0;
            $wB = $urgencyWeight[$b['urgency'] ?? 'info'] ?? 0;

            if ($wA !== $wB) {
                return $wB <=> $wA; // Descending prioritas
            }

            return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
        })->values();

        // Paginasi
        $total = $allTasks->count();
        $offset = ($page - 1) * $perPage;
        $items = $allTasks->slice($offset, $perPage)->values()->all();

        return [
            'data' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Menghitung ringkasan metrik tugas per urgensi dan domain.
     *
     * @return array<string, mixed>
     */
    public function getSummary(User $user): array
    {
        $todayWib = Carbon::now('Asia/Jakarta')->toDateString();

        $systemTasks = $this->collectSystemTasks($user);
        $manualPending = $this->collectManualReminders($user, 'manual');

        $activeTasks = $systemTasks->concat($manualPending);

        $highCount = $activeTasks->where('urgency', 'high')->count();
        $warnCount = $activeTasks->where('urgency', 'warn')->count();
        $infoCount = $activeTasks->where('urgency', 'info')->count();

        // Hitung tugas selesai hari ini WIB
        $completedToday = OperatorReminder::query()
            ->where('status', OperatorReminder::STATUS_COMPLETED)
            ->whereDate('completed_at', $todayWib)
            ->count();

        // Hitung per domain
        $byDomain = [
            'letters' => $activeTasks->where('domain', 'letters')->count(),
            'leaves' => $activeTasks->where('domain', 'leaves')->count(),
            'teaching' => $activeTasks->where('domain', 'teaching')->count(),
            'archives' => $activeTasks->where('domain', 'archives')->count(),
            'students' => $activeTasks->where('domain', 'students')->count(),
            'system' => $activeTasks->where('domain', 'system')->count(),
            'manual' => $manualPending->count(),
        ];

        return [
            'total_active' => $activeTasks->count(),
            'total_high' => $highCount,
            'total_warn' => $warnCount,
            'total_info' => $infoCount,
            'completed_today' => $completedToday,
            'by_domain' => $byDomain,
        ];
    }

    /**
     * Membuat pengingat internal manual baru.
     *
     * @param  array<string, mixed>  $data
     */
    public function createReminder(User $user, array $data): OperatorReminder
    {
        return OperatorReminder::create([
            'user_id' => $user->id,
            'title' => trim($data['title']),
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'category' => $data['category'] ?? OperatorReminder::CATEGORY_GENERAL,
            'priority' => $data['priority'] ?? OperatorReminder::PRIORITY_INFO,
            'due_date' => $data['due_date'] ?? null,
            'status' => OperatorReminder::STATUS_PENDING,
        ]);
    }

    /**
     * Memperbarui data pengingat internal manual.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateReminder(OperatorReminder $reminder, array $data): OperatorReminder
    {
        $reminder->update([
            'title' => isset($data['title']) ? trim($data['title']) : $reminder->title,
            'description' => array_key_exists('description', $data) ? ($data['description'] ? trim($data['description']) : null) : $reminder->description,
            'category' => $data['category'] ?? $reminder->category,
            'priority' => $data['priority'] ?? $reminder->priority,
            'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $reminder->due_date,
        ]);

        return $reminder;
    }

    /**
     * Menandai pengingat manual sebagai selesai.
     */
    public function completeReminder(OperatorReminder $reminder, User $user): OperatorReminder
    {
        $reminder->update([
            'status' => OperatorReminder::STATUS_COMPLETED,
            'completed_at' => Carbon::now('Asia/Jakarta'),
            'completed_by' => $user->id,
        ]);

        return $reminder;
    }

    /**
     * Membatalkan pengingat manual.
     */
    public function cancelReminder(OperatorReminder $reminder): OperatorReminder
    {
        $reminder->update([
            'status' => OperatorReminder::STATUS_CANCELLED,
        ]);

        return $reminder;
    }

    /**
     * Menghapus pengingat manual.
     */
    public function deleteReminder(OperatorReminder $reminder): void
    {
        $reminder->delete();
    }

    /**
     * Menandai tugas sistem tertentu telah ditinjau (dismiss).
     */
    public function dismissSystemTask(string $systemKey, User $user, ?string $title = null): OperatorReminder
    {
        $now = Carbon::now('Asia/Jakarta');

        return OperatorReminder::updateOrCreate(
            ['system_source_key' => $systemKey],
            [
                'user_id' => $user->id,
                'title' => $title ?? ('Ditinjau: '.$systemKey),
                'category' => OperatorReminder::CATEGORY_SYSTEM,
                'priority' => OperatorReminder::PRIORITY_INFO,
                'status' => OperatorReminder::STATUS_COMPLETED,
                'completed_at' => $now,
                'completed_by' => $user->id,
            ]
        );
    }

    /**
     * Mengumpulkan tugas sistem aktif lintas domain secara deterministik.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function collectSystemTasks(User $user): Collection
    {
        $tasks = collect();
        $todayWib = Carbon::now('Asia/Jakarta')->toDateString();

        // Kunci-kunci yang telah di-dismiss
        $dismissedKeys = OperatorReminder::query()
            ->whereNotNull('system_source_key')
            ->where('status', OperatorReminder::STATUS_COMPLETED)
            ->pluck('system_source_key')
            ->flip()
            ->all();

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
        $canViewStudents = $user->can('students.view');
        $canManageBackup = $user->can('backup.manage');

        // 1. Letters: Surat Keluar Menunggu Approval
        if ($canApproveLetters) {
            $outgoing = Letter::query()
                ->where('direction', Letter::DIRECTION_OUTGOING)
                ->where('status', Letter::STATUS_ACTIVE)
                ->where('workflow_status', Letter::WORKFLOW_REVIEW)
                ->orderByDesc('id')
                ->limit(10)
                ->get();

            foreach ($outgoing as $letter) {
                $key = 'letter-out-'.$letter->id;
                if (isset($dismissedKeys[$key])) {
                    continue;
                }
                $tasks->push([
                    'id' => $key,
                    'domain' => 'letters',
                    'category' => 'Surat Keluar Menunggu Approval',
                    'title' => $letter->letter_number ? 'No. '.$letter->letter_number.' · '.$letter->subject : $letter->subject,
                    'description' => 'Tujuan: '.($letter->recipient ?? '-'),
                    'date' => $letter->letter_date ?? $letter->created_at?->toDateString(),
                    'due_date' => null,
                    'urgency' => 'high',
                    'source_type' => 'system',
                    'status' => 'pending',
                    'action_url' => '/public/letters/outgoing',
                ]);
            }
        }

        // 2. Letters: Surat Masuk Belum Disposisi
        if ($canManageLetters) {
            $incoming = Letter::query()
                ->where('direction', Letter::DIRECTION_INCOMING)
                ->where('status', Letter::STATUS_ACTIVE)
                ->whereDoesntHave('dispositions')
                ->orderByDesc('id')
                ->limit(10)
                ->get();

            foreach ($incoming as $letter) {
                $key = 'letter-in-'.$letter->id;
                if (isset($dismissedKeys[$key])) {
                    continue;
                }
                $tasks->push([
                    'id' => $key,
                    'domain' => 'letters',
                    'category' => 'Surat Masuk Belum Disposisi',
                    'title' => ($letter->incoming_number ? 'No. '.$letter->incoming_number.' · ' : '').$letter->subject,
                    'description' => 'Pengirim: '.($letter->sender_name ?? '-'),
                    'date' => $letter->letter_date ?? $letter->created_at?->toDateString(),
                    'due_date' => null,
                    'urgency' => 'warn',
                    'source_type' => 'system',
                    'status' => 'pending',
                    'action_url' => '/public/letters/incoming',
                ]);
            }

            // Disposisi Terbuka mendekati atau lewat batas tenggat
            $nearDue = Carbon::now('Asia/Jakarta')->addDays(3)->toDateString();
            $dispositions = LetterDisposition::query()
                ->where('status', 'open')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', $nearDue)
                ->with(['letter'])
                ->orderBy('due_date')
                ->limit(10)
                ->get();

            foreach ($dispositions as $disp) {
                $key = 'disposition-'.$disp->id;
                if (isset($dismissedKeys[$key])) {
                    continue;
                }
                $isOverdue = $disp->due_date < $todayWib;
                $tasks->push([
                    'id' => $key,
                    'domain' => 'letters',
                    'category' => $isOverdue ? 'Disposisi Telah Melewati Tenggat' : 'Disposisi Mendekati Batas Tenggat',
                    'title' => 'Disposisi: '.($disp->letter?->subject ?? 'Surat Masuk'),
                    'description' => 'Tenggat: '.$disp->due_date.' · Instruksi: '.($disp->notes ?? '-'),
                    'date' => $disp->created_at?->toDateString(),
                    'due_date' => $disp->due_date,
                    'urgency' => $isOverdue ? 'high' : 'warn',
                    'source_type' => 'system',
                    'status' => 'pending',
                    'action_url' => '/public/letters/dispositions',
                ]);
            }
        }

        // 3. Leaves: Permohonan Izin Menunggu Persetujuan
        if ($canApproveLeaves) {
            $leaves = LeaveRequest::query()
                ->where('status', LeaveRequest::STATUS_SUBMITTED)
                ->with(['student', 'employee'])
                ->orderByDesc('id')
                ->limit(10)
                ->get();

            foreach ($leaves as $leave) {
                $key = 'leave-'.$leave->id;
                if (isset($dismissedKeys[$key])) {
                    continue;
                }
                $applicant = $leave->student?->name ?? $leave->employee?->name ?? 'Pemohon';
                $tasks->push([
                    'id' => $key,
                    'domain' => 'leaves',
                    'category' => 'Permohonan Izin Menunggu Keputusan',
                    'title' => strtoupper($leave->type).' · '.$applicant,
                    'description' => 'Tanggal: '.$leave->start_date.' s.d. '.$leave->end_date,
                    'date' => $leave->submitted_at ? substr($leave->submitted_at, 0, 10) : $leave->created_at?->toDateString(),
                    'due_date' => $leave->start_date,
                    'urgency' => 'high',
                    'source_type' => 'system',
                    'status' => 'pending',
                    'action_url' => '/public/leaves',
                ]);
            }
        }

        // 4. Teaching: Jurnal Mengajar Belum Diisi
        if ($canViewTeaching) {
            $period = $this->getActivePeriod();
            $yearId = $period['school_year']?->id;
            $semesterId = $period['semester']?->id;

            if ($yearId && $semesterId) {
                $employee = Employee::query()->where('user_id', $user->id)->first();
                $isTeacherOnly = ! $canManageTeaching && $employee !== null;

                if ($isTeacherOnly) {
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
                        foreach ($drilldown as $item) {
                            $key = 'teaching-mine-'.$item['teaching_schedule_id'].'-'.$item['session_date'];
                            if (isset($dismissedKeys[$key])) {
                                continue;
                            }
                            $tasks->push([
                                'id' => $key,
                                'domain' => 'teaching',
                                'category' => 'Jurnal Mengajar Perlu Diisi (Masa Tenggang)',
                                'title' => ($item['subject_name'] ?? 'Mata Pelajaran').' · '.($item['rombel_name'] ?? 'Kelas'),
                                'description' => 'Sesi: '.$item['session_date'].' (Jam ke-'.$item['period_start'].'-'.$item['period_end'].')',
                                'date' => $item['session_date'],
                                'due_date' => $item['grace_deadline_date'] ?? null,
                                'urgency' => 'warn',
                                'source_type' => 'system',
                                'status' => 'pending',
                                'action_url' => '/public/teaching/journals',
                            ]);
                        }
                    } catch (Throwable) {
                    }
                } elseif ($canManageTeaching) {
                    try {
                        $dateFrom = Carbon::now('Asia/Jakarta')->subDays(14)->toDateString();
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
                        foreach ($drilldown as $item) {
                            $key = 'teaching-overdue-'.$item['teaching_schedule_id'].'-'.$item['session_date'];
                            if (isset($dismissedKeys[$key])) {
                                continue;
                            }
                            $tasks->push([
                                'id' => $key,
                                'domain' => 'teaching',
                                'category' => 'Jurnal Mengajar Melewati Batas Toleransi',
                                'title' => ($item['teacher_name'] ?? 'Guru').' · '.($item['subject_name'] ?? 'Mapel'),
                                'description' => 'Kelas: '.($item['rombel_name'] ?? '-').' · Tanggal: '.$item['session_date'],
                                'date' => $item['session_date'],
                                'due_date' => null,
                                'urgency' => 'high',
                                'source_type' => 'system',
                                'status' => 'pending',
                                'action_url' => '/public/teaching/reports',
                            ]);
                        }
                    } catch (Throwable) {
                    }
                }
            }
        }

        // 5. Archives: Dokumen Kedaluwarsa & Segera Kedaluwarsa
        if ($canViewArchives) {
            $threshold = Carbon::now('Asia/Jakarta')->addDays(30)->toDateString();

            $expiringDocs = ArchiveDocument::query()
                ->whereNull('archived_at')
                ->whereNotNull('valid_until')
                ->whereBetween('valid_until', [$todayWib, $threshold])
                ->orderBy('valid_until')
                ->limit(5)
                ->get();

            foreach ($expiringDocs as $doc) {
                $key = 'archive-soon-'.$doc->id;
                if (isset($dismissedKeys[$key])) {
                    continue;
                }
                $tasks->push([
                    'id' => $key,
                    'domain' => 'archives',
                    'category' => 'Arsip Dokumen Segera Kedaluwarsa',
                    'title' => $doc->title,
                    'description' => 'Kategori: '.strtoupper($doc->category).' · Berlaku s.d. '.$doc->valid_until,
                    'date' => $doc->created_at?->toDateString(),
                    'due_date' => $doc->valid_until,
                    'urgency' => 'warn',
                    'source_type' => 'system',
                    'status' => 'pending',
                    'action_url' => '/public/archives',
                ]);
            }

            $expiredDocs = ArchiveDocument::query()
                ->whereNull('archived_at')
                ->whereNotNull('valid_until')
                ->whereDate('valid_until', '<', $todayWib)
                ->orderBy('valid_until')
                ->limit(5)
                ->get();

            foreach ($expiredDocs as $doc) {
                $key = 'archive-exp-'.$doc->id;
                if (isset($dismissedKeys[$key])) {
                    continue;
                }
                $tasks->push([
                    'id' => $key,
                    'domain' => 'archives',
                    'category' => 'Arsip Dokumen Telah Kedaluwarsa',
                    'title' => $doc->title,
                    'description' => 'Kategori: '.strtoupper($doc->category).' · Berakhir: '.$doc->valid_until,
                    'date' => $doc->created_at?->toDateString(),
                    'due_date' => $doc->valid_until,
                    'urgency' => 'high',
                    'source_type' => 'system',
                    'status' => 'pending',
                    'action_url' => '/public/archives',
                ]);
            }
        }

        // 6. Students & Kesiswaan: Siswa Unplaced
        if ($canViewStudents) {
            $period = $this->getActivePeriod();
            $yearId = $period['school_year']?->id;

            if ($yearId) {
                $unplacedCount = Student::query()
                    ->whereNull('deleted_at')
                    ->whereDoesntHave('studentRombels', function ($q) use ($yearId) {
                        $q->where('school_year_id', $yearId)
                            ->whereNull('archived_at');
                    })
                    ->count();

                if ($unplacedCount > 0) {
                    $key = 'students-unplaced-summary';
                    if (! isset($dismissedKeys[$key])) {
                        $tasks->push([
                            'id' => $key,
                            'domain' => 'students',
                            'category' => 'Penempatan Rombel Siswa',
                            'title' => $unplacedCount.' Siswa Belum Ditempatkan ke Rombel',
                            'description' => 'Terdapat siswa aktif yang belum terdaftar di rombongan belajar tahun ajaran ini.',
                            'date' => $todayWib,
                            'due_date' => null,
                            'urgency' => 'warn',
                            'source_type' => 'system',
                            'status' => 'pending',
                            'action_url' => '/public/students',
                        ]);
                    }
                }
            }
        }

        // 7. Backup & System Health
        if ($canManageBackup) {
            try {
                $health = $this->backupMonitoringService->getHealthStatus();
                $backupStatus = $health['status'] ?? 'healthy';
                $lastBackup = $health['last_backup_at'] ?? null;

                $needsBackup = false;
                if ($backupStatus !== 'healthy') {
                    $needsBackup = true;
                } elseif (! $lastBackup || Carbon::parse($lastBackup)->diffInHours(Carbon::now('Asia/Jakarta')) >= 48) {
                    $needsBackup = true;
                }

                if ($needsBackup) {
                    $key = 'system-backup-health';
                    if (! isset($dismissedKeys[$key])) {
                        $tasks->push([
                            'id' => $key,
                            'domain' => 'system',
                            'category' => 'Pencadangan Sistem & Database',
                            'title' => 'Cadangan Database Perlu Dijalankan',
                            'description' => $lastBackup ? 'Cadangan terakhir dibuat pada: '.$lastBackup : 'Belum ada arsip cadangan tersimpan.',
                            'date' => $todayWib,
                            'due_date' => null,
                            'urgency' => 'warn',
                            'source_type' => 'system',
                            'status' => 'pending',
                            'action_url' => '/',
                        ]);
                    }
                }
            } catch (Throwable) {
            }
        }

        return $tasks;
    }

    /**
     * Mengumpulkan pengingat internal manual dari tabel `operator_reminders`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function collectManualReminders(User $user, string $tab): Collection
    {
        $query = OperatorReminder::query()
            ->whereNull('system_source_key')
            ->with(['user', 'completedBy']);

        if ($tab === 'completed') {
            $query->whereIn('status', [OperatorReminder::STATUS_COMPLETED, OperatorReminder::STATUS_CANCELLED]);
        } elseif ($tab === 'manual' || $tab === 'all') {
            $query->where('status', OperatorReminder::STATUS_PENDING);
        }

        $reminders = $query->orderByDesc('id')->limit(50)->get();

        return $reminders->map(function (OperatorReminder $r) {
            return [
                'id' => 'reminder-'.$r->id,
                'reminder_id' => $r->id,
                'domain' => 'manual',
                'category' => $r->category ? strtoupper($r->category) : 'UMUM',
                'title' => $r->title,
                'description' => $r->description ?? '',
                'date' => $r->created_at?->toDateString(),
                'due_date' => $r->due_date?->toDateString(),
                'urgency' => $r->priority ?? 'info',
                'source_type' => 'manual',
                'status' => $r->status,
                'completed_at' => $r->completed_at?->toDateTimeString(),
                'completed_by_name' => $r->completedBy?->name,
                'action_url' => null,
            ];
        });
    }

    /**
     * Mengumpulkan tugas sistem yang telah di-dismiss (untuk tab completed).
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function collectDismissedSystemTasks(User $user): Collection
    {
        $dismissed = OperatorReminder::query()
            ->whereNotNull('system_source_key')
            ->where('status', OperatorReminder::STATUS_COMPLETED)
            ->with(['completedBy'])
            ->orderByDesc('completed_at')
            ->limit(30)
            ->get();

        return $dismissed->map(function (OperatorReminder $r) {
            return [
                'id' => 'dismissed-'.$r->id,
                'reminder_id' => $r->id,
                'domain' => 'system',
                'category' => 'Tugas Sistem (Ditinjau)',
                'title' => $r->title,
                'description' => 'Key: '.$r->system_source_key,
                'date' => $r->completed_at?->toDateString(),
                'due_date' => null,
                'urgency' => 'info',
                'source_type' => 'system',
                'status' => 'completed',
                'completed_at' => $r->completed_at?->toDateTimeString(),
                'completed_by_name' => $r->completedBy?->name,
                'action_url' => null,
            ];
        });
    }

    /**
     * Mengambil tahun ajaran dan semester aktif.
     *
     * @return array{school_year: ?SchoolYear, semester: ?Semester}
     */
    protected function getActivePeriod(): array
    {
        $setting = AcademicSetting::query()->first();
        $schoolYear = null;
        $semester = null;

        if ($setting && $setting->active_school_year_id) {
            $schoolYear = SchoolYear::find($setting->active_school_year_id);
        } else {
            $schoolYear = SchoolYear::query()->latest('id')->first();
        }

        if ($setting && $setting->active_semester_id) {
            $semester = Semester::find($setting->active_semester_id);
        } else {
            $semester = Semester::query()->latest('id')->first();
        }

        return [
            'school_year' => $schoolYear,
            'semester' => $semester,
        ];
    }
}
