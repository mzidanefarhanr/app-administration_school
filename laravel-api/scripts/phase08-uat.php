<?php

declare(strict_types=1);

$base = str_replace('\\', '/', dirname(__DIR__));
$runtime = dirname($base).'/.uat-phase08';
$command = $argv[1] ?? '';

foreach (['DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'DATABASE_URL', 'APP_ENV', 'APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_BASE_PATH', 'LARAVEL_STORAGE_PATH'] as $key) {
    if (getenv($key) !== false && getenv($key) !== '') {
        throw new RuntimeException("Unset process override {$key} before using the isolated UAT launcher.");
    }
}
foreach (['config.php', 'routes-v7.php'] as $cache) {
    if (file_exists($base.'/bootstrap/cache/'.$cache)) {
        throw new RuntimeException('Cached application configuration/routes must not be present.');
    }
}

if (PHP_SAPI === 'cli' && $command === 'init') {
    if (file_exists($runtime.'/.env')) {
        throw new RuntimeException('The isolated UAT runtime already exists; it will not be overwritten.');
    }
    foreach (['', '/storage/app', '/storage/framework/cache/data', '/storage/framework/sessions', '/storage/framework/views', '/storage/logs', '/tmp'] as $directory) {
        if (! is_dir($runtime.$directory) && ! mkdir($runtime.$directory, 0700, true)) {
            throw new RuntimeException('Unable to create isolated runtime directories.');
        }
    }
    touch($runtime.'/database.sqlite');
    $key = 'base64:'.base64_encode(random_bytes(32));
    $password = 'uat-'.bin2hex(random_bytes(12));
    file_put_contents($runtime.'/account-password.txt', $password);
    $env = [
        'APP_NAME' => 'School UAT Phase 08', 'APP_ENV' => 'uat', 'APP_KEY' => $key, 'APP_DEBUG' => 'false',
        'APP_URL' => 'http://127.0.0.1:18026', 'FRONTEND_URL' => 'http://127.0.0.1:14026',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $runtime.'/database.sqlite', 'DB_FOREIGN_KEYS' => 'true',
        'CACHE_DRIVER' => 'file', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'school_phase08_session',
        'SESSION_DOMAIN' => '127.0.0.1', 'SANCTUM_STATEFUL_DOMAINS' => '127.0.0.1:14026',
        'FILESYSTEM_DISK' => 'local', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'single',
    ];
    file_put_contents($runtime.'/.env', implode("\n", array_map(fn ($key, $value) => $key.'="'.$value.'"', array_keys($env), $env))."\n");
    fwrite(STDOUT, "Isolated SQLite UAT (phase 08) initialized. Credentials remain in .uat-phase08/account-password.txt (ignored).\n");
    exit(0);
}

if (! is_file($runtime.'/.env') || ! is_file($runtime.'/database.sqlite')) {
    throw new RuntimeException('Initialize the isolated UAT runtime first.');
}

require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->useEnvironmentPath($runtime);
$app->loadEnvironmentFrom('.env');
$app->useStoragePath($runtime.'/storage');
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if (! $app->environment('uat') || config('database.default') !== 'sqlite'
    || realpath((string) config('database.connections.sqlite.database')) !== realpath($runtime.'/database.sqlite')
    || config('database.connections.sqlite.url') || config('app.url') !== 'http://127.0.0.1:18026'
    || config('session.cookie') !== 'school_phase08_session') {
    throw new RuntimeException('Isolation guard rejected the effective UAT configuration.');
}

if (PHP_SAPI === 'cli-server') {
    $httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::capture();
    $response = $httpKernel->handle($request);
    $response->send();
    $httpKernel->terminate($request, $response);
    exit;
}

if ($command === 'prepare') {
    $exit = $kernel->call('migrate', ['--force' => true]);
    fwrite(STDOUT, $kernel->output());
    if ($exit !== 0) {
        exit($exit);
    }

    if (App\Models\User::withTrashed()->count() === 0) {
        putenv('UAT_SEED_PASSWORD='.trim(file_get_contents($runtime.'/account-password.txt')));
        try {
            $exit = $kernel->call('db:seed', ['--class' => Database\Seeders\UatSeeder::class, '--force' => true]);
            fwrite(STDOUT, $kernel->output());
        } finally {
            putenv('UAT_SEED_PASSWORD');
        }
        if ($exit !== 0) {
            exit($exit);
        }
    }

    fwrite(STDOUT, 'Users: '.App\Models\User::withTrashed()->count().' (uat.admin, uat.operator, uat.kepsek, uat.waka expected).'."\n");
    fwrite(STDOUT, 'Prepare complete. Run prepare-leaves to populate the synthetic leave agenda.'."\n");
    exit(0);
}

if ($command === 'prepare-leaves') {
    $exit = $kernel->call('migrate', ['--force' => true]);
    fwrite(STDOUT, $kernel->output());
    if ($exit !== 0) {
        exit($exit);
    }

    $admin = App\Models\User::where('username', 'uat.admin')->first();
    $operator = App\Models\User::where('username', 'uat.operator')->first();
    $userType = App\Models\TypeUser::where('name', 'User')->first();
    $activeUserStatus = App\Models\StatusUser::where('name', 'Aktif')->first();
    if (! $admin || ! $operator || ! $userType || ! $activeUserStatus) {
        throw new RuntimeException('Run prepare first (UAT users and reference data are required).');
    }

    $password = trim(file_get_contents($runtime.'/account-password.txt'));
    $guruRole = App\Models\Role::where('slug', 'guru')->first();
    if (! $guruRole) {
        throw new RuntimeException('The guru role is missing; run prepare first.');
    }

    $guru = App\Models\User::withTrashed()->firstOrNew(['username' => 'uat.guru']);
    $guru->fill([
        'name' => 'UAT Guru Tertaut',
        'email' => 'uat.guru@uat.local',
        'email_verified_at' => now(),
        'password' => bcrypt($password),
        'nik' => '9000000000000006',
        'type_user_id' => $userType->id,
        'status_user_id' => $activeUserStatus->id,
        'first_login_at' => 0,
        'status_active' => true,
    ]);
    $guru->deleted_at = null;
    $guru->save();
    $guru->roles()->sync([$guruRole->id]);

    // 1. Tahun ajaran aktif dengan rentang tanggal (dipakai validasi izin siswa).
    $year = App\Models\SchoolYear::withTrashed()->firstOrNew(['name' => '2026/2027']);
    $year->fill(['start_date' => '2026-07-01', 'end_date' => '2027-06-30']);
    $year->deleted_at = null;
    $year->save();

    $academic = App\Models\AcademicSetting::find(App\Models\AcademicSetting::SINGLETON_ID);
    if (! $academic) {
        $academic = new App\Models\AcademicSetting;
        $academic->id = App\Models\AcademicSetting::SINGLETON_ID;
    }
    if ($academic->active_school_year_id !== null && (int) $academic->active_school_year_id !== (int) $year->id) {
        throw new RuntimeException('The active school year is already set to another year; reset it before preparing leave fixtures.');
    }
    $academic->active_school_year_id = $year->id;
    $academic->updated_by = $admin->id;
    $academic->save();

    // 2. Referensi pegawai sintetis.
    $statusActive = App\Models\StudentStatus::firstOrCreate(['name' => 'Aktif']);
    if ((int) $statusActive->id !== 1) {
        throw new RuntimeException('The active student status does not match the canonical id 1 baseline.');
    }
    $entryNew = App\Models\StudentEntry::firstOrCreate(['name' => 'Siswa Baru']);

    $employeeStatus = App\Models\EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap Yayasan'], ['abbreviation' => 'PTY']);
    $teacherType = App\Models\EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $staffType = App\Models\EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
    $employment = App\Models\Employment::firstOrCreate(['name' => 'Guru Tetap'], ['description' => 'Guru Tetap']);
    $subject = App\Models\Subject::firstOrCreate(['name' => 'Umum'], ['description' => 'Mata pelajaran umum']);
    $religion = App\Models\Religion::firstOrCreate(['name' => 'Islam']);
    $male = App\Models\Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $female = App\Models\Gender::firstOrCreate(['name' => 'Perempuan']);
    $marital = App\Models\MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);
    $province = App\Models\Province::firstOrCreate(['name' => 'DKI Jakarta UAT']);
    $regency = App\Models\Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Utara UAT']);
    $district = App\Models\District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Koja UAT']);

    $employees = [];
    $employeesCreated = 0;
    for ($i = 1; $i <= 20; $i++) {
        $name = sprintf('UAT-P08 Employee %02d', $i);
        $employee = App\Models\Employee::withTrashed()->firstOrNew(['name' => $name]);
        if (! $employee->exists) {
            $employeesCreated++;
        }
        $employee->fill([
            'user_id' => $i === 1 ? $guru->id : null,
            'user_nik' => $i === 1 ? $guru->nik : null,
            'employee_status_id' => $employeeStatus->id,
            'employee_type_id' => $i <= 12 ? $teacherType->id : $staffType->id,
            'employment_id' => $employment->id,
            'subject_id' => $i <= 12 ? $subject->id : null,
            'religion_id' => $religion->id,
            'district_id' => $district->id,
            'marital_status_id' => $marital->id,
            'gender_id' => $i % 2 === 0 ? $female->id : $male->id,
            'nuptk' => 'P08'.str_pad((string) $i, 13, '0', STR_PAD_LEFT),
            'place_of_birth' => 'Synthetic City',
            'date_of_birth' => '1990-01-01',
            'address' => 'Jl. Sintetis UAT No. '.$i,
            'email' => 'uat.employee'.sprintf('%02d', $i).'@uat.local',
            'tmt_employee' => '2020-01-01',
            'exit_date' => null,
        ]);
        $employee->deleted_at = null;
        $employee->save();
        $employees[$i] = $employee;
    }

    // 3. 120 siswa aktif dalam tahun ajaran aktif.
    $level = App\Models\SchoolLevel::firstOrCreate(['name' => 'UAT Level']);
    $classNames = ['X-A', 'X-B', 'XI-A', 'XI-B'];
    $classes = [];
    foreach ($classNames as $suffix) {
        $classes[$suffix] = App\Models\SchoolRombel::firstOrCreate(
            ['school_year_id' => $year->id, 'name' => 'UAT-P08 '.$suffix],
            ['school_level_id' => $level->id]
        );
    }

    $students = [];
    $studentsCreated = 0;
    for ($i = 1; $i <= 120; $i++) {
        $nis = 'P08'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $student = App\Models\Student::withTrashed()->where('nis', $nis)->first();
        if (! $student) {
            $student = App\Models\Student::factory()->create([
                'nis' => $nis,
                'nik' => '08'.str_pad((string) $i, 14, '0', STR_PAD_LEFT),
                'nisn' => '0080'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'name' => 'UAT-P08 Student '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'birthplace' => 'Synthetic City',
                'birthdate' => '2010-01-01',
                'gender_id' => $i % 2 === 0 ? $female->id : $male->id,
            ]);
            $studentsCreated++;
        } else {
            $student->fill([
                'name' => 'UAT-P08 Student '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'deleted_at' => null,
            ]);
            $student->save();
        }

        $classKey = $classNames[(int) floor(($i - 1) / 30)];
        $membership = App\Models\StudentRombel::withTrashed()->firstOrNew([
            'student_id' => $student->id,
            'school_rombel' => $classes[$classKey]->id,
        ]);
        $membership->student_nik = $student->nik;
        $membership->student_status_id = $statusActive->id;
        $membership->student_entry_id = $entryNew->id;
        $membership->latest_student_entry_date = '2026-07-01';
        $membership->latest_student_exit_date = null;
        $membership->archived_at = null;
        $membership->deleted_at = null;
        $membership->save();

        $students[$i] = $student;
    }

    // 4. Fixture izin sintetis: 100 izin siswa + 20 izin pegawai.
    /** @var App\Services\Leaves\LeaveRequestService $leaves */
    $leaves = $app->make(App\Services\Leaves\LeaveRequestService::class);

    $studentTypes = array_keys((array) config('leaves.types.student'));
    $employeeTypes = array_keys((array) config('leaves.types.employee'));
    $startingDate = Illuminate\Support\Carbon::create(2026, 7, 6);
    $samplePng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $created = 0;
    $reused = 0;
    $attachmentsAdded = 0;
    $submittedCount = 0;
    $draftCount = 0;
    $cancelledCount = 0;

    $fixtures = [];
    for ($i = 1; $i <= 100; $i++) {
        $fixtures[] = [
            'kind' => 'student', 'index' => $i,
            'start' => $startingDate->copy()->addDays(($i - 1) * 3),
            'days' => 1 + ($i % 3),
            'type' => $studentTypes[($i - 1) % count($studentTypes)],
            'actor' => $operator,
        ];
    }
    for ($i = 1; $i <= 20; $i++) {
        $fixtures[] = [
            'kind' => 'employee', 'index' => $i,
            'start' => $startingDate->copy()->addDays(($i - 1) * 12),
            'days' => $i % 5 === 4 ? 30 : 1 + ($i % 4),
            'type' => $employeeTypes[($i - 1) % count($employeeTypes)],
            'actor' => $i === 1 ? $guru : $operator,
        ];
    }

    foreach ($fixtures as $fixture) {
        $kind = $fixture['kind'];
        $i = $fixture['index'];
        $reason = sprintf('UAT-P08 synthetic leave fixture %s %03d for manual review.', $kind, $i);

        $existing = App\Models\LeaveRequest::query()->where('reason', $reason)->first();
        if ($existing) {
            $reused++;

            continue;
        }

        $start = $fixture['start'];
        $end = $start->copy()->addDays($fixture['days'] - 1);
        $data = [
            'subject_type' => $kind,
            'student_id' => $kind === 'student' ? (int) $students[$i]->id : null,
            'employee_id' => $kind === 'employee' ? (int) $employees[$i]->id : null,
            'type' => $fixture['type'],
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'reason' => $reason,
        ];

        $actor = $fixture['actor'];
        $leave = $leaves->create($data, $actor);
        $created++;

        $target = match (true) {
            $i === 1 && $kind === 'student' => 2,
            $i === 2 && $kind === 'student' => 3,
            $i % 4 === 0 || $i % 7 === 0 => 1,
            default => 0,
        };

        for ($n = 1; $n <= $target; $n++) {
            $isPng = $n % 2 === 0;
            $name = sprintf('UAT-P08-L%s%03d-A%d.%s', $kind === 'student' ? 'S' : 'E', $i, $n, $isPng ? 'png' : 'pdf');
            $path = $runtime.'/tmp/'.$name;
            if ($isPng) {
                file_put_contents($path, $samplePng);
            } else {
                file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%% ".$name."\n%%EOF\n");
            }
            $upload = new Illuminate\Http\UploadedFile($path, $name, null, null, true);
            $leaves->addAttachment((int) $leave['id'], $upload, $actor);
            @unlink($path);
            $attachmentsAdded++;
        }

        $statusRule = $kind === 'student' ? $i % 10 : $i % 5;
        $isDraft = $kind === 'student' ? in_array($statusRule, [1, 2, 3], true) : $statusRule === 1;
        $isCancelled = $statusRule === 0;

        $fresh = App\Models\LeaveRequest::findOrFail((int) $leave['id']);

        if ($isCancelled) {
            $cancelFromDraft = $kind === 'student' ? $i % 20 === 0 : $i % 10 === 0;
            if (! $cancelFromDraft) {
                $leaves->submit($fresh->id, (int) $fresh->version, $actor);
                $fresh = $fresh->fresh();
                $submittedCount++;
            }
            $leaves->cancel(
                $fresh->id,
                sprintf('UAT-P08 synthetic cancellation reason for %s %03d.', $kind, $i),
                (int) $fresh->version,
                $actor,
            );
            $cancelledCount++;

            continue;
        }

        if ($isDraft) {
            $draftCount++;

            continue;
        }

        $leaves->submit($fresh->id, (int) $fresh->version, $actor);
        $submittedCount++;
    }

    $leaveQuery = App\Models\LeaveRequest::query();
    $byStatus = (clone $leaveQuery)->pluck('status')->countBy();
    $bySubject = (clone $leaveQuery)->pluck('subject_type')->countBy();
    $attachmentFiles = Illuminate\Support\Facades\Storage::disk((string) config('leaves.attachment.disk'))->allFiles((string) config('leaves.attachment.path'));

    fwrite(STDOUT, 'School year: '.$year->name.' ('.$year->start_date->format('Y-m-d').' to '
        .$year->end_date->format('Y-m-d').'), active id '.$academic->active_school_year_id.".\n");
    fwrite(STDOUT, 'Students: '.App\Models\Student::count().' total ('.$studentsCreated.' created this run), '
        .App\Models\StudentRombel::query()->whereNull('archived_at')->whereNull('latest_student_exit_date')
            ->whereHas('schoolRombel', fn ($query) => $query->where('school_year_id', $year->id))->count()
        .' active memberships in the active year.'."\n");
    fwrite(STDOUT, 'Employees: '.App\Models\Employee::query()->count().' total ('.$employeesCreated
        .' created this run), '.App\Models\Employee::whereNull('exit_date')->count().' active, '
        .App\Models\Employee::whereNotNull('user_id')->count().' linked to a user (uat.guru).'."\n");
    fwrite(STDOUT, 'Leave requests: '.(clone $leaveQuery)->count()." total ({$created} created, {$reused} reused).\n");
    fwrite(STDOUT, 'By status: draft '.($byStatus['draft'] ?? 0).', submitted '.($byStatus['submitted'] ?? 0)
        .', cancelled '.($byStatus['cancelled'] ?? 0).', approved '.($byStatus['approved'] ?? 0)
        .', rejected '.($byStatus['rejected'] ?? 0).'; this run: '.$draftCount.' drafts, '.$submittedCount
        .' submissions, '.$cancelledCount.' cancellations.'."\n");
    fwrite(STDOUT, 'By subject: student '.($bySubject['student'] ?? 0).', employee '.($bySubject['employee'] ?? 0).".\n");
    fwrite(STDOUT, 'Attachments: '.App\Models\LeaveAttachment::count()." rows ({$attachmentsAdded} added this run), "
        .count($attachmentFiles).' private files; decisions '.App\Models\LeaveDecision::count().".\n");
    fwrite(STDOUT, 'Accounts: uat.admin, uat.operator, uat.kepsek, uat.waka, uat.guru, uat.inactive.'."\n");
    exit(0);
}

