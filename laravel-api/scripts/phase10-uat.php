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

fwrite(STDERR, "Unknown command. Use init, prepare, prepare-teaching, prepare-journals, status, or start the HTTP server with this script.\n");
exit(2);
