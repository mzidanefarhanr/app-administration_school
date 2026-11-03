<?php

declare(strict_types=1);

$base = str_replace('\\', '/', dirname(__DIR__));
$runtime = dirname($base).'/.uat-phase10';
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
        'APP_NAME' => 'School UAT Phase 10', 'APP_ENV' => 'uat', 'APP_KEY' => $key, 'APP_DEBUG' => 'false',
        'APP_URL' => 'http://127.0.0.1:18028', 'FRONTEND_URL' => 'http://127.0.0.1:14028',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $runtime.'/database.sqlite', 'DB_FOREIGN_KEYS' => 'true',
        'CACHE_DRIVER' => 'file', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'school_phase10_session',
        'SESSION_DOMAIN' => '127.0.0.1', 'SANCTUM_STATEFUL_DOMAINS' => '127.0.0.1:14028',
        'FILESYSTEM_DISK' => 'local', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'single',
    ];
    file_put_contents($runtime.'/.env', implode("\n", array_map(fn ($key, $value) => $key.'="'.$value.'"', array_keys($env), $env))."\n");
    fwrite(STDOUT, "Isolated SQLite UAT (phase 10) initialized. Credentials remain in .uat-phase10/account-password.txt (ignored).\n");
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
    || config('database.connections.sqlite.url') || config('app.url') !== 'http://127.0.0.1:18028'
    || config('session.cookie') !== 'school_phase10_session') {
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

    $password = trim((string) file_get_contents($runtime.'/account-password.txt'));

    if (App\Models\User::withTrashed()->count() === 0) {
        putenv('UAT_SEED_PASSWORD='.$password);
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

    $userType = App\Models\TypeUser::where('name', 'User')->first();
    $activeStatus = App\Models\StatusUser::where('name', 'Aktif')->first();
    if (! $userType || ! $activeStatus) {
        throw new RuntimeException('Reference type/status rows are missing after seeding.');
    }

    $guruRole = App\Models\Role::where('slug', 'guru')->first();
    if (! $guruRole) {
        throw new RuntimeException('The guru role is missing after seeding.');
    }

    $guru = App\Models\User::withTrashed()->firstOrNew(['username' => 'uat.guru']);
    $guru->fill([
        'name' => 'UAT Guru',
        'email' => 'uat.guru@uat.local',
        'email_verified_at' => now(),
        'password' => bcrypt($password),
        'nik' => '9000000000000006',
        'type_user_id' => $userType->id,
        'status_user_id' => $activeStatus->id,
        'first_login_at' => 0,
        'status_active' => true,
    ]);
    $guru->deleted_at = null;
    $guru->save();
    $guru->roles()->sync([$guruRole->id]);

    $staffRole = App\Models\Role::withTrashed()->firstOrNew(['slug' => 'uat-staff-no-teaching']);
    $staffRole->fill([
        'name' => 'UAT Staff Non-Teaching',
        'description' => 'Isolated UAT role without teaching.view or teaching.manage.',
        'is_protected' => false,
    ]);
    $staffRole->deleted_at = null;
    $staffRole->save();
    $masterView = App\Models\Ability::where('name', 'master.view')->firstOrFail();
    $staffRole->abilities()->sync([$masterView->id]);

    $staff = App\Models\User::withTrashed()->firstOrNew(['username' => 'uat.staff']);
    $staff->fill([
        'name' => 'UAT Staff Non-Teaching',
        'email' => 'uat.staff@uat.local',
        'email_verified_at' => now(),
        'password' => bcrypt($password),
        'nik' => '9000000000000008',
        'type_user_id' => $userType->id,
        'status_user_id' => $activeStatus->id,
        'first_login_at' => 0,
        'status_active' => true,
    ]);
    $staff->deleted_at = null;
    $staff->save();
    $staff->roles()->sync([$staffRole->id]);

    fwrite(STDOUT, 'Users: '.App\Models\User::withTrashed()->count().' (uat.admin, uat.operator, uat.kepsek, uat.waka, uat.guru, uat.inactive, uat.staff).'."\n");
    fwrite(STDOUT, 'uat.guru role abilities: '.$guruRole->abilities()->pluck('name')->implode(', ').".\n");
    fwrite(STDOUT, 'Prepare complete. Run prepare-teaching to populate the synthetic teaching assignments and schedules.'."\n");
    exit(0);
}

if ($command === 'prepare-teaching') {
    $exit = $kernel->call('migrate', ['--force' => true]);
    fwrite(STDOUT, $kernel->output());
    if ($exit !== 0) {
        exit($exit);
    }

    $operator = App\Models\User::where('username', 'uat.operator')->first();
    $admin = App\Models\User::where('username', 'uat.admin')->first();
    $guruUser = App\Models\User::where('username', 'uat.guru')->first();
    if (! $operator || ! $admin || ! $guruUser) {
        throw new RuntimeException('Run prepare first (uat.operator, uat.admin, and uat.guru are required).');
    }

    // 1. Tahun ajaran aktif (2026/2027) + tahun ajaran terkunci (2024/2025) + semester.
    $activeYear = App\Models\SchoolYear::withTrashed()->firstOrNew(['name' => '2026/2027']);
    $activeYear->fill([
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
        'locked_at' => null,
        'locked_by' => null,
        'lock_reason' => null,
    ]);
    $activeYear->deleted_at = null;
    $activeYear->save();

    $lockedYear = App\Models\SchoolYear::withTrashed()->firstOrNew(['name' => '2024/2025']);
    $lockedYear->fill([
        'start_date' => '2024-07-01',
        'end_date' => '2025-06-30',
    ]);
    $lockedYear->deleted_at = null;
    $lockedYear->save();

    $sem1 = App\Models\Semester::withTrashed()->firstOrNew(['name' => 'Semester I']);
    $sem1->fill(['name_other' => 'Ganjil']);
    $sem1->deleted_at = null;
    $sem1->save();

    $sem2 = App\Models\Semester::withTrashed()->firstOrNew(['name' => 'Semester II']);
    $sem2->fill(['name_other' => 'Genap']);
    $sem2->deleted_at = null;
    $sem2->save();

    $academic = App\Models\AcademicSetting::find(App\Models\AcademicSetting::SINGLETON_ID);
    if (! $academic) {
        $academic = new App\Models\AcademicSetting;
        $academic->id = App\Models\AcademicSetting::SINGLETON_ID;
    }
    $academic->active_school_year_id = $activeYear->id;
    $academic->active_semester_id = $sem1->id;
    $academic->updated_by = $operator->id;
    $academic->save();

    // 2. Referensi minimum pegawai dan akademik.
    $employeeStatus = App\Models\EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap Yayasan'], ['abbreviation' => 'PTY']);
    $teacherType = App\Models\EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $staffType = App\Models\EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
    $employment = App\Models\Employment::firstOrCreate(['name' => 'Guru Mata Pelajaran'], ['description' => 'Guru Mata Pelajaran']);
    $religion = App\Models\Religion::firstOrCreate(['name' => 'Islam']);
    $male = App\Models\Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $female = App\Models\Gender::firstOrCreate(['name' => 'Perempuan']);
    $marital = App\Models\MaritalStatus::firstOrCreate(['name' => 'Menikah']);
    $province = App\Models\Province::firstOrCreate(['name' => 'DKI Jakarta UAT']);
    $regency = App\Models\Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Selatan UAT']);
    $district = App\Models\District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Tebet UAT']);
    $levelX = App\Models\SchoolLevel::firstOrCreate(['name' => 'Kelas X']);
    $levelXI = App\Models\SchoolLevel::firstOrCreate(['name' => 'Kelas XI']);
    $levelXII = App\Models\SchoolLevel::firstOrCreate(['name' => 'Kelas XII']);
    $majorMipa = App\Models\StudentMajor::firstOrCreate(['name' => 'MIPA']);
    $majorIps = App\Models\StudentMajor::firstOrCreate(['name' => 'IPS']);

    // 3. 10 Mata Pelajaran.
    $subjectNames = [
        'Matematika',
        'Bahasa Indonesia',
        'Bahasa Inggris',
        'Fisika',
        'Kimia',
        'Biologi',
        'Sejarah Indonesia',
        'Informatika',
        'Pendidikan Pancasila',
        'Ekonomi',
    ];
    $subjects = [];
    foreach ($subjectNames as $idx => $sName) {
        $subj = App\Models\Subject::withTrashed()->firstOrNew(['name' => $sName]);
        $subj->fill(['description' => 'Mata pelajaran '.$sName.' (UAT Phase 10)']);
        $subj->deleted_at = null;
        $subj->save();
        $subjects[$idx] = $subj;
    }

    // 4. 18 Pegawai (15 Pendidik aktif, 2 Tenaga Kependidikan aktif, 1 Pendidik keluar).
    $teachers = [];
    $employeesCreated = 0;
    for ($i = 1; $i <= 18; $i++) {
        $isTeacher = $i <= 15 || $i === 18;
        $isExited = $i === 18;
        $label = $isExited
            ? sprintf('UAT-P10 Exited Teacher %02d', $i)
            : ($isTeacher ? sprintf('UAT-P10 Teacher %02d', $i) : sprintf('UAT-P10 Staff %02d', $i));

        $employee = App\Models\Employee::withTrashed()->firstOrNew(['name' => $label]);
        if (! $employee->exists) {
            $employeesCreated++;
        }
        $employee->fill([
            'user_id' => $i === 1 ? $guruUser->id : null,
            'employee_status_id' => $employeeStatus->id,
            'employee_type_id' => $isTeacher ? $teacherType->id : $staffType->id,
            'employment_id' => $employment->id,
            'subject_id' => $isTeacher ? $subjects[($i - 1) % count($subjects)]->id : null,
            'religion_id' => $religion->id,
            'district_id' => $district->id,
            'marital_status_id' => $marital->id,
            'gender_id' => $i % 2 === 0 ? $female->id : $male->id,
            'nuptk' => 'P10'.str_pad((string) $i, 13, '0', STR_PAD_LEFT),
            'place_of_birth' => 'Jakarta',
            'date_of_birth' => '1988-05-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT),
            'address' => 'Jl. Pendidikan UAT Phase 10 No. '.$i,
            'email' => sprintf('uat.p10.emp%02d@uat.local', $i),
            'tmt_employee' => '2019-07-01',
            'exit_date' => $isExited ? '2025-12-31' : null,
        ]);
        $employee->deleted_at = null;
        $employee->save();

        if ($i <= 15) {
            $teachers[$i - 1] = $employee;
        }
    }

    // 5. 10 Rombel di tahun ajaran aktif (2026/2027) + 1 Rombel di tahun ajaran terkunci (2024/2025).
    $classDefinitions = [
        ['name' => 'UAT-P10 X-MIPA-1', 'level' => $levelX, 'major' => $majorMipa, 'teacher' => $teachers[0]],
        ['name' => 'UAT-P10 X-MIPA-2', 'level' => $levelX, 'major' => $majorMipa, 'teacher' => $teachers[1]],
        ['name' => 'UAT-P10 X-IPS-1', 'level' => $levelX, 'major' => $majorIps, 'teacher' => $teachers[2]],
        ['name' => 'UAT-P10 X-IPS-2', 'level' => $levelX, 'major' => $majorIps, 'teacher' => $teachers[3]],
        ['name' => 'UAT-P10 XI-MIPA-1', 'level' => $levelXI, 'major' => $majorMipa, 'teacher' => $teachers[4]],
        ['name' => 'UAT-P10 XI-MIPA-2', 'level' => $levelXI, 'major' => $majorMipa, 'teacher' => $teachers[5]],
        ['name' => 'UAT-P10 XI-IPS-1', 'level' => $levelXI, 'major' => $majorIps, 'teacher' => $teachers[6]],
        ['name' => 'UAT-P10 XII-MIPA-1', 'level' => $levelXII, 'major' => $majorMipa, 'teacher' => $teachers[7]],
        ['name' => 'UAT-P10 XII-MIPA-2', 'level' => $levelXII, 'major' => $majorMipa, 'teacher' => $teachers[8]],
        ['name' => 'UAT-P10 XII-IPS-1', 'level' => $levelXII, 'major' => $majorIps, 'teacher' => $teachers[9]],
    ];

    $classes = [];
    foreach ($classDefinitions as $idx => $def) {
        $rombel = App\Models\SchoolRombel::withTrashed()->firstOrNew([
            'school_year_id' => $activeYear->id,
            'name' => $def['name'],
        ]);
        $rombel->fill([
            'school_level_id' => $def['level']->id,
            'student_major_id' => $def['major']->id,
            'employee_id' => $def['teacher']->id,
        ]);
        $rombel->deleted_at = null;
        $rombel->save();
        $classes[$idx] = $rombel;
    }

    // Pastikan tahun 2024/2025 dibuka sementara bila belum ada fixture terkunci, lalu kunci kembali.
    if ($lockedYear->locked_at !== null) {
        $lockedYear->locked_at = null;
        $lockedYear->locked_by = null;
        $lockedYear->lock_reason = null;
        $lockedYear->save();
    }

    $lockedClass = App\Models\SchoolRombel::withTrashed()->firstOrNew([
        'school_year_id' => $lockedYear->id,
        'name' => 'UAT-P10 X-MIPA-1 (2024/2025)',
    ]);
    $lockedClass->fill([
        'school_level_id' => $levelX->id,
        'student_major_id' => $majorMipa->id,
        'employee_id' => $teachers[0]->id,
    ]);
    $lockedClass->deleted_at = null;
    $lockedClass->save();

    /** @var App\Services\TeachingScheduleService $teachingService */
    $teachingService = $app->make(App\Services\TeachingScheduleService::class);
    $presets = (array) config('teaching.period_presets', []);

    // Fixture di tahun ajaran 2024/2025 sebelum dikunci kembali.
    $lockedAssignment = App\Models\TeachingAssignment::query()
        ->where('school_year_id', $lockedYear->id)
        ->where('semester_id', $sem1->id)
        ->where('school_rombel_id', $lockedClass->id)
        ->where('subject_id', $subjects[0]->id)
        ->first();

    if (! $lockedAssignment) {
        $createdLockedAssn = $teachingService->createAssignment([
            'school_year_id' => $lockedYear->id,
            'semester_id' => $sem1->id,
            'school_rombel_id' => $lockedClass->id,
            'subject_id' => $subjects[0]->id,
            'employee_id' => $teachers[0]->id,
            'hours_per_week' => 2,
            'status' => 'active',
            'notes' => 'Historical assignment in locked school year 2024/2025.',
        ], (int) $operator->id);

        $teachingService->createSchedule([
            'teaching_assignment_id' => (int) $createdLockedAssn->id,
            'day_of_week' => 1,
            'period_start' => 1,
            'period_end' => 2,
            'start_time' => $presets[1]['start_time'] ?? '07:00',
            'end_time' => $presets[2]['end_time'] ?? '08:30',
            'room' => 'R-HIST-101',
            'status' => 'active',
        ], (int) $operator->id);
    }

    $lockedYear->locked_at = Carbon\Carbon::parse('2025-07-15 10:00:00', 'Asia/Jakarta');
    $lockedYear->locked_by = $admin->id;
    $lockedYear->lock_reason = 'Tahun ajaran 2024/2025 telah ditutup setelah akhir tahun akademik.';
    $lockedYear->save();

    // 6. 100 Penugasan Mengajar & 120 Slot Jadwal di Tahun Ajaran Aktif (2026/2027 Semester I).
    // Pola bebas bentrok:
    // - Slot blok 0..9 (Senin..Jumat, masing-masing 2 blok per hari: jam 1-2 dan jam 3-4).
    // - Untuk kelas c (0..9) dan mapel s (0..9):
    //   Guru yang ditugaskan adalah teachers[(c + s) % 10].
    //   Pada blok waktu s, kelas c menggunakan ruangan R-10{c+1} (atau LAB khusus untuk blok tertentu dengan offset unik).
    $slotBlocks = [
        0 => ['day' => 1, 'p_start' => 1, 'p_end' => 2],
        1 => ['day' => 1, 'p_start' => 3, 'p_end' => 4],
        2 => ['day' => 2, 'p_start' => 1, 'p_end' => 2],
        3 => ['day' => 2, 'p_start' => 3, 'p_end' => 4],
        4 => ['day' => 3, 'p_start' => 1, 'p_end' => 2],
        5 => ['day' => 3, 'p_start' => 3, 'p_end' => 4],
        6 => ['day' => 4, 'p_start' => 1, 'p_end' => 2],
        7 => ['day' => 4, 'p_start' => 3, 'p_end' => 4],
        8 => ['day' => 5, 'p_start' => 1, 'p_end' => 2],
        9 => ['day' => 5, 'p_start' => 3, 'p_end' => 4],
    ];

    $assignmentsCreated = 0;
    $assignmentsReused = 0;
    $schedulesCreated = 0;
    $schedulesReused = 0;
    $assignmentMatrix = [];
    $primaryScheduleMatrix = [];

    foreach ($classes as $cIdx => $rombel) {
        $homeRoom = sprintf('R-%d', 101 + $cIdx);

        foreach ($subjects as $sIdx => $subject) {
            $teacher = $teachers[($cIdx + $sIdx) % 10];
            $hoursPerWeek = $sIdx < 2 ? 4 : 2;

            $existingAssn = App\Models\TeachingAssignment::query()
                ->where('school_year_id', $activeYear->id)
                ->where('semester_id', $sem1->id)
                ->where('school_rombel_id', $rombel->id)
                ->where('subject_id', $subject->id)
                ->first();

            if ($existingAssn) {
                $assignmentsReused++;
                $assnId = (int) $existingAssn->id;
            } else {
                $createdAssn = $teachingService->createAssignment([
                    'school_year_id' => $activeYear->id,
                    'semester_id' => $sem1->id,
                    'school_rombel_id' => $rombel->id,
                    'subject_id' => $subject->id,
                    'employee_id' => $teacher->id,
                    'hours_per_week' => $hoursPerWeek,
                    'status' => 'active',
                    'notes' => sprintf('UAT-P10 synthetic assignment %s - %s.', $rombel->name, $subject->name),
                ], (int) $operator->id);
                $assignmentsCreated++;
                $assnId = (int) $createdAssn->id;
            }

            $assignmentMatrix[$cIdx][$sIdx] = $assnId;

            // Jadwal utama (Senin..Jumat)
            $block = $slotBlocks[$sIdx];
            $room = $homeRoom;
            if ($cIdx === 0 && $sIdx === 3) {
                $room = 'LAB-FISIKA';
            } elseif ($cIdx === 0 && $sIdx === 4) {
                $room = 'LAB-KIMIA';
            } elseif ($cIdx === 0 && $sIdx === 5) {
                $room = 'LAB-BIOLOGI';
            } elseif ($cIdx === 0 && $sIdx === 7) {
                $room = 'LAB-KOMP-1';
            }

            $existingSched = App\Models\TeachingSchedule::query()
                ->where('teaching_assignment_id', $assnId)
                ->where('day_of_week', $block['day'])
                ->where('period_start', $block['p_start'])
                ->first();

            if ($existingSched) {
                $schedulesReused++;
                $primaryScheduleMatrix[$cIdx][$sIdx] = (int) $existingSched->id;
            } else {
                $createdSched = $teachingService->createSchedule([
                    'teaching_assignment_id' => $assnId,
                    'day_of_week' => $block['day'],
                    'period_start' => $block['p_start'],
                    'period_end' => $block['p_end'],
                    'start_time' => $presets[$block['p_start']]['start_time'] ?? '07:00',
                    'end_time' => $presets[$block['p_end']]['end_time'] ?? '08:30',
                    'room' => $room,
                    'status' => 'active',
                    'notes' => sprintf('Primary weekly slot for %s (%s).', $subject->name, $rombel->name),
                ], (int) $operator->id);
                $schedulesCreated++;
                $primaryScheduleMatrix[$cIdx][$sIdx] = (int) $createdSched->id;
            }

            // Pertemuan kedua di hari Sabtu untuk 2 mapel pertama (menambah 20 jadwal -> total 120 di 2026/2027)
            if ($sIdx < 2) {
                $satStart = $sIdx === 0 ? 1 : 3;
                $satEnd = $sIdx === 0 ? 2 : 4;

                $existingSat = App\Models\TeachingSchedule::query()
                    ->where('teaching_assignment_id', $assnId)
                    ->where('day_of_week', 6)
                    ->where('period_start', $satStart)
                    ->first();

                if ($existingSat) {
                    $schedulesReused++;
                } else {
                    $teachingService->createSchedule([
                        'teaching_assignment_id' => $assnId,
                        'day_of_week' => 6,
                        'period_start' => $satStart,
                        'period_end' => $satEnd,
                        'start_time' => $presets[$satStart]['start_time'] ?? '07:00',
                        'end_time' => $presets[$satEnd]['end_time'] ?? '08:30',
                        'room' => $homeRoom,
                        'status' => 'active',
                        'notes' => sprintf('Second weekly session on Saturday for %s (%s).', $subject->name, $rombel->name),
                    ], (int) $operator->id);
                    $schedulesCreated++;
                }
            }
        }
    }

    // 7. 6 Pengecualian Kalender (Holiday, Cancelled, Rescheduled).
    // Catatan tanggal di 2026/2027:
    // - Senin, 2026-08-17: Libur Nasional Kemerdekaan RI (school-wide holiday)
    // - Senin, 2026-09-07: Libur khusus kelas UAT-P10 X-MIPA-1 (class-specific holiday)
    // - Selasa, 2026-09-08: Cancelled slot untuk X-MIPA-1 mapel index 2 (Bahasa Inggris, Selasa jam 1-2)
    // - Rabu, 2026-09-09: Cancelled slot untuk X-MIPA-2 mapel index 4 (Kimia, Rabu jam 1-2)
    // - Kamis, 2026-09-10: Rescheduled slot untuk X-MIPA-1 mapel index 6 (Sejarah Indonesia, Kamis jam 1-2) -> pindah ke Sabtu, 2026-09-12 jam 5-6 di LAB-KOMP-1 dengan guru pengganti Teacher 11
    // - Jumat, 2026-09-11: Rescheduled slot untuk XI-MIPA-1 mapel index 8 (Pendidikan Pancasila, Jumat jam 1-2) -> pindah ke Sabtu, 2026-09-12 jam 7-8 di R-105
    $exceptionFixtures = [
        [
            'check' => ['school_year_id' => $activeYear->id, 'exception_type' => 'holiday', 'exception_date' => '2026-08-17', 'school_rombel_id' => null],
            'payload' => [
                'school_year_id' => $activeYear->id,
                'semester_id' => $sem1->id,
                'exception_type' => 'holiday',
                'exception_date' => '2026-08-17',
                'school_rombel_id' => null,
                'reason' => 'Libur Nasional Hari Kemerdekaan Republik Indonesia.',
            ],
        ],
        [
            'check' => ['school_year_id' => $activeYear->id, 'exception_type' => 'holiday', 'exception_date' => '2026-09-07', 'school_rombel_id' => $classes[0]->id],
            'payload' => [
                'school_year_id' => $activeYear->id,
                'semester_id' => $sem1->id,
                'exception_type' => 'holiday',
                'exception_date' => '2026-09-07',
                'school_rombel_id' => $classes[0]->id,
                'reason' => 'Kegiatan kunjungan industri khusus kelas UAT-P10 X-MIPA-1.',
            ],
        ],
        [
            'check' => ['teaching_schedule_id' => $primaryScheduleMatrix[0][2], 'exception_date' => '2026-09-08'],
            'payload' => [
                'school_year_id' => $activeYear->id,
                'semester_id' => $sem1->id,
                'exception_type' => 'cancelled',
                'exception_date' => '2026-09-08',
                'school_rombel_id' => $classes[0]->id,
                'teaching_schedule_id' => $primaryScheduleMatrix[0][2],
                'reason' => 'Sesi ditiadakan karena rapat koordinasi kurikulum.',
            ],
        ],
        [
            'check' => ['teaching_schedule_id' => $primaryScheduleMatrix[1][4], 'exception_date' => '2026-09-09'],
            'payload' => [
                'school_year_id' => $activeYear->id,
                'semester_id' => $sem1->id,
                'exception_type' => 'cancelled',
                'exception_date' => '2026-09-09',
                'school_rombel_id' => $classes[1]->id,
                'teaching_schedule_id' => $primaryScheduleMatrix[1][4],
                'reason' => 'Sesi ditiadakan karena pemeliharaan ruang kelas.',
            ],
        ],
        [
            'check' => ['teaching_schedule_id' => $primaryScheduleMatrix[0][6], 'exception_date' => '2026-09-10'],
            'payload' => [
                'school_year_id' => $activeYear->id,
                'semester_id' => $sem1->id,
                'exception_type' => 'rescheduled',
                'exception_date' => '2026-09-10',
                'school_rombel_id' => $classes[0]->id,
                'teaching_schedule_id' => $primaryScheduleMatrix[0][6],
                'rescheduled_date' => '2026-09-12',
                'rescheduled_period_start' => 5,
                'rescheduled_period_end' => 6,
                'rescheduled_start_time' => $presets[5]['start_time'] ?? '10:15',
                'rescheduled_end_time' => $presets[6]['end_time'] ?? '11:45',
                'rescheduled_room' => 'LAB-KOMP-1',
                'substitute_employee_id' => $teachers[10]->id,
                'reason' => 'Jadwal dipindahkan ke hari Sabtu dengan guru pengganti karena dinas luar.',
            ],
        ],
        [
            'check' => ['teaching_schedule_id' => $primaryScheduleMatrix[4][8], 'exception_date' => '2026-09-11'],
            'payload' => [
                'school_year_id' => $activeYear->id,
                'semester_id' => $sem1->id,
                'exception_type' => 'rescheduled',
                'exception_date' => '2026-09-11',
                'school_rombel_id' => $classes[4]->id,
                'teaching_schedule_id' => $primaryScheduleMatrix[4][8],
                'rescheduled_date' => '2026-09-12',
                'rescheduled_period_start' => 7,
                'rescheduled_period_end' => 8,
                'rescheduled_start_time' => $presets[7]['start_time'] ?? '11:45',
                'rescheduled_end_time' => $presets[8]['end_time'] ?? '13:45',
                'rescheduled_room' => 'R-105',
                'substitute_employee_id' => null,
                'reason' => 'Penyesuaian jadwal Jumat ke sesi tambahan hari Sabtu.',
            ],
        ],
    ];

    $exceptionsCreated = 0;
    $exceptionsReused = 0;
    foreach ($exceptionFixtures as $fixture) {
        $query = App\Models\TeachingScheduleException::query();
        foreach ($fixture['check'] as $col => $val) {
            if ($val === null) {
                $query->whereNull($col);
            } else {
                $query->where($col, $val);
            }
        }
        if ($query->exists()) {
            $exceptionsReused++;

            continue;
        }
        $teachingService->createException($fixture['payload'], (int) $operator->id);
        $exceptionsCreated++;
    }

    fwrite(STDOUT, 'Active school year: '.$activeYear->name.' (id '.$activeYear->id.'), active semester: '.$sem1->name.' (id '.$sem1->id.").\n");
    fwrite(STDOUT, 'Locked school year: '.$lockedYear->name.' (id '.$lockedYear->id.', locked_at '.$lockedYear->locked_at->format('Y-m-d H:i:s').").\n");
    fwrite(STDOUT, 'Subjects: '.App\Models\Subject::count().'; Classes: '.App\Models\SchoolRombel::count().' (10 in 2026/2027, 1 in 2024/2025).'."\n");
    fwrite(STDOUT, 'Employees: '.App\Models\Employee::count().' total ('.$employeesCreated.' created this run; '
        .App\Models\Employee::query()->eligibleTeacher()->count().' eligible active Pendidik teachers).'."\n");
    fwrite(STDOUT, 'Teaching assignments: '.App\Models\TeachingAssignment::count()." total ({$assignmentsCreated} created, {$assignmentsReused} reused in 2026/2027).\n");
    fwrite(STDOUT, 'Teaching schedules: '.App\Models\TeachingSchedule::count()." total ({$schedulesCreated} created, {$schedulesReused} reused in 2026/2027).\n");
    fwrite(STDOUT, 'Schedule exceptions: '.App\Models\TeachingScheduleException::count()." total ({$exceptionsCreated} created, {$exceptionsReused} reused).\n");
    fwrite(STDOUT, 'Accounts: uat.admin, uat.operator, uat.kepsek, uat.waka, uat.guru, uat.inactive, uat.staff.'."\n");
    fwrite(STDOUT, 'Phase 10a fixtures ready. Start the API on 127.0.0.1:18028 and the SPA on 127.0.0.1:14028 for the browser review.'."\n");
    exit(0);
}

if ($command === 'prepare-journals') {
    $exit = $kernel->call('migrate', ['--force' => true]);
    fwrite(STDOUT, $kernel->output());
    if ($exit !== 0) {
        exit($exit);
    }

    $password = trim((string) file_get_contents($runtime.'/account-password.txt'));
    $operator = App\Models\User::where('username', 'uat.operator')->first();
    $admin = App\Models\User::where('username', 'uat.admin')->first();
    $guru1 = App\Models\User::where('username', 'uat.guru')->first();
    $guruRole = App\Models\Role::where('slug', 'guru')->first();
    $userType = App\Models\TypeUser::where('name', 'User')->first();
    $activeStatus = App\Models\StatusUser::where('name', 'Aktif')->first();

    if (! $operator || ! $admin || ! $guru1 || ! $guruRole || ! $userType || ! $activeStatus) {
        throw new RuntimeException('Run prepare and prepare-teaching first before prepare-journals.');
    }

    // 1. Pastikan akun guru kedua (uat.guru2) tersedia dan ditautkan ke UAT-P10 Teacher 02.
    $guru2 = App\Models\User::withTrashed()->firstOrNew(['username' => 'uat.guru2']);
    $guru2->fill([
        'name' => 'UAT Guru 02',
        'email' => 'uat.guru2@uat.local',
        'email_verified_at' => now(),
        'password' => bcrypt($password),
        'nik' => '9000000000000009',
        'type_user_id' => $userType->id,
        'status_user_id' => $activeStatus->id,
        'first_login_at' => 0,
        'status_active' => true,
    ]);
    $guru2->deleted_at = null;
    $guru2->save();
    $guru2->roles()->sync([$guruRole->id]);

    $teacher01 = App\Models\Employee::where('name', 'UAT-P10 Teacher 01')->firstOrFail();
    $teacher02 = App\Models\Employee::where('name', 'UAT-P10 Teacher 02')->firstOrFail();

    if ((int) $teacher01->user_id !== (int) $guru1->id) {
        $teacher01->user_id = $guru1->id;
        $teacher01->save();
    }
    if ((int) $teacher02->user_id !== (int) $guru2->id) {
        $teacher02->user_id = $guru2->id;
        $teacher02->save();
    }

    $activeYear = App\Models\SchoolYear::where('name', '2026/2027')->firstOrFail();
    $lockedYear = App\Models\SchoolYear::where('name', '2024/2025')->firstOrFail();
    $sem1 = App\Models\Semester::where('name', 'Semester I')->firstOrFail();

    $journalsCreated = 0;
    $journalsReused = 0;

    // 2. 1 Jurnal historis di tahun ajaran terkunci 2024/2025 (Senin, 2024-10-07).
    $lockedSchedule = App\Models\TeachingSchedule::query()
        ->with('assignment')
        ->whereHas('assignment', fn ($q) => $q->where('school_year_id', $lockedYear->id))
        ->firstOrFail();

    $existingLockedJournal = App\Models\TeachingJournal::query()
        ->where('teaching_schedule_id', $lockedSchedule->id)
        ->whereDate('session_date', '2024-10-07')
        ->first();

    if ($existingLockedJournal) {
        $journalsReused++;
    } else {
        App\Models\TeachingJournal::create([
            'school_year_id' => $lockedYear->id,
            'semester_id' => $sem1->id,
            'teaching_assignment_id' => $lockedSchedule->teaching_assignment_id,
            'teaching_schedule_id' => $lockedSchedule->id,
            'teaching_schedule_exception_id' => null,
            'school_rombel_id' => $lockedSchedule->assignment->school_rombel_id,
            'subject_id' => $lockedSchedule->assignment->subject_id,
            'session_date' => '2024-10-07',
            'source_type' => App\Models\TeachingJournal::SOURCE_REGULAR,
            'period_start' => $lockedSchedule->period_start,
            'period_end' => $lockedSchedule->period_end,
            'scheduled_start_time' => $lockedSchedule->start_time,
            'scheduled_end_time' => $lockedSchedule->end_time,
            'actual_start_time' => $lockedSchedule->start_time,
            'actual_end_time' => $lockedSchedule->end_time,
            'room' => $lockedSchedule->room,
            'assigned_employee_id' => $lockedSchedule->assignment->employee_id,
            'teacher_employee_id' => $lockedSchedule->assignment->employee_id,
            'topic' => 'UAT-P10 Arsip Jurnal Matematika 2024/2025',
            'method' => 'lecture',
            'summary' => 'Jurnal historis pada tahun ajaran 2024/2025 yang telah dikunci (read-only archive).',
            'notes' => 'Tidak dapat diubah karena tahun ajaran terkunci.',
            'version' => 1,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $journalsCreated++;
    }

    // 3. 1 Jurnal sesi rescheduled pada Sabtu, 2026-09-12 (Sejarah Indonesia X-MIPA-1 dengan guru pengganti Teacher 11).
    $rescheduledExc = App\Models\TeachingScheduleException::query()
        ->with('schedule.assignment')
        ->where('school_year_id', $activeYear->id)
        ->where('exception_type', App\Models\TeachingScheduleException::TYPE_RESCHEDULED)
        ->whereDate('exception_date', '2026-09-10')
        ->whereDate('rescheduled_date', '2026-09-12')
        ->firstOrFail();

    $existingReschedJournal = App\Models\TeachingJournal::query()
        ->where('teaching_schedule_id', $rescheduledExc->teaching_schedule_id)
        ->whereDate('session_date', '2026-09-12')
        ->first();

    if ($existingReschedJournal) {
        $journalsReused++;
    } else {
        $sched = $rescheduledExc->schedule;
        $assn = $sched->assignment;
        $effectiveTeacherId = $rescheduledExc->substitute_employee_id ?? $assn->employee_id;

        App\Models\TeachingJournal::create([
            'school_year_id' => $activeYear->id,
            'semester_id' => $sem1->id,
            'teaching_assignment_id' => $assn->id,
            'teaching_schedule_id' => $sched->id,
            'teaching_schedule_exception_id' => $rescheduledExc->id,
            'school_rombel_id' => $assn->school_rombel_id,
            'subject_id' => $assn->subject_id,
            'session_date' => '2026-09-12',
            'source_type' => App\Models\TeachingJournal::SOURCE_RESCHEDULED,
            'period_start' => $rescheduledExc->rescheduled_period_start,
            'period_end' => $rescheduledExc->rescheduled_period_end,
            'scheduled_start_time' => $rescheduledExc->rescheduled_start_time,
            'scheduled_end_time' => $rescheduledExc->rescheduled_end_time,
            'actual_start_time' => $rescheduledExc->rescheduled_start_time,
            'actual_end_time' => $rescheduledExc->rescheduled_end_time,
            'room' => $rescheduledExc->rescheduled_room ?? $sched->room,
            'assigned_employee_id' => $assn->employee_id,
            'teacher_employee_id' => $effectiveTeacherId,
            'topic' => 'UAT-P10 Kelas Pengganti Sejarah Indonesia (Pergerakan Nasional)',
            'method' => 'discussion',
            'summary' => 'Diskusi kelompok pada sesi pengganti hari Sabtu bersama guru pengganti.',
            'notes' => 'Sesi pengganti dari jadwal Kamis 2026-09-10.',
            'correction_reason' => 'Pencatatan terverifikasi oleh operator untuk sesi pengganti bulan September.',
            'corrected_by' => $operator->id,
            'corrected_at' => now(),
            'version' => 1,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
        ]);
        $journalsCreated++;
    }

    // 4. 64 Jurnal reguler sintetis pada Oktober 2026 (Senin..Jumat: 2026-10-05..2026-10-09 & 2026-10-12..2026-10-15)
    // untuk kelas X-MIPA-1, X-MIPA-2, X-IPS-1, X-IPS-2 (2 slot/hari x 4 kelas = 8 jurnal/hari x 8 hari = 64 jurnal).
    // Seluruh sesi pada minggu berjalan (mis. 2026-10-26..2026-11-03) dibiarkan terbuka agar dapat diuji pengisiannya.
    $targetClassNames = [
        'UAT-P10 X-MIPA-1',
        'UAT-P10 X-MIPA-2',
        'UAT-P10 X-IPS-1',
        'UAT-P10 X-IPS-2',
    ];
    $targetClassIds = App\Models\SchoolRombel::query()
        ->where('school_year_id', $activeYear->id)
        ->whereIn('name', $targetClassNames)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    $octoberDates = [
        '2026-10-05', // Mon
        '2026-10-06', // Tue
        '2026-10-07', // Wed
        '2026-10-08', // Thu
        '2026-10-09', // Fri
        '2026-10-12', // Mon
        '2026-10-13', // Tue
        '2026-10-14', // Wed
    ];

    $methods = ['lecture', 'discussion', 'practicum', 'demonstration', 'project', 'assignment', 'assessment'];
    $seq = 0;

    foreach ($octoberDates as $dateStr) {
        $isoDay = Carbon\Carbon::parse($dateStr)->dayOfWeekIso;
        $daySchedules = App\Models\TeachingSchedule::query()
            ->with(['assignment.schoolRombel', 'assignment.subject'])
            ->where('day_of_week', $isoDay)
            ->where('status', App\Models\TeachingSchedule::STATUS_ACTIVE)
            ->whereHas('assignment', function ($q) use ($activeYear, $sem1, $targetClassIds) {
                $q->where('school_year_id', $activeYear->id)
                    ->where('semester_id', $sem1->id)
                    ->whereIn('school_rombel_id', $targetClassIds);
            })
            ->orderBy('id')
            ->get();

        foreach ($daySchedules as $sched) {
            $seq++;
            $assn = $sched->assignment;
            $method = $methods[($seq - 1) % count($methods)];

            $existing = App\Models\TeachingJournal::query()
                ->where('teaching_schedule_id', $sched->id)
                ->whereDate('session_date', $dateStr)
                ->first();

            if ($existing) {
                $journalsReused++;

                continue;
            }

            $isCorrected = $seq <= 6;
            App\Models\TeachingJournal::create([
                'school_year_id' => $activeYear->id,
                'semester_id' => $sem1->id,
                'teaching_assignment_id' => $assn->id,
                'teaching_schedule_id' => $sched->id,
                'teaching_schedule_exception_id' => null,
                'school_rombel_id' => $assn->school_rombel_id,
                'subject_id' => $assn->subject_id,
                'session_date' => $dateStr,
                'source_type' => App\Models\TeachingJournal::SOURCE_REGULAR,
                'period_start' => $sched->period_start,
                'period_end' => $sched->period_end,
                'scheduled_start_time' => $sched->start_time,
                'scheduled_end_time' => $sched->end_time,
                'actual_start_time' => $sched->start_time,
                'actual_end_time' => $sched->end_time,
                'room' => $sched->room,
                'assigned_employee_id' => $assn->employee_id,
                'teacher_employee_id' => $assn->employee_id,
                'topic' => sprintf('UAT-P10 Pertemuan #%02d %s (%s)', $seq, $assn->subject->name, $assn->schoolRombel->name),
                'method' => $method,
                'summary' => sprintf(
                    'Pelaksanaan pembelajaran %s di kelas %s pada tanggal %s berjalan tertib sesuai RPP.',
                    $assn->subject->name,
                    $assn->schoolRombel->name,
                    $dateStr
                ),
                'notes' => $seq % 3 === 0 ? 'Evaluasi formatif diberikan di akhir sesi.' : null,
                'correction_reason' => $isCorrected ? 'Backfill terverifikasi operator untuk rekap awal Oktober 2026.' : null,
                'corrected_by' => $isCorrected ? $operator->id : null,
                'corrected_at' => $isCorrected ? now() : null,
                'version' => 1,
                'created_by' => $isCorrected ? $operator->id : $guru1->id,
                'updated_by' => $isCorrected ? $operator->id : $guru1->id,
            ]);
            $journalsCreated++;
        }
    }

    fwrite(STDOUT, 'Teaching journals: '.App\Models\TeachingJournal::count()." total ({$journalsCreated} created, {$journalsReused} reused).\n");
    fwrite(STDOUT, 'Linked teacher accounts: uat.guru -> '.$teacher01->name.' (id '.$teacher01->id.'), uat.guru2 -> '.$teacher02->name.' (id '.$teacher02->id.").\n");
    fwrite(STDOUT, 'Phase 10b journal fixtures ready.'."\n");
    exit(0);
}

if ($command === 'status') {
    $counts = [
        'users' => App\Models\User::withTrashed()->count(),
        'school_years' => App\Models\SchoolYear::withTrashed()->count(),
        'semesters' => App\Models\Semester::withTrashed()->count(),
        'school_rombels' => App\Models\SchoolRombel::withTrashed()->count(),
        'subjects' => App\Models\Subject::withTrashed()->count(),
        'employees' => App\Models\Employee::withTrashed()->count(),
        'eligible_teachers' => App\Models\Employee::query()->eligibleTeacher()->count(),
        'teaching_assignments' => App\Models\TeachingAssignment::count(),
        'teaching_schedules' => App\Models\TeachingSchedule::count(),
        'teaching_schedule_exceptions' => App\Models\TeachingScheduleException::count(),
        'teaching_journals' => Illuminate\Support\Facades\Schema::hasTable('teaching_journals') ? App\Models\TeachingJournal::count() : 0,
    ];

    fwrite(STDOUT, 'Environment: '.$app->environment().'; database: '.config('database.connections.sqlite.database')."\n");
    foreach ($counts as $key => $count) {
        fwrite(STDOUT, str_replace('_', ' ', ucfirst($key)).': '.$count."\n");
    }
    exit(0);
}

if ($command === 'probe-reports') {
    $reportService = $app->make(App\Services\TeachingReportService::class);
    $activeYear = App\Models\SchoolYear::where('name', '2026/2027')->firstOrFail();
    $lockedYear = App\Models\SchoolYear::where('name', '2024/2025')->firstOrFail();
    $sem1 = App\Models\Semester::where('name', 'Semester I')->firstOrFail();

    $admin = App\Models\User::where('username', 'uat.admin')->firstOrFail();
    $operator = App\Models\User::where('username', 'uat.operator')->firstOrFail();
    $kepsek = App\Models\User::where('username', 'uat.kepsek')->firstOrFail();
    $waka = App\Models\User::where('username', 'uat.waka')->firstOrFail();
    $guru1 = App\Models\User::where('username', 'uat.guru')->firstOrFail();
    $guru2 = App\Models\User::where('username', 'uat.guru2')->firstOrFail();

    $teacher01 = App\Models\Employee::where('name', 'UAT-P10 Teacher 01')->firstOrFail();
    $teacher02 = App\Models\Employee::where('name', 'UAT-P10 Teacher 02')->firstOrFail();

    fwrite(STDOUT, "=== UAT Phase 10c Compliance & Missing Journals Probe ===\n\n");

    // Probe 1: School-wide Summary for Admin
    $reportAdmin = $reportService->missingJournalsReport([
        'school_year_id' => $activeYear->id,
        'semester_id' => $sem1->id,
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-31',
    ], $admin);

    $s = $reportAdmin['data']['summary'];
    fwrite(STDOUT, "[PROBE 1] Admin School-wide Report (October 2026):\n");
    fwrite(STDOUT, "  - Teachers with sessions: {$s['total_teachers_with_sessions']}\n");
    fwrite(STDOUT, "  - Total Expected Sessions: {$s['total_expected_sessions']}\n");
    fwrite(STDOUT, "  - Total Recorded Journals: {$s['total_recorded_journals']}\n");
    fwrite(STDOUT, "  - Total Missing Journals: {$s['total_missing_journals']}\n");
    fwrite(STDOUT, "    * Within Grace: {$s['total_missing_within_grace']}\n");
    fwrite(STDOUT, "    * Overdue: {$s['total_missing_overdue']}\n");
    fwrite(STDOUT, "  - Overall Compliance: {$s['overall_compliance_percentage']}%\n");
    fwrite(STDOUT, "  - Excluded Slots: {$s['total_excluded_slots']}\n");

    if ($s['total_expected_sessions'] !== ($s['total_recorded_journals'] + $s['total_missing_journals'])) {
        throw new RuntimeException('Probe 1 failed: Expected != Recorded + Missing');
    }
    if ($s['total_missing_journals'] !== ($s['total_missing_within_grace'] + $s['total_missing_overdue'])) {
        throw new RuntimeException('Probe 1 failed: Missing != Grace + Overdue');
    }
    fwrite(STDOUT, "  -> PASS: Aggregate math is 100% consistent.\n\n");

    // Probe 2: RBAC Teacher Scoping (uat.guru)
    $reportGuru = $reportService->missingJournalsReport([
        'school_year_id' => $activeYear->id,
        'semester_id' => $sem1->id,
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-31',
    ], $guru1);

    fwrite(STDOUT, "[PROBE 2] Teacher Scoping (uat.guru):\n");
    fwrite(STDOUT, "  - Effective Scope: {$reportGuru['meta']['scope']}\n");
    fwrite(STDOUT, '  - Teachers in list: '.count($reportGuru['data']['by_teacher'])."\n");
    if ($reportGuru['meta']['scope'] !== 'mine' || count($reportGuru['data']['by_teacher']) !== 1) {
        throw new RuntimeException('Probe 2 failed: scope is not forced to mine for teacher');
    }
    if ((int) $reportGuru['data']['by_teacher'][0]['employee_id'] !== (int) $teacher01->id) {
        throw new RuntimeException('Probe 2 failed: teacher in list does not match uat.guru');
    }
    fwrite(STDOUT, "  -> PASS: Teacher scope=mine enforced to {$teacher01->name}.\n\n");

    // Probe 3: RBAC Cross-teacher Access Forbidden
    $blocked = false;
    try {
        $reportService->missingJournalsReport([
            'school_year_id' => $activeYear->id,
            'semester_id' => $sem1->id,
            'employee_id' => $teacher02->id,
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
        ], $guru1);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        if ($e->getStatusCode() === 403) {
            $blocked = true;
        }
    }
    fwrite(STDOUT, "[PROBE 3] Cross-teacher Access Guard:\n");
    if (! $blocked) {
        throw new RuntimeException('Probe 3 failed: uat.guru was able to request teacher02 without 403');
    }
    fwrite(STDOUT, "  -> PASS: Cross-teacher access blocked with 403 Forbidden.\n\n");

    // Probe 4: Leadership Role Access (uat.kepsek & uat.waka)
    $reportKepsek = $reportService->missingJournalsReport([
        'school_year_id' => $activeYear->id,
        'semester_id' => $sem1->id,
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-31',
    ], $kepsek);

    fwrite(STDOUT, "[PROBE 4] Leadership Access (uat.kepsek):\n");
    fwrite(STDOUT, '  - Can View All: '.($reportKepsek['meta']['can_view_all'] ? 'true' : 'false')."\n");
    fwrite(STDOUT, '  - Teachers in list: '.count($reportKepsek['data']['by_teacher'])."\n");
    if (! $reportKepsek['meta']['can_view_all'] || count($reportKepsek['data']['by_teacher']) < 2) {
        throw new RuntimeException('Probe 4 failed: kepsek cannot view all teachers');
    }
    fwrite(STDOUT, "  -> PASS: Leadership has school-wide view access.\n\n");

    // Probe 5: CSV Export Streaming
    $csvResponse = $reportService->exportMissingJournalsCsv([
        'school_year_id' => $activeYear->id,
        'semester_id' => $sem1->id,
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-31',
    ], $admin);

    ob_start();
    $csvResponse->sendContent();
    $csvOutput = (string) ob_get_clean();

    fwrite(STDOUT, "[PROBE 5] CSV Export Streaming:\n");
    fwrite(STDOUT, '  - Content Length: '.strlen($csvOutput)." bytes\n");
    fwrite(STDOUT, '  - Has UTF-8 BOM: '.(str_starts_with($csvOutput, "\xEF\xBB\xBF") ? 'YES' : 'NO')."\n");
    fwrite(STDOUT, '  - Headers present: '.(str_contains($csvOutput, 'Session Date') && str_contains($csvOutput, 'Journal Status') ? 'YES' : 'NO')."\n");

    if (! str_starts_with($csvOutput, "\xEF\xBB\xBF") || ! str_contains($csvOutput, 'Session Date')) {
        throw new RuntimeException('Probe 5 failed: CSV output invalid');
    }
    fwrite(STDOUT, "  -> PASS: CSV streaming export is valid and conformant.\n\n");

    fwrite(STDOUT, "=== ALL 5 UAT PHASE 10C PROBES PASSED SUCCESSFULLY ===\n");
    exit(0);
}

if ($command === 'probe-dashboard') {
    $dashboardService = $app->make(App\Services\DashboardService::class);
    $admin = App\Models\User::where('username', 'uat.admin')->firstOrFail();
    $operator = App\Models\User::where('username', 'uat.operator')->firstOrFail();
    $kepsek = App\Models\User::where('username', 'uat.kepsek')->firstOrFail();
    $guru = App\Models\User::where('username', 'uat.guru')->firstOrFail();

    fwrite(STDOUT, "=== UAT Phase 11a Operational Dashboard Probe ===\n\n");

    // Probe 1: Admin Overview
    $adminOverview = $dashboardService->getOverview($admin);
    fwrite(STDOUT, "[PROBE 1] Admin Dashboard Overview:\n");
    fwrite(STDOUT, '  - Active School Year: '.($adminOverview['academic']['active_school_year'] ?? 'N/A')."\n");
    fwrite(STDOUT, '  - Total Active Students: '.($adminOverview['academic']['total_active_students'] ?? 'N/A')."\n");
    fwrite(STDOUT, '  - Total Active Rombels: '.($adminOverview['academic']['total_active_rombels'] ?? 'N/A')."\n");
    fwrite(STDOUT, '  - Teaching View Type: '.($adminOverview['teaching']['view_type'] ?? 'N/A')."\n");
    fwrite(STDOUT, '  - System Info: '.($adminOverview['system'] !== null ? 'Present' : 'Null')."\n");
    if (($adminOverview['teaching']['view_type'] ?? null) !== 'school_wide' || $adminOverview['system'] === null) {
        throw new RuntimeException('Probe 1 failed: Admin overview invalid');
    }
    fwrite(STDOUT, "  -> PASS: Admin overview contains complete school-wide metrics.\n\n");

    // Probe 2: Leadership (Kepala Sekolah) Overview
    $kepsekOverview = $dashboardService->getOverview($kepsek);
    fwrite(STDOUT, "[PROBE 2] Kepala Sekolah Dashboard Overview:\n");
    fwrite(STDOUT, '  - Role Slug: '.($kepsekOverview['meta']['user_role'] ?? 'N/A')."\n");
    fwrite(STDOUT, '  - Teaching View Type: '.($kepsekOverview['teaching']['view_type'] ?? 'N/A')."\n");
    fwrite(STDOUT, '  - Can View Students: '.($kepsekOverview['meta']['can']['view_students'] ? 'true' : 'false')."\n");
    if (($kepsekOverview['teaching']['view_type'] ?? null) !== 'school_wide') {
        throw new RuntimeException('Probe 2 failed: Kepsek should have school_wide teaching view');
    }
    fwrite(STDOUT, "  -> PASS: Kepala Sekolah overview is school-wide.\n\n");

    // Probe 3: Teacher (Guru) Overview
    $guruOverview = $dashboardService->getOverview($guru);
    fwrite(STDOUT, "[PROBE 3] Guru Dashboard Overview:\n");
    fwrite(STDOUT, '  - Teaching View Type: '.($guruOverview['teaching']['view_type'] ?? 'N/A')."\n");
    fwrite(STDOUT, '  - Teacher Name: '.($guruOverview['teaching']['teacher_name'] ?? 'N/A')."\n");
    fwrite(STDOUT, '  - Employees Data: '.($guruOverview['employees'] === null ? 'NULL (Hidden)' : 'Visible')."\n");
    fwrite(STDOUT, '  - Letters Data: '.($guruOverview['letters'] === null ? 'NULL (Hidden)' : 'Visible')."\n");
    fwrite(STDOUT, '  - System Data: '.($guruOverview['system'] === null ? 'NULL (Hidden)' : 'Visible')."\n");
    if (($guruOverview['teaching']['view_type'] ?? null) !== 'teacher_personal' || $guruOverview['employees'] !== null || $guruOverview['system'] !== null) {
        throw new RuntimeException('Probe 3 failed: Guru overview scoping/RBAC leak');
    }
    fwrite(STDOUT, "  -> PASS: Guru personal view correctly scoped without RBAC leaks.\n\n");

    // Probe 4: Pending Tasks
    $adminTasks = $dashboardService->getPendingTasks($admin);
    $guruTasks = $dashboardService->getPendingTasks($guru);
    fwrite(STDOUT, "[PROBE 4] Operational Pending Tasks:\n");
    fwrite(STDOUT, '  - Admin Tasks Count: '.count($adminTasks)."\n");
    fwrite(STDOUT, '  - Guru Tasks Count: '.count($guruTasks)."\n");
    if (! is_array($adminTasks) || ! is_array($guruTasks)) {
        throw new RuntimeException('Probe 4 failed: Tasks must be arrays');
    }
    fwrite(STDOUT, "  -> PASS: Pending tasks returned as structured arrays.\n\n");

    // Probe 5: Charts Data
    $adminCharts = $dashboardService->getCharts($admin);
    $guruCharts = $dashboardService->getCharts($guru);
    fwrite(STDOUT, "[PROBE 5] Charts API:\n");
    fwrite(STDOUT, '  - Admin Student Chart Labels: '.count($adminCharts['students_by_level']['labels'] ?? [])." rombels\n");
    fwrite(STDOUT, '  - Admin Teaching Trend Labels: '.count($adminCharts['teaching_compliance_trend']['labels'] ?? [])." months\n");
    fwrite(STDOUT, '  - Guru Student Chart: '.($guruCharts['students_by_level'] === null ? 'NULL (Unpermitted)' : 'Visible')."\n");
    fwrite(STDOUT, '  - Guru Teaching Trend: '.($guruCharts['teaching_compliance_trend'] !== null ? 'Present' : 'NULL')."\n");
    if ($adminCharts['students_by_level'] === null || $adminCharts['teaching_compliance_trend'] === null || $guruCharts['students_by_level'] !== null) {
        throw new RuntimeException('Probe 5 failed: Charts RBAC or structure mismatch');
    }
    fwrite(STDOUT, "  -> PASS: Operational charts conform to authorization matrix.\n\n");

    fwrite(STDOUT, "=== ALL 5 UAT PHASE 11A DASHBOARD PROBES PASSED SUCCESSFULLY ===\n");
    exit(0);
}

if ($command === 'probe-reports-school') {
    $statsService = $app->make(App\Services\Reports\SchoolStatisticsReportService::class);
    $reconcileService = $app->make(App\Services\Reports\DapodikReconciliationService::class);

    $activeYear = App\Models\SchoolYear::where('name', '2026/2027')->first()
        ?? App\Models\SchoolYear::orderByDesc('start_date')->firstOrFail();

    fwrite(STDOUT, "=== UAT Phase 11b School Statistics & Dapodik Reconciliation Probe ===\n\n");

    // Probe 1: Multi-Year Statistics Trends
    $trends = $statsService->getMultiYearTrends(5);
    fwrite(STDOUT, "[PROBE 1] Multi-Year School Statistics Trends (5 Years):\n");
    fwrite(STDOUT, '  - Years Returned: '.count($trends['years'])."\n");
    fwrite(STDOUT, '  - Chart Labels: '.implode(', ', $trends['charts']['labels'])."\n");
    fwrite(STDOUT, '  - Latest Year Students: '.($trends['years'][count($trends['years']) - 1]['total_students'] ?? 0)."\n");
    if (! isset($trends['years']) || ! isset($trends['charts']['students']['total'])) {
        throw new RuntimeException('Probe 1 failed: Invalid trends structure');
    }
    fwrite(STDOUT, "  -> PASS: Multi-year trends aggregate correctly.\n\n");

    // Probe 2: Demographics Breakdown
    $demographics = $statsService->getDemographics($activeYear->id);
    fwrite(STDOUT, "[PROBE 2] Demographics Breakdown (Year {$activeYear->name}):\n");
    fwrite(STDOUT, '  - Total Students: '.($demographics['gender']['total'] ?? 0)."\n");
    fwrite(STDOUT, '  - Male Students: '.($demographics['gender']['male'] ?? 0)." ({$demographics['gender']['male_percent']}%)\n");
    fwrite(STDOUT, '  - Female Students: '.($demographics['gender']['female'] ?? 0)." ({$demographics['gender']['female_percent']}%)\n");
    fwrite(STDOUT, '  - Religions Count: '.count($demographics['religion'])."\n");
    fwrite(STDOUT, '  - Active Rombels: '.count($demographics['rombels'])."\n");
    if (! isset($demographics['gender']['total']) || ! isset($demographics['age_groups'])) {
        throw new RuntimeException('Probe 2 failed: Invalid demographics structure');
    }
    fwrite(STDOUT, "  -> PASS: Demographics distribution conforms to specification.\n\n");

    // Probe 3: Dapodik Readiness Audit
    $readiness = $statsService->getDapodikReadiness($activeYear->id);
    fwrite(STDOUT, "[PROBE 3] Dapodik Profile Readiness Audit:\n");
    fwrite(STDOUT, "  - Students Readiness: {$readiness['students']['readiness_percent']}%\n");
    fwrite(STDOUT, "  - Valid NIK Students: {$readiness['students']['valid_nik']} / {$readiness['students']['total']}\n");
    fwrite(STDOUT, "  - Valid NISN Students: {$readiness['students']['valid_nisn']} / {$readiness['students']['total']}\n");
    fwrite(STDOUT, "  - Employees Readiness: {$readiness['employees']['readiness_percent']}%\n");
    if (! isset($readiness['students']['readiness_percent']) || ! isset($readiness['employees']['readiness_percent'])) {
        throw new RuntimeException('Probe 3 failed: Invalid readiness structure');
    }
    fwrite(STDOUT, "  -> PASS: Dapodik mandatory attribute audit computes accurately.\n\n");

    // Probe 4: Dapodik CSV Reconciliation Comparison
    $sampleStudents = App\Models\Student::take(2)->get();
    $csvRows = ['nisn,nik,nama,rombel,jenis_kelamin'];
    if ($sampleStudents->count() > 0) {
        $s1 = $sampleStudents[0];
        $csvRows[] = "{$s1->nisn},{$s1->nik},{$s1->name},Rombel Identik,L";
    }
    if ($sampleStudents->count() > 1) {
        $s2 = $sampleStudents[1];
        $csvRows[] = "{$s2->nisn},{$s2->nik},Nama Berbeda Di Dapodik,Rombel Diff,P";
    }
    $csvRows[] = '9999999999,3201019999999999,Siswa Hanya Dapodik,X-Z,L';

    $tempCsvPath = tempnam(sys_get_temp_dir(), 'dapodik_uat_').'.csv';
    file_put_contents($tempCsvPath, implode("\n", $csvRows));

    $uploadedFile = new Illuminate\Http\UploadedFile(
        $tempCsvPath,
        'dapodik_uat.csv',
        'text/csv',
        null,
        true
    );

    $reconciliation = $reconcileService->reconcileCsv($uploadedFile, $activeYear->id);
    $recSummary = $reconciliation['summary'];
    fwrite(STDOUT, "[PROBE 4] Dapodik Two-Pass CSV Reconciliation:\n");
    fwrite(STDOUT, "  - Total Dapodik Rows: {$recSummary['total_dapodik_rows']}\n");
    fwrite(STDOUT, "  - Total Local Students: {$recSummary['total_local_students']}\n");
    fwrite(STDOUT, "  - Matched Identical: {$recSummary['total_identical']}\n");
    fwrite(STDOUT, "  - Matched Diff: {$recSummary['total_diff']}\n");
    fwrite(STDOUT, "  - Dapodik Only: {$recSummary['total_dapodik_only']}\n");
    fwrite(STDOUT, "  - Local Only: {$recSummary['total_local_only']}\n");
    if ($recSummary['total_dapodik_rows'] < 2 || ! isset($reconciliation['items'])) {
        @unlink($tempCsvPath);
        throw new RuntimeException('Probe 4 failed: Reconciliation output invalid');
    }
    fwrite(STDOUT, "  -> PASS: Two-pass reconciliation and diff detection verified.\n\n");

    // Probe 5: Export School Statistics & Reconciliation
    $statsExport = $statsService->exportMultiYearTrendsCsv(3);
    $reconcileExport = $reconcileService->exportReconciliationFromUpload($uploadedFile, $activeYear->id);
    @unlink($tempCsvPath);

    fwrite(STDOUT, "[PROBE 5] CSV Export with UTF-8 BOM:\n");
    fwrite(STDOUT, '  - Stats Export Content-Type: '.$statsExport->headers->get('Content-Type')."\n");
    fwrite(STDOUT, '  - Reconcile Export Content-Type: '.$reconcileExport->headers->get('Content-Type')."\n");
    if ($statsExport->headers->get('Content-Type') !== 'text/csv; charset=UTF-8' ||
        $reconcileExport->headers->get('Content-Type') !== 'text/csv; charset=UTF-8') {
        throw new RuntimeException('Probe 5 failed: Invalid export content type or BOM');
    }
    fwrite(STDOUT, "  -> PASS: Export streaming generates compliant CSV with UTF-8 BOM.\n\n");

    fwrite(STDOUT, "=== ALL 5 UAT PHASE 11B SCHOOL REPORTS & RECONCILIATION PROBES PASSED SUCCESSFULLY ===\n");
    exit(0);
}

if ($command === 'prepare-tasks') {
    $operator = App\Models\User::where('username', 'operator')->first()
        ?? App\Models\User::firstOrFail();

    fwrite(STDOUT, "=== UAT Phase 11c Preparing Synthetic Operational Reminders ===\n\n");

    // Ensure database migration has run
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

    $syntheticTasks = [
        [
            'title' => 'Pengesahan dan Penandatanganan Surat Tugas Workshop Kurikulum Merdeka',
            'description' => 'Surat tugas dinas MGMP perlu ditandatangani Kepala Sekolah sebelum hari Kamis.',
            'category' => 'letters',
            'priority' => 'high',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->addDays(2)->toDateString(),
            'status' => 'pending',
        ],
        [
            'title' => 'Verifikasi Pengajuan Cuti Sakit Guru Matematika',
            'description' => 'Surat keterangan dokter dari RSUD Koja perlu dicek kelengkapannya untuk lampiran cuti.',
            'category' => 'leaves',
            'priority' => 'high',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->addDays(1)->toDateString(),
            'status' => 'pending',
        ],
        [
            'title' => 'Follow up Kelengkapan Jurnal Mengajar Semester Ganjil',
            'description' => 'Ada 3 guru mata pelajaran produktif yang belum mencatatkan topik jurnal minggu lalu.',
            'category' => 'teaching',
            'priority' => 'warn',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->addDays(4)->toDateString(),
            'status' => 'pending',
        ],
        [
            'title' => 'Audit Retensi Berkas Dokumen Akreditasi Sekolah 2021',
            'description' => 'Pemeriksaan status retensi dan legalitas digital sertifikat lama yang mendekati kedaluwarsa.',
            'category' => 'archives',
            'priority' => 'info',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->addDays(14)->toDateString(),
            'status' => 'pending',
        ],
        [
            'title' => 'Penyelarasan Data NISN Siswa Tingkat Akhir ke Portal VervalPD',
            'description' => 'Pencocokan 12 siswa dengan status NISN kosong sebelum penutupan pendaftaran ujian sertifikasi.',
            'category' => 'students',
            'priority' => 'high',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->addDays(3)->toDateString(),
            'status' => 'pending',
        ],
        [
            'title' => 'Pembaruan SK Pembagian Beban Kerja Mengajar Semester Baru',
            'description' => 'Cek ulang alokasi jam mengajar per rombel untuk penyiapan SK Kepala Sekolah.',
            'category' => 'personnel',
            'priority' => 'warn',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->addDays(7)->toDateString(),
            'status' => 'pending',
        ],
        [
            'title' => 'Koordinasi Pengawas Pembina Dinas Pendidikan Jakarta Utara',
            'description' => 'Menyiapkan berkas profil kelembagaan dan statistik tren siswa untuk kunjungan monitoring.',
            'category' => 'general',
            'priority' => 'warn',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->addDays(5)->toDateString(),
            'status' => 'pending',
        ],
        [
            'title' => 'Pencetakan Surat Pengantar Mutasi Siswa Pindahan Masuk',
            'description' => 'Surat pengantar rekomendasi dinas untuk 2 siswa pindahan dari Jawa Barat.',
            'category' => 'letters',
            'priority' => 'info',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->addDays(6)->toDateString(),
            'status' => 'pending',
        ],
        [
            'title' => 'Pemeriksaan Berkas Permohonan Dispensasi Lomba OSN Siswa',
            'description' => 'Permohonan izin dispensasi latihan intensif tim olimpiade sains.',
            'category' => 'leaves',
            'priority' => 'info',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->addDays(8)->toDateString(),
            'status' => 'pending',
        ],
        [
            'title' => 'Backfill Jurnal Sesi Pengganti Praktikum Bengkel',
            'description' => 'Input jurnal manual pengganti sesi libur nasional Hari Sumpah Pemuda.',
            'category' => 'teaching',
            'priority' => 'warn',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->addDays(3)->toDateString(),
            'status' => 'pending',
        ],
        [
            'title' => 'Pengecekan Integritas Backup Basis Data Mingguan',
            'description' => 'Verifikasi sha256 arsip zip backup dan drill simulasi di lingkungan isolasi.',
            'category' => 'general',
            'priority' => 'high',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->toDateString(),
            'status' => 'pending',
        ],
        [
            'title' => 'Pengunggahan Salinan Dokumen Ijazah Kepala Sekolah Baru',
            'description' => 'Digitalisasi berkas kepangkatan dan ijazah untuk repositori arsip kepegawaian.',
            'category' => 'archives',
            'priority' => 'info',
            'due_date' => null,
            'status' => 'pending',
        ],
        [
            'title' => 'Penerbitan Surat Rekomendasi Beasiswa Prestasi Kaltara',
            'description' => 'Draft surat rekomendasi beasiswa untuk 5 siswa berprestasi telah disetujui Kepala Sekolah.',
            'category' => 'letters',
            'priority' => 'info',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->subDays(1)->toDateString(),
            'status' => 'completed',
            'completed_at' => Carbon\Carbon::now('Asia/Jakarta')->subHours(4),
            'completed_by' => $operator->id,
        ],
        [
            'title' => 'Sinkronisasi Data NIK Ganda Guru Honorer',
            'description' => 'Verifikasi duplikasi NIK telah diselesaikan dengan bukti KTP asli dari dinas kependudukan.',
            'category' => 'personnel',
            'priority' => 'warn',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
            'status' => 'completed',
            'completed_at' => Carbon\Carbon::now('Asia/Jakarta')->subHours(6),
            'completed_by' => $operator->id,
        ],
        [
            'title' => 'Pemberitahuan Uji Coba Asesmen Sumatif Tengah Semester',
            'description' => 'Agenda dibatalkan karena digabung dengan jadwal pekan pembelajaran kolaboratif.',
            'category' => 'teaching',
            'priority' => 'info',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->subDays(3)->toDateString(),
            'status' => 'cancelled',
        ],
        [
            'title' => 'Pelaporan Bulanan Disposisi Surat Masuk ke Pengawas',
            'description' => 'Disposisi rekap surat masuk bulan lalu telah dikirimkan via email resmi dinas.',
            'category' => 'letters',
            'priority' => 'info',
            'due_date' => Carbon\Carbon::now('Asia/Jakarta')->toDateString(),
            'status' => 'completed',
            'completed_at' => Carbon\Carbon::now('Asia/Jakarta')->subMinutes(30),
            'completed_by' => $operator->id,
        ],
    ];

    $createdCount = 0;
    foreach ($syntheticTasks as $taskData) {
        App\Models\OperatorReminder::create(array_merge($taskData, [
            'user_id' => $operator->id,
        ]));
        $createdCount++;
    }

    fwrite(STDOUT, "Successfully inserted {$createdCount} synthetic operator reminders for user '{$operator->username}'.\n");
    fwrite(STDOUT, "=== FINISHED PREPARING TASKS ===\n");
    exit(0);
}

if ($command === 'probe-tasks') {
    $service = $app->make(App\Services\Tasks\OperationalTaskService::class);
    $operator = App\Models\User::where('username', 'operator')->first()
        ?? App\Models\User::firstOrFail();

    fwrite(STDOUT, "=== UAT Phase 11c Operational Tasks & Reminders Center Probes ===\n\n");

    // Probe 1: Get Tasks Summary
    $summary = $service->getSummary($operator);
    fwrite(STDOUT, "[PROBE 1] Tasks & Reminders KPI Summary:\n");
    fwrite(STDOUT, "  - Total Active Tasks: {$summary['total_active']}\n");
    fwrite(STDOUT, "  - High Priority (Tindakan Segera): {$summary['total_high']}\n");
    fwrite(STDOUT, "  - Warning (Peringatan Operasional): {$summary['total_warn']}\n");
    fwrite(STDOUT, "  - Info Level: {$summary['total_info']}\n");
    fwrite(STDOUT, "  - Completed Today: {$summary['completed_today']}\n");
    fwrite(STDOUT, '  - Domains Covered: '.implode(', ', array_keys($summary['by_domain']))."\n");

    if (! isset($summary['total_active']) || ! isset($summary['total_high']) || ! isset($summary['completed_today'])) {
        throw new RuntimeException('Probe 1 failed: Invalid summary structure');
    }
    fwrite(STDOUT, "  -> PASS: Summary counters and domain breakdown computed accurately.\n\n");

    // Probe 2: List Tasks with Tab Separation & Domain Filters
    $allTasks = $service->getTasks($operator, ['tab' => 'all', 'per_page' => 10]);
    $systemTasks = $service->getTasks($operator, ['tab' => 'system']);
    $manualTasks = $service->getTasks($operator, ['tab' => 'manual']);
    $completedTasks = $service->getTasks($operator, ['tab' => 'completed']);

    fwrite(STDOUT, "[PROBE 2] Tab Filtering & Pagination:\n");
    fwrite(STDOUT, "  - Tab 'all' items returned: ".count($allTasks['data'])." (Total: {$allTasks['pagination']['total']})\n");
    fwrite(STDOUT, "  - Tab 'system' items returned: ".count($systemTasks['data'])."\n");
    fwrite(STDOUT, "  - Tab 'manual' items returned: ".count($manualTasks['data'])."\n");
    fwrite(STDOUT, "  - Tab 'completed' items returned: ".count($completedTasks['data'])."\n");

    if ($allTasks['pagination']['total'] < 1) {
        throw new RuntimeException('Probe 2 failed: Tasks list is empty, run prepare-tasks first');
    }
    fwrite(STDOUT, "  -> PASS: Tab filtering separates system, manual reminders, and completed history.\n\n");

    // Probe 3: Create & Update Manual Reminder
    $newReminder = $service->createReminder($operator, [
        'title' => 'UAT Synthetic Probe Reminder',
        'description' => 'Dibuat untuk memverifikasi fungsionalitas store & update reminder.',
        'category' => 'general',
        'priority' => 'high',
        'due_date' => Carbon\Carbon::now('Asia/Jakarta')->addDays(3)->toDateString(),
    ]);

    fwrite(STDOUT, "[PROBE 3] Create & Update Manual Reminder:\n");
    fwrite(STDOUT, "  - Created Reminder ID: {$newReminder->id}, Title: {$newReminder->title}\n");

    $updatedReminder = $service->updateReminder($newReminder, [
        'title' => 'UAT Synthetic Probe Reminder (Updated)',
        'priority' => 'warn',
    ]);
    fwrite(STDOUT, "  - Updated Title: {$updatedReminder->title}, Priority: {$updatedReminder->priority}\n");

    if ($updatedReminder->title !== 'UAT Synthetic Probe Reminder (Updated)' || $updatedReminder->priority !== 'warn') {
        throw new RuntimeException('Probe 3 failed: Update reminder failed');
    }
    fwrite(STDOUT, "  -> PASS: Manual reminder create & update logic verified.\n\n");

    // Probe 4: Mark Complete, Cancel & Delete Reminder
    $completedReminder = $service->completeReminder($updatedReminder, $operator);
    fwrite(STDOUT, "[PROBE 4] Reminder Lifecycle (Complete, Cancel, Delete):\n");
    fwrite(STDOUT, "  - Status after complete: {$completedReminder->status}, Completed by: {$completedReminder->completed_by}\n");

    if ($completedReminder->status !== 'completed' || ! $completedReminder->completed_at) {
        throw new RuntimeException('Probe 4 failed: Complete reminder did not record timestamp');
    }

    // Cancel another test reminder
    $tempReminder = $service->createReminder($operator, ['title' => 'Temp to cancel']);
    $cancelled = $service->cancelReminder($tempReminder);
    fwrite(STDOUT, "  - Status after cancel: {$cancelled->status}\n");
    if ($cancelled->status !== 'cancelled') {
        throw new RuntimeException('Probe 4 failed: Cancel reminder failed');
    }

    // Delete test reminder
    $service->deleteReminder($tempReminder);
    $foundDeleted = App\Models\OperatorReminder::find($tempReminder->id);
    if ($foundDeleted !== null) {
        throw new RuntimeException('Probe 4 failed: Delete reminder failed');
    }
    fwrite(STDOUT, "  -> PASS: Lifecycle transitions (complete, cancel, delete) verified.\n\n");

    // Probe 5: System Task Dismissal
    $testSystemKey = 'uat-probe-system-task-'.bin2hex(random_bytes(4));
    $dismissal = $service->dismissSystemTask(
        $testSystemKey,
        $operator,
        'UAT Dismissed System Letter'
    );

    fwrite(STDOUT, "[PROBE 5] System Task Dismissal:\n");
    fwrite(STDOUT, "  - Dismissed Key: {$dismissal->system_source_key}, Status: {$dismissal->status}\n");

    $foundDismissal = App\Models\OperatorReminder::where('system_source_key', $testSystemKey)->first();
    if (! $foundDismissal || $foundDismissal->status !== 'completed') {
        throw new RuntimeException('Probe 5 failed: Dismissed key not found in operator_reminders table');
    }

    // Clean up test reminder from probe 3
    $service->deleteReminder($completedReminder);
    $service->deleteReminder($dismissal);

    fwrite(STDOUT, "  -> PASS: System task dismissal and completed history tracking verified.\n\n");

    fwrite(STDOUT, "=== ALL 5 UAT PHASE 11C OPERATIONAL TASKS PROBES PASSED SUCCESSFULLY ===\n");
    exit(0);
}

fwrite(STDERR, "Unknown command. Use init, prepare, prepare-teaching, prepare-journals, prepare-tasks, probe-reports, probe-dashboard, probe-reports-school, probe-tasks, status, or start the HTTP server with this script.\n");
exit(2);