if ($command === 'prepare-decisions') {
    $exit = $kernel->call('migrate', ['--force' => true]);
    fwrite(STDOUT, $kernel->output());
    if ($exit !== 0) {
        exit($exit);
    }

    $kepsek = App\Models\User::where('username', 'uat.kepsek')->first();
    $operator = App\Models\User::where('username', 'uat.operator')->first();
    if (! $kepsek || ! $operator) {
        throw new RuntimeException('Run prepare first (uat.kepsek and uat.operator are required).');
    }

    $approver = App\Models\Employee::withTrashed()->firstOrNew(['name' => 'UAT-P08 Approver 01']);
    if (! $approver->exists) {
        $approver = App\Models\Employee::factory()->create([
            'name' => 'UAT-P08 Approver 01',
            'user_id' => $kepsek->id,
            'user_nik' => $kepsek->nik,
            'nuptk' => 'P08APPROVER0001',
            'exit_date' => null,
        ]);
        $approverCreated = true;
    } else {
        $approver->user_id = $kepsek->id;
        $approver->user_nik = $kepsek->nik;
        $approver->exit_date = null;
        $approver->deleted_at = null;
        $approver->save();
        $approverCreated = false;
    }

    /** @var App\Services\Leaves\LeaveRequestService $leaves */
    $leaves = $app->make(App\Services\Leaves\LeaveRequestService::class);

    $selfReason = 'UAT-P08 synthetic self decision leave for manual review.';
    $selfLeave = App\Models\LeaveRequest::query()->where('reason', $selfReason)->first();
    $selfCreated = false;
    if (! $selfLeave) {
        $selfLeave = App\Models\LeaveRequest::findOrFail((int) $leaves->create([
            'subject_type' => 'employee',
            'employee_id' => $approver->id,
            'type' => 'annual',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-01',
            'reason' => $selfReason,
        ], $kepsek)['id']);
        $leaves->submit($selfLeave->id, (int) $selfLeave->version, $kepsek);
        $selfLeave = $selfLeave->fresh();
        $selfCreated = true;
    }

    $subjectReason = 'UAT-P08 synthetic subject decision leave for manual review.';
    $subjectLeave = App\Models\LeaveRequest::query()->where('reason', $subjectReason)->first();
    $subjectCreated = false;
    if (! $subjectLeave) {
        $subjectLeave = App\Models\LeaveRequest::findOrFail((int) $leaves->create([
            'subject_type' => 'employee',
            'employee_id' => $approver->id,
            'type' => 'sick',
            'start_date' => '2026-12-02',
            'end_date' => '2026-12-02',
            'reason' => $subjectReason,
        ], $operator)['id']);
        $leaves->submit($subjectLeave->id, (int) $subjectLeave->version, $operator);
        $subjectLeave = $subjectLeave->fresh();
        $subjectCreated = true;
    }

    $approvalMarker = 'UAT-P08 decision fixture approval leave ';
    $rejectionMarker = 'UAT-P08 decision fixture rejection leave ';
    $cancellationMarker = 'UAT-P08 decision fixture approved cancellation leave ';

    $approvedDone = App\Models\LeaveDecision::where('action', 'approved')
        ->where('reason', 'like', $approvalMarker.'%')->count();
    $rejectedDone = App\Models\LeaveDecision::where('action', 'rejected')
        ->where('reason', 'like', $rejectionMarker.'%')->count();
    $cancelledDone = App\Models\LeaveDecision::where('action', 'cancelled')
        ->where('reason', 'like', $cancellationMarker.'%')->count();

    $candidateQuery = fn () => App\Models\LeaveRequest::query()
        ->where('status', App\Models\LeaveRequest::STATUS_SUBMITTED)
        ->where('requested_by', '!=', $kepsek->id)
        ->where(fn ($query) => $query->whereNull('employee_id')->orWhere('employee_id', '!=', $approver->id))
        ->whereDoesntHave('decisions')
        ->orderBy('start_date')
        ->orderBy('id');

    $approvedThisRun = 0;
    $rejectedThisRun = 0;
    $cancelledThisRun = 0;

    foreach ($candidateQuery()->limit(max(0, 20 - $approvedDone))->get() as $candidate) {
        $leaves->approve(
            $candidate->id,
            $approvalMarker.$candidate->id.'.',
            (int) $candidate->version,
            $kepsek,
        );
        $approvedThisRun++;
    }

    foreach ($candidateQuery()->limit(max(0, 10 - $rejectedDone))->get() as $candidate) {
        $leaves->reject(
            $candidate->id,
            $rejectionMarker.$candidate->id.'.',
            (int) $candidate->version,
            $kepsek,
        );
        $rejectedThisRun++;
    }

    $pendingCancellations = App\Models\LeaveRequest::query()
        ->where('status', App\Models\LeaveRequest::STATUS_APPROVED)
        ->whereHas('decisions', fn ($query) => $query->where('action', 'approved')->where('reason', 'like', $approvalMarker.'%'))
        ->whereDoesntHave('decisions', fn ($query) => $query->where('action', 'cancelled'))
        ->orderBy('start_date')
        ->orderBy('id')
        ->limit(max(0, 5 - $cancelledDone))
        ->get();

    foreach ($pendingCancellations as $candidate) {
        $leaves->cancel(
            $candidate->id,
            $cancellationMarker.$candidate->id.'.',
            (int) $candidate->version,
            $kepsek,
        );
        $cancelledThisRun++;
    }

    $stillNeeded = max(0, 5 - $cancelledDone) - $cancelledThisRun;

    if ($stillNeeded > 0) {
        foreach ($candidateQuery()->limit($stillNeeded)->get() as $candidate) {
            $leaves->approve(
                $candidate->id,
                $approvalMarker.$candidate->id.'.',
                (int) $candidate->version,
                $kepsek,
            );
            $approvedThisRun++;

            $candidate->refresh();
            $leaves->cancel(
                $candidate->id,
                $cancellationMarker.$candidate->id.'.',
                (int) $candidate->version,
                $kepsek,
            );
            $cancelledThisRun++;
        }
    }

    $byStatus = App\Models\LeaveRequest::query()->pluck('status')->countBy();
    $byAction = App\Models\LeaveDecision::query()->pluck('action')->countBy();

    fwrite(STDOUT, 'Approver employee: UAT-P08 Approver 01 #'.$approver->id
        .' ('.($approverCreated ? 'created' : 'reused').'), linked to uat.kepsek.'."\n");
    fwrite(STDOUT, 'Self leave: #'.$selfLeave->id.' '.$selfLeave->status.' ('.($selfCreated ? 'created' : 'reused').').'."\n");
    fwrite(STDOUT, 'Subject-self leave: #'.$subjectLeave->id.' '.$subjectLeave->status.' ('.($subjectCreated ? 'created' : 'reused').').'."\n");
    fwrite(STDOUT, 'Decision fixture: approvals '.($approvedDone + $approvedThisRun).' (target 20), rejections '
        .($rejectedDone + $rejectedThisRun).' (target 10), approved cancellations '.($cancelledDone + $cancelledThisRun)
        .' (target 5); this run: '.$approvedThisRun.' approvals, '.$rejectedThisRun.' rejections, '
        .$cancelledThisRun.' cancellations.'."\n");
    fwrite(STDOUT, 'Leaves: '.App\Models\LeaveRequest::count().' total; draft '.($byStatus['draft'] ?? 0)
        .', submitted '.($byStatus['submitted'] ?? 0).', cancelled '.($byStatus['cancelled'] ?? 0)
        .', approved '.($byStatus['approved'] ?? 0).', rejected '.($byStatus['rejected'] ?? 0).'.'."\n");
    fwrite(STDOUT, 'Decisions: '.App\Models\LeaveDecision::count().' total; approved '.($byAction['approved'] ?? 0)
        .', rejected '.($byAction['rejected'] ?? 0).', cancelled '.($byAction['cancelled'] ?? 0).'.'."\n");
    exit(0);
}

if ($command === 'status') {
    fwrite(STDOUT, "[ISOLATION GUARD PASS] Dedicated phase-08 SQLite UAT; no MariaDB connection.\n");
    $year = App\Models\AcademicSetting::find(App\Models\AcademicSetting::SINGLETON_ID)?->activeSchoolYear;
    fwrite(STDOUT, 'Active school year: '.($year ? $year->name.' ('.$year->start_date?->format('Y-m-d').' to '.$year->end_date?->format('Y-m-d').')' : 'none')."\n");
    $leaveQuery = App\Models\LeaveRequest::query();
    $byStatus = (clone $leaveQuery)->pluck('status')->countBy();
    $bySubject = (clone $leaveQuery)->pluck('subject_type')->countBy();
    fwrite(STDOUT, 'Leaves: '.(clone $leaveQuery)->count().' total; draft '.($byStatus['draft'] ?? 0)
        .', submitted '.($byStatus['submitted'] ?? 0).', cancelled '.($byStatus['cancelled'] ?? 0)
        .', approved '.($byStatus['approved'] ?? 0).', rejected '.($byStatus['rejected'] ?? 0)
        .'; student '.($bySubject['student'] ?? 0).', employee '.($bySubject['employee'] ?? 0).".\n");
    $attachmentFiles = Illuminate\Support\Facades\Storage::disk((string) config('leaves.attachment.disk'))->allFiles((string) config('leaves.attachment.path'));
    fwrite(STDOUT, 'Attachments: '.App\Models\LeaveAttachment::count().' rows, '.count($attachmentFiles)
        .' private files; decisions '.App\Models\LeaveDecision::count().'.'."\n");
    $byAction = App\Models\LeaveDecision::query()->pluck('action')->countBy();
    fwrite(STDOUT, 'Decisions by action: approved '.($byAction['approved'] ?? 0).', rejected '.($byAction['rejected'] ?? 0)
        .', cancelled '.($byAction['cancelled'] ?? 0).'.'."\n");
    fwrite(STDOUT, 'Students: '.App\Models\Student::count().' total; '
        .App\Models\StudentRombel::query()->whereNull('archived_at')->whereNull('latest_student_exit_date')
            ->whereHas('schoolRombel', fn ($query) => $query->where('school_year_id', $year?->id))->count()
        ." active memberships in the active year.\n");
    fwrite(STDOUT, 'Employees: '.App\Models\Employee::query()->count().' total; '
        .App\Models\Employee::whereNull('exit_date')->count().' active; '
        .App\Models\Employee::whereNotNull('user_id')->count()." linked.\n");
    fwrite(STDOUT, 'Users: '.App\Models\User::count().' (uat.admin, uat.operator, uat.kepsek, uat.waka, uat.guru, uat.inactive).'."\n");
    fwrite(STDOUT, 'Storage root: '.config('filesystems.disks.local.root')."\n");
    exit(0);
}

throw new RuntimeException('Allowed commands: init, prepare, prepare-leaves, prepare-decisions, status. Serve using PHP -S 127.0.0.1:18026 with this router.');
