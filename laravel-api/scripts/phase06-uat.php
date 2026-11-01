<?php

declare(strict_types=1);

$base = str_replace('\\', '/', dirname(__DIR__));
$runtime = dirname($base).'/.uat-phase06';
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
    foreach (['', '/storage/app', '/storage/framework/cache/data', '/storage/framework/sessions', '/storage/framework/views', '/storage/logs'] as $directory) {
        if (! is_dir($runtime.$directory) && ! mkdir($runtime.$directory, 0700, true)) {
            throw new RuntimeException('Unable to create isolated runtime directories.');
        }
    }
    touch($runtime.'/database.sqlite');
    $key = 'base64:'.base64_encode(random_bytes(32));
    $password = 'uat-'.bin2hex(random_bytes(12));
    file_put_contents($runtime.'/account-password.txt', $password);
    $env = [
        'APP_NAME' => 'School UAT Phase 06', 'APP_ENV' => 'uat', 'APP_KEY' => $key, 'APP_DEBUG' => 'false',
        'APP_URL' => 'http://127.0.0.1:18024', 'FRONTEND_URL' => 'http://127.0.0.1:14024',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $runtime.'/database.sqlite', 'DB_FOREIGN_KEYS' => 'true',
        'CACHE_DRIVER' => 'file', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'school_phase06_session',
        'SESSION_DOMAIN' => '127.0.0.1', 'SANCTUM_STATEFUL_DOMAINS' => '127.0.0.1:14024',
        'FILESYSTEM_DISK' => 'local', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'single',
    ];
    file_put_contents($runtime.'/.env', implode("\n", array_map(fn ($key, $value) => $key.'="'.$value.'"', array_keys($env), $env))."\n");
    fwrite(STDOUT, "Isolated SQLite UAT (phase 06) initialized. Credentials remain in .uat-phase06/account-password.txt (ignored).\n");
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
    || config('database.connections.sqlite.url') || config('app.url') !== 'http://127.0.0.1:18024'
    || config('session.cookie') !== 'school_phase06_session') {
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

    $male = App\Models\Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $female = App\Models\Gender::firstOrCreate(['name' => 'Perempuan']);
    $religion = App\Models\Religion::firstOrCreate(['name' => 'Islam']);
    $maritalStatus = App\Models\MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);
    $province = App\Models\Province::firstOrCreate(['name' => 'Synthetic Province']);
    $regency = App\Models\Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Synthetic Regency']);
    $district = App\Models\District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Synthetic District']);
    $teacherType = App\Models\EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $staffType = App\Models\EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
    $teacherStatus = App\Models\EmployeeStatus::firstOrCreate(['name' => 'Guru Tetap Yayasan'], ['abbreviation' => 'GTY']);
    $staffStatus = App\Models\EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap Yayasan'], ['abbreviation' => 'PTY']);
    $teacherEmployment = App\Models\Employment::firstOrCreate(['name' => 'Guru Mata Pelajaran'], ['description' => 'Synthetic UAT reference']);
    $staffEmployment = App\Models\Employment::firstOrCreate(['name' => 'Tenaga Administrasi Sekolah'], ['description' => 'Synthetic UAT reference']);
    $subjects = collect(['Matematika', 'Bahasa Inggris', 'Ilmu Pengetahuan Alam (IPA)'])
        ->map(fn ($name) => App\Models\Subject::firstOrCreate(['name' => $name], ['description' => 'Synthetic UAT reference']));
    $statusActive = App\Models\StudentStatus::firstOrCreate(['name' => 'Aktif']);
    $entryNew = App\Models\StudentEntry::firstOrCreate(['name' => 'Siswa Baru']);
    App\Models\Semester::firstOrCreate(['name' => 'Ganjil'], ['name_other' => 'I']);
    App\Models\Semester::firstOrCreate(['name' => 'Genap'], ['name_other' => 'II']);
    $levels = collect(['X', 'XI', 'XII'])->mapWithKeys(fn ($name) => [$name => App\Models\SchoolLevel::firstOrCreate(['name' => $name])]);
    $majors = collect(['Ilmu Pengetahuan Alam (IPA)', 'Ilmu Pengetahuan Sosial (IPS)', 'Bahasa', 'Kurikulum Merdeka'])
        ->mapWithKeys(fn ($name) => [$name => App\Models\StudentMajor::firstOrCreate(['name' => $name])]);

    // D2: tanggal diisi operator/fixture, tidak diturunkan dari nama tahun.
    $sourceYear = App\Models\SchoolYear::firstOrNew(['name' => '2026/2027']);
    $sourceYear->fill(['start_date' => '2026-07-01', 'end_date' => '2027-06-30']);
    $sourceYear->deleted_at = null;
    $sourceYear->save();
    $targetYear = App\Models\SchoolYear::firstOrNew(['name' => '2027/2028']);
    $targetYear->fill(['start_date' => '2027-07-01', 'end_date' => '2028-06-30']);
    $targetYear->deleted_at = null;
    $targetYear->save();

    // D1: biarkan periode aktif kosong agar operator menetapkannya lewat UI.
    if (! App\Models\AcademicSetting::whereKey(1)->exists()) {
        Illuminate\Support\Facades\DB::table('academic_settings')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $employees = [];
    for ($i = 1; $i <= 10; $i++) {
        $number = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $employee = App\Models\Employee::withTrashed()->firstOrNew(['name' => 'UAT-P06 Teacher '.$number]);
        $employee->fill([
            'nik' => null,
            'user_nik' => null,
            'employee_type_id' => $teacherType->id,
            'employee_status_id' => $teacherStatus->id,
            'employment_id' => $teacherEmployment->id,
            'subject_id' => $subjects[($i - 1) % $subjects->count()]->id,
            'religion_id' => $religion->id,
            'district_id' => $district->id,
            'marital_status_id' => $maritalStatus->id,
            'gender_id' => ($i % 2 === 0 ? $female : $male)->id,
            'date_of_birth' => '1985-02-'.str_pad((string) (($i - 1) % 28 + 1), 2, '0', STR_PAD_LEFT),
            'place_of_birth' => 'Synthetic City',
            'address' => 'UAT-P06 Teacher Address '.$number,
            'email' => 'uat.p06.teacher'.$number.'@example.invalid',
            'tmt_employee' => '2020-01-01',
            'exit_date' => null,
        ]);
        $employee->deleted_at = null;
        $employee->save();
        $employees[$i] = $employee;
    }
    $employees[9]->user_id = App\Models\User::where('username', 'uat.waka')->value('id');
    $employees[9]->user_id_resolved_at = $employees[9]->user_id ? now() : null;
    $employees[9]->save();
    $employees[10]->user_id = App\Models\User::where('username', 'uat.kepsek')->value('id');
    $employees[10]->user_id_resolved_at = $employees[10]->user_id ? now() : null;
    $employees[10]->save();

    for ($i = 1; $i <= 4; $i++) {
        $number = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $employee = App\Models\Employee::withTrashed()->firstOrNew(['name' => 'UAT-P06 Staff '.$number]);
        $employee->fill([
            'nik' => null,
            'user_nik' => null,
            'employee_type_id' => $staffType->id,
            'employee_status_id' => $staffStatus->id,
            'employment_id' => $staffEmployment->id,
            'subject_id' => null,
            'religion_id' => $religion->id,
            'district_id' => $district->id,
            'marital_status_id' => $maritalStatus->id,
            'gender_id' => ($i % 2 === 0 ? $female : $male)->id,
            'date_of_birth' => '1988-03-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'place_of_birth' => 'Synthetic City',
            'address' => 'UAT-P06 Staff Address '.$number,
            'email' => 'uat.p06.staff'.$number.'@example.invalid',
            'tmt_employee' => '2021-01-01',
            'exit_date' => null,
        ]);
        $employee->deleted_at = null;
        $employee->save();
    }

    $separated = App\Models\Employee::withTrashed()->firstOrNew(['name' => 'UAT-P06 Teacher Separated']);
    $separated->fill([
        'nik' => null,
        'user_nik' => null,
        'employee_type_id' => $teacherType->id,
        'employee_status_id' => $teacherStatus->id,
        'employment_id' => $teacherEmployment->id,
        'subject_id' => $subjects[0]->id,
        'religion_id' => $religion->id,
        'district_id' => $district->id,
        'marital_status_id' => $maritalStatus->id,
        'gender_id' => $male->id,
        'date_of_birth' => '1980-04-01',
        'place_of_birth' => 'Synthetic City',
        'address' => 'UAT-P06 Separated Address',
        'email' => 'uat.p06.separated@example.invalid',
        'tmt_employee' => '2010-01-01',
        'exit_date' => '2020-12-31',
    ]);
    $separated->deleted_at = null;
    $separated->save();

    $operatorId = App\Models\User::where('username', 'uat.operator')->value('id');
    $legacyYear = App\Models\SchoolRombel::withTrashed()
        ->where('school_year_id', $sourceYear->id)
        ->where('name', 'UAT Rombel 1')
        ->first();
    if ($legacyYear && ! App\Models\SchoolRombel::withTrashed()
        ->where('school_year_id', $sourceYear->id)->where('name', 'X-1')->exists()) {
        $legacyYear->name = 'X-1';
        $legacyYear->save();
    }

    $classSpecs = [
        // Sumber: 6 kelas, satu memakai wali legacy (users) untuk kompatibilitas UI.
        ['year' => $sourceYear, 'name' => 'X-1', 'level' => 'X', 'major' => 'Ilmu Pengetahuan Alam (IPA)', 'teacher' => null, 'legacy' => $operatorId],
        ['year' => $sourceYear, 'name' => 'X-2', 'level' => 'X', 'major' => 'Ilmu Pengetahuan Sosial (IPS)', 'teacher' => 1, 'legacy' => null],
        ['year' => $sourceYear, 'name' => 'XI-1', 'level' => 'XI', 'major' => 'Bahasa', 'teacher' => 2, 'legacy' => null],
        ['year' => $sourceYear, 'name' => 'XI-2', 'level' => 'XI', 'major' => null, 'teacher' => null, 'legacy' => null],
        ['year' => $sourceYear, 'name' => 'XII-1', 'level' => 'XII', 'major' => 'Kurikulum Merdeka', 'teacher' => 3, 'legacy' => null],
        ['year' => $sourceYear, 'name' => 'XII-2', 'level' => 'XII', 'major' => 'Ilmu Pengetahuan Alam (IPA)', 'teacher' => 4, 'legacy' => null],
        // Tujuan: 6 kelas, X-2 sengaja kosong tanpa riwayat untuk uji hapus.
        ['year' => $targetYear, 'name' => 'XI-1', 'level' => 'XI', 'major' => 'Ilmu Pengetahuan Alam (IPA)', 'teacher' => 5, 'legacy' => null],
        ['year' => $targetYear, 'name' => 'XI-2', 'level' => 'XI', 'major' => 'Ilmu Pengetahuan Sosial (IPS)', 'teacher' => 6, 'legacy' => null],
        ['year' => $targetYear, 'name' => 'XII-1', 'level' => 'XII', 'major' => 'Bahasa', 'teacher' => 7, 'legacy' => null],
        ['year' => $targetYear, 'name' => 'XII-2', 'level' => 'XII', 'major' => null, 'teacher' => null, 'legacy' => null],
        ['year' => $targetYear, 'name' => 'X-1', 'level' => 'X', 'major' => 'Ilmu Pengetahuan Alam (IPA)', 'teacher' => 8, 'legacy' => null],
        ['year' => $targetYear, 'name' => 'X-2', 'level' => 'X', 'major' => 'Ilmu Pengetahuan Sosial (IPS)', 'teacher' => null, 'legacy' => null],
    ];

    $classes = [];
    foreach ($classSpecs as $spec) {
        $class = App\Models\SchoolRombel::withTrashed()->firstOrNew([
            'school_year_id' => $spec['year']->id,
            'name' => $spec['name'],
        ]);
        $class->fill([
            'school_level_id' => $levels[$spec['level']]->id,
            'student_major_id' => $spec['major'] !== null ? $majors[$spec['major']]->id : null,
            'class_teach_employee_id' => $spec['teacher'] !== null ? $employees[$spec['teacher']]->id : null,
            'class_teach_id' => $spec['legacy'],
        ]);
        $class->deleted_at = null;
        $class->save();
        $classes[] = $class;
    }

    for ($i = 1; $i <= 130; $i++) {
        $serial = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $student = App\Models\Student::firstOrCreate(
            ['nik' => '96'.str_pad((string) $i, 14, '0', STR_PAD_LEFT)],
            [
                'name' => 'UAT-P06 Student '.$serial,
                'birthplace' => 'Synthetic City',
                'birthdate' => '2011-05-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT),
                'gender_id' => ($i % 2 === 0 ? $female : $male)->id,
                'religion_id' => $religion->id,
                'nis' => 'P06-'.$serial,
                'nisn' => '96'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            ]
        );

        if ($i <= 70) {
            $class = $classes[($i - 1) % 6];
        } elseif ($i <= 100) {
            $class = $classes[6 + (($i - 71) % 5)];
        } else {
            continue;
        }

        App\Models\StudentRombel::firstOrCreate(
            ['student_id' => $student->id, 'school_rombel' => $class->id],
            [
                'student_nik' => $student->nik,
                'student_status_id' => $statusActive->id,
                'student_entry_id' => $entryNew->id,
            ]
        );
    }

    $markerStudents = App\Models\Student::where('name', 'like', 'UAT-P06%')->count();
    $classIds = array_map(fn ($class) => $class->id, $classes);
    $sourcePlaced = App\Models\StudentRombel::whereIn('school_rombel', array_slice($classIds, 0, 6))->count();
    $targetPlaced = App\Models\StudentRombel::whereIn('school_rombel', array_slice($classIds, 6))->count();
    $unplaced = App\Models\Student::where('name', 'like', 'UAT-P06%')
        ->whereDoesntHave('studentRombels')
        ->count();

    fwrite(STDOUT, 'UAT-P06 students: '.$markerStudents.' ('.$sourcePlaced.' placed source, '.$targetPlaced.' placed target, '.$unplaced.' unplaced).'."\n");
    fwrite(STDOUT, 'Classes: '.count($classes).' (6 source, 6 target), teachers: 10 active Pendidik + 1 separated, staff: 4.'."\n");
    fwrite(STDOUT, 'Academic period is intentionally empty; set it from School Years before reviewing.'."\n");
    exit(0);
}

if ($command === 'prepare-progression') {
    $sourceYear = App\Models\SchoolYear::where('name', '2026/2027')->first();
    $targetYear = App\Models\SchoolYear::where('name', '2027/2028')->first();
    foreach (['Aktif', 'Mutasi Keluar', 'Drop Out', 'Mengundurkan Diri', 'Meninggal Dunia', 'Lulus'] as $statusName) {
        App\Models\StudentStatus::firstOrCreate(['name' => $statusName]);
    }
    $statusActive = App\Models\StudentStatus::where('name', 'Aktif')->first();
    $statusGraduated = App\Models\StudentStatus::where('name', 'Lulus')->first();
    $entryNew = App\Models\StudentEntry::where('name', 'Siswa Baru')->first();
    if (! $sourceYear || ! $targetYear || ! $statusActive || ! $statusGraduated || ! $entryNew) {
        throw new RuntimeException('Run the prepare command first (years, statuses, entries, and classes are required).');
    }
    if ((int) $statusGraduated->id !== App\Services\Academic\StudentProgressionService::GRADUATED_STATUS_ID) {
        throw new RuntimeException('The Lulus status ID must be '.App\Services\Academic\StudentProgressionService::GRADUATED_STATUS_ID.' for progression graduate commits.');
    }

    $classFor = function (App\Models\SchoolYear $year, string $name): App\Models\SchoolRombel {
        $class = App\Models\SchoolRombel::where('school_year_id', $year->id)->where('name', $name)->first();
        if (! $class) {
            throw new RuntimeException('Missing class '.$name.' for year '.$year->name.'; run prepare first.');
        }

        return $class;
    };
    $sourceClasses = [
        'X-1' => $classFor($sourceYear, 'X-1'),
        'X-2' => $classFor($sourceYear, 'X-2'),
        'XI-1' => $classFor($sourceYear, 'XI-1'),
        'XI-2' => $classFor($sourceYear, 'XI-2'),
        'XII-1' => $classFor($sourceYear, 'XII-1'),
    ];
    $targetClasses = [
        'XI-1' => $classFor($targetYear, 'XI-1'),
        'XII-1' => $classFor($targetYear, 'XII-1'),
        'XII-2' => $classFor($targetYear, 'XII-2'),
    ];

    // 20 siswa sintetis UAT-P06B: naik satu jurusan, naik lintas jurusan,
    // tinggal kelas, lulus, serta dua siswa yang sudah punya keanggotaan di tahun tujuan.
    $specs = [
        ['source' => 'X-1', 'conflict' => false],   // 1-4: naik, jurusan sama (X-1 -> XI-1)
        ['source' => 'X-1', 'conflict' => false],
        ['source' => 'X-1', 'conflict' => false],
        ['source' => 'X-1', 'conflict' => false],
        ['source' => 'X-1', 'conflict' => true],    // 5-6: sudah punya keanggotaan aktif di XI-1 tujuan
        ['source' => 'X-1', 'conflict' => true],
        ['source' => 'X-2', 'conflict' => false],   // 7-10: naik lintas jurusan (IPS -> IPA)
        ['source' => 'X-2', 'conflict' => false],
        ['source' => 'X-2', 'conflict' => false],
        ['source' => 'X-2', 'conflict' => false],
        ['source' => 'XI-1', 'conflict' => false],  // 11-14: naik, jurusan sama (XI-1 -> XII-1)
        ['source' => 'XI-1', 'conflict' => false],
        ['source' => 'XI-1', 'conflict' => false],
        ['source' => 'XI-1', 'conflict' => false],
        ['source' => 'XII-1', 'conflict' => false], // 15-17: kandidat lulus (tanpa kelas tujuan)
        ['source' => 'XII-1', 'conflict' => false],
        ['source' => 'XII-1', 'conflict' => false],
        ['source' => 'XI-2', 'conflict' => false],  // 18-19: naik tanpa jurusan (XI-2 -> XII-2)
        ['source' => 'XI-2', 'conflict' => false],
        ['source' => 'X-1', 'conflict' => false],   // 20: kandidat tinggal kelas (tanpa kelas tujuan)
    ];

    $male = App\Models\Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $female = App\Models\Gender::firstOrCreate(['name' => 'Perempuan']);
    $religion = App\Models\Religion::firstOrCreate(['name' => 'Islam']);

    $createdStudents = 0;
    $reusedStudents = 0;
    $createdSourceMemberships = 0;
    $createdTargetMemberships = 0;

    foreach ($specs as $offset => $spec) {
        $i = $offset + 1;
        $serial = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $student = App\Models\Student::firstOrCreate(
            ['nik' => '97'.str_pad((string) $i, 14, '0', STR_PAD_LEFT)],
            [
                'name' => 'UAT-P06B Progression Student '.$serial,
                'birthplace' => 'Synthetic City',
                'birthdate' => '2010-08-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT),
                'gender_id' => ($i % 2 === 0 ? $female : $male)->id,
                'religion_id' => $religion->id,
                'nis' => 'P06B-'.$serial,
                'nisn' => '97'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            ]
        );
        $student->wasRecentlyCreated ? $createdStudents++ : $reusedStudents++;

        $sourceMembership = App\Models\StudentRombel::firstOrCreate(
            ['student_id' => $student->id, 'school_rombel' => $sourceClasses[$spec['source']]->id],
            [
                'student_nik' => $student->nik,
                'student_status_id' => $statusActive->id,
                'student_entry_id' => $entryNew->id,
            ]
        );
        if ($sourceMembership->wasRecentlyCreated) {
            $createdSourceMemberships++;
        }

        if ($spec['conflict']) {
            $conflictMembership = App\Models\StudentRombel::firstOrCreate(
                ['student_id' => $student->id, 'school_rombel' => $targetClasses['XI-1']->id],
                [
                    'student_nik' => $student->nik,
                    'student_status_id' => $statusActive->id,
                    'student_entry_id' => $entryNew->id,
                ]
            );
            if ($conflictMembership->wasRecentlyCreated) {
                $createdTargetMemberships++;
            }
        }
    }

    fwrite(STDOUT, 'UAT-P06B students: '.count($specs).' ('.$createdStudents.' created, '.$reusedStudents.' reused); source memberships +'.$createdSourceMemberships.', conflict target memberships +'.$createdTargetMemberships.". No data was reset.\n");
    fwrite(STDOUT, "Targets: 1-4 X-1->XI-1; 5-6 X-1 with existing XI-1 membership; 7-10 X-2->XI-1 (cross-major); 11-14 XI-1->XII-1; 15-17 XII-1 graduate; 18-19 XI-2->XII-2; 20 X-1 retain.\n");
    exit(0);
}

if ($command === 'prepare-mutations') {
    $exit = $kernel->call('migrate', ['--force' => true]);
    fwrite(STDOUT, $kernel->output());
    if ($exit !== 0) {
        exit($exit);
    }

    $sourceYear = App\Models\SchoolYear::where('name', '2026/2027')->first();
    $targetYear = App\Models\SchoolYear::where('name', '2027/2028')->first();
    $operatorId = App\Models\User::where('username', 'uat.operator')->value('id');
    $adminId = App\Models\User::where('username', 'uat.admin')->value('id');
    if (! $sourceYear || ! $targetYear || ! $operatorId || ! $adminId) {
        throw new RuntimeException('Run the prepare command first (years, classes, and UAT accounts are required).');
    }

    foreach (['Aktif', 'Mutasi Keluar', 'Drop Out', 'Mengundurkan Diri', 'Meninggal Dunia', 'Lulus'] as $statusName) {
        App\Models\StudentStatus::firstOrCreate(['name' => $statusName]);
    }
    $statusActive = App\Models\StudentStatus::where('name', 'Aktif')->first();
    $statusOut = App\Models\StudentStatus::where('name', 'Mutasi Keluar')->first();
    $statusGraduated = App\Models\StudentStatus::where('name', 'Lulus')->first();
    if (! $statusActive || ! $statusOut || ! $statusGraduated) {
        throw new RuntimeException('The baseline student statuses are incomplete.');
    }
    if ((int) $statusGraduated->id !== App\Services\Academic\StudentMutationService::STATUS_GRADUATED
        || (int) $statusActive->id !== App\Services\Academic\StudentMutationService::STATUS_ACTIVE
        || (int) $statusOut->id !== App\Services\Academic\StudentMutationService::STATUS_MUTATION_OUT) {
        throw new RuntimeException('The baseline student status IDs do not match the mutation service constants.');
    }

    $entryNew = App\Models\StudentEntry::where('name', 'Siswa Baru')->first();
    if (! $entryNew) {
        throw new RuntimeException('Run the prepare command first (the Siswa Baru entry is required).');
    }
    if (! App\Models\StudentEntry::whereKey(App\Services\Academic\StudentMutationService::ENTRY_MUTATION_IN)->exists()) {
        Illuminate\Support\Facades\DB::table('student_entries')->insert([
            'id' => App\Services\Academic\StudentMutationService::ENTRY_MUTATION_IN,
            'name' => 'Mutasi Masuk',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $province = App\Models\Province::firstOrCreate(['name' => 'Synthetic Province']);
    $regency = App\Models\Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Synthetic Regency']);
    $district = App\Models\District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Synthetic District']);
    $educationLevel = App\Models\EducationLevel::firstOrCreate(['name' => 'SMA']);

    // 20 sekolah sintetis dengan NPSN 8 digit unik (91xxxxxx).
    $schools = [];
    for ($i = 1; $i <= 20; $i++) {
        $serial = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $npsn = '91'.str_pad((string) $i, 6, '0', STR_PAD_LEFT);
        $school = App\Models\EducationSchool::withTrashed()->firstOrNew(['npsn' => $npsn]);
        if (! $school->exists) {
            $school->fill([
                'name' => 'UAT-P06C School '.$serial,
                'education_level_id' => $educationLevel->id,
                'status_education' => 'Negeri',
                'address' => 'Synthetic UAT school address '.$serial,
                'district_id' => $district->id,
            ]);
            $school->deleted_at = null;
            $school->save();
        }
        $schools[$i] = $school;
    }

    // Tahun locked non-aktif (2025/2026) untuk skenario koreksi periode terkunci.
    $lockedYear = App\Models\SchoolYear::firstOrNew(['name' => '2025/2026']);
    if (! $lockedYear->exists) {
        $lockedYear->fill(['start_date' => '2025-07-01', 'end_date' => '2026-06-30']);
    }
    $lockedYear->deleted_at = null;
    if ($lockedYear->locked_at === null) {
        $lockedYear->locked_at = now();
    }
    $lockedYear->save();

    $settings = App\Models\AcademicSetting::find(1);
    if ($settings && (int) $settings->active_school_year_id === (int) $lockedYear->id) {
        $settings->active_school_year_id = $sourceYear->id;
        $settings->save();
        fwrite(STDOUT, 'Active period moved back to 2026/2027 because the locked fixture year must not be active.'."\n");
    }

    $levels = App\Models\SchoolLevel::whereIn('name', ['X', 'XI', 'XII'])->get()->keyBy('name');
    foreach (['X', 'XI', 'XII'] as $levelName) {
        if (! $levels->has($levelName)) {
            throw new RuntimeException('Run the prepare command first (school levels are required).');
        }
    }

    $lockedClasses = [];
    foreach ([['UAT-P06C Locked X-1', 'X'], ['UAT-P06C Locked XI-1', 'XI'], ['UAT-P06C Locked XII-1', 'XII']] as [$name, $levelName]) {
        $class = App\Models\SchoolRombel::withTrashed()->firstOrNew(['school_year_id' => $lockedYear->id, 'name' => $name]);
        if (! $class->exists) {
            $class->fill(['school_level_id' => $levels[$levelName]->id]);
            $class->deleted_at = null;
            $class->save();
        }
        $lockedClasses[$name] = $class;
    }

    $classFor = function (App\Models\SchoolYear $year, string $name): App\Models\SchoolRombel {
        $class = App\Models\SchoolRombel::where('school_year_id', $year->id)->where('name', $name)->first();
        if (! $class) {
            throw new RuntimeException('Missing class '.$name.' for year '.$year->name.'; run prepare first.');
        }

        return $class;
    };
    $sourceX1 = $classFor($sourceYear, 'X-1');
    $sourceX2 = $classFor($sourceYear, 'X-2');
    $sourceXI1 = $classFor($sourceYear, 'XI-1');
    $sourceXI2 = $classFor($sourceYear, 'XI-2');
    $targetXI1 = $classFor($targetYear, 'XI-1');

    $male = App\Models\Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $female = App\Models\Gender::firstOrCreate(['name' => 'Perempuan']);
    $religion = App\Models\Religion::firstOrCreate(['name' => 'Islam']);

    $students = [];
    for ($i = 1; $i <= 120; $i++) {
        $serial = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $students[$i] = App\Models\Student::withTrashed()->firstOrCreate(
            ['nik' => '98'.str_pad((string) $i, 14, '0', STR_PAD_LEFT)],
            [
                'name' => 'UAT-P06C Student '.$serial,
                'birthplace' => 'Synthetic City',
                'birthdate' => '2010-09-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT),
                'gender_id' => ($i % 2 === 0 ? $female : $male)->id,
                'religion_id' => $religion->id,
                'nis' => 'P06C-'.$serial,
                'nisn' => '98'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            ]
        );
    }

    $place = function (int $index, App\Models\SchoolRombel $class, array $attributes) use (&$students, $entryNew, $statusActive): App\Models\StudentRombel {
        $student = $students[$index];
        $membership = App\Models\StudentRombel::withTrashed()->firstOrNew([
            'student_id' => $student->id,
            'school_rombel' => $class->id,
        ]);
        if (! $membership->exists) {
            $membership->fill(array_merge([
                'student_nik' => $student->nik,
                'student_status_id' => $statusActive->id,
                'student_entry_id' => $entryNew->id,
                'latest_student_entry_date' => '2026-07-01',
                'version' => 1,
            ], $attributes));
            $membership->deleted_at = null;
            $membership->save();
        }

        return $membership;
    };

    $raiseVersion = function (int $index, int $version) use (&$students): void {
        $student = $students[$index];
        if ((int) $student->version < $version) {
            $student->version = $version;
            $student->save();
        }
    };

    $log = function (int $index, App\Models\SchoolYear $year, string $type, App\Models\SchoolRombel $class, App\Models\StudentRombel $membership, string $date, string $npsn, string $reason, array $overrides = []) use (&$students, $operatorId): App\Models\StudentMutation {
        return App\Models\StudentMutation::firstOrCreate(
            [
                'student_id' => $students[$index]->id,
                'school_year_id' => $year->id,
                'type' => $type,
                'effective_date' => $date,
            ],
            array_merge([
                'student_rombel_id' => $membership->id,
                'school_rombel_id' => $class->id,
                'school_npsn' => $npsn,
                'reason' => $reason,
                'status' => App\Models\StudentMutation::STATUS_ACTIVE,
                'version' => 1,
                'created_by' => $operatorId,
            ], $overrides)
        );
    };

    $reasonIn = 'UAT-P06C synthetic mutation-in for a family relocation.';
    $reasonOut = 'UAT-P06C synthetic mutation-out to the destination school.';
    $cancelAttrs = [
        'status' => App\Models\StudentMutation::STATUS_CANCELLED,
        'version' => 2,
        'cancelled_at' => now(),
        'cancelled_by' => $adminId,
        'cancel_reason' => 'UAT-P06C synthetic cancellation for the review scenario.',
    ];

    // 0001-0020: mutasi masuk aktif di 2026/2027 (membership baru, entry Mutasi Masuk).
    for ($i = 1; $i <= 20; $i++) {
        $school = $schools[(($i - 1) % 20) + 1];
        $membership = $place($i, $sourceX1, [
            'student_entry_id' => App\Services\Academic\StudentMutationService::ENTRY_MUTATION_IN,
            'latest_student_entry_date' => '2026-07-15',
            'mutation_education_school_id' => $school->npsn,
        ]);
        $log($i, $sourceYear, 'in', $sourceX1, $membership, '2026-07-15', $school->npsn, $reasonIn);
        $raiseVersion($i, 2);
    }

    // 0021-0030: sudah keluar dari 2026/2027 (membership tertutup + log keluar aktif).
    for ($i = 21; $i <= 30; $i++) {
        $school = $schools[(($i - 1) % 20) + 1];
        $membership = $place($i, $sourceX2, [
            'student_status_id' => $statusOut->id,
            'latest_student_entry_date' => '2026-07-01',
            'latest_student_exit_date' => '2027-03-10',
            'latest_student_exit_school_id' => $school->npsn,
            'version' => 2,
        ]);
        $log($i, $sourceYear, 'out', $sourceX2, $membership, '2027-03-10', $school->npsn, $reasonOut);
        $raiseVersion($i, 2);
    }

    // 0031-0040: keluar lalu masuk kembali (dua membership, masuk/keluar/masuk).
    for ($i = 31; $i <= 40; $i++) {
        $origin = $schools[(($i - 1) % 20) + 1];
        $destination = $schools[(($i + 4) % 20) + 1];
        $first = $place($i, $sourceX1, [
            'student_status_id' => $statusOut->id,
            'student_entry_id' => App\Services\Academic\StudentMutationService::ENTRY_MUTATION_IN,
            'latest_student_entry_date' => '2026-07-20',
            'mutation_education_school_id' => $origin->npsn,
            'latest_student_exit_date' => '2027-01-10',
            'latest_student_exit_school_id' => $destination->npsn,
            'version' => 2,
        ]);
        $log($i, $sourceYear, 'in', $sourceX1, $first, '2026-07-20', $origin->npsn, $reasonIn);
        $log($i, $sourceYear, 'out', $sourceX1, $first, '2027-01-10', $destination->npsn, $reasonOut);

        $second = $place($i, $sourceX2, [
            'student_entry_id' => App\Services\Academic\StudentMutationService::ENTRY_MUTATION_IN,
            'latest_student_entry_date' => '2027-01-15',
            'mutation_education_school_id' => $destination->npsn,
        ]);
        $log($i, $sourceYear, 'in', $sourceX2, $second, '2027-01-15', $destination->npsn, $reasonIn);
        $raiseVersion($i, 4);
    }

    // 0041-0050: mutasi masuk dibatalkan (membership diarsipkan, log cancelled).
    for ($i = 41; $i <= 50; $i++) {
        $school = $schools[(($i - 1) % 20) + 1];
        $membership = $place($i, $sourceXI1, [
            'student_entry_id' => App\Services\Academic\StudentMutationService::ENTRY_MUTATION_IN,
            'latest_student_entry_date' => '2026-08-05',
            'mutation_education_school_id' => $school->npsn,
            'archived_at' => now(),
            'archive_reason' => App\Services\Academic\StudentMutationService::CANCEL_IN_ARCHIVE_REASON,
            'version' => 2,
        ]);
        $log($i, $sourceYear, 'in', $sourceXI1, $membership, '2026-08-05', $school->npsn, $reasonIn, $cancelAttrs);
        $raiseVersion($i, 3);
    }

    // 0051-0060: mutasi keluar dibatalkan (membership kembali aktif, log cancelled).
    for ($i = 51; $i <= 60; $i++) {
        $school = $schools[(($i - 1) % 20) + 1];
        $membership = $place($i, $sourceXI1, [
            'latest_student_entry_date' => '2026-07-01',
            'version' => 3,
        ]);
        $log($i, $sourceYear, 'out', $sourceXI1, $membership, '2027-02-20', $school->npsn, $reasonOut, $cancelAttrs);
        $raiseVersion($i, 3);
    }

    // 0061-0070: mutasi masuk aktif di 2027/2028 (tahun tujuan).
    for ($i = 61; $i <= 70; $i++) {
        $school = $schools[(($i - 1) % 20) + 1];
        $membership = $place($i, $targetXI1, [
            'student_entry_id' => App\Services\Academic\StudentMutationService::ENTRY_MUTATION_IN,
            'latest_student_entry_date' => '2027-07-15',
            'mutation_education_school_id' => $school->npsn,
        ]);
        $log($i, $targetYear, 'in', $targetXI1, $membership, '2027-07-15', $school->npsn, $reasonIn);
        $raiseVersion($i, 2);
    }

    // 0071-0080: tahun locked 2025/2026 untuk koreksi periode terkunci.
    $lockedX1 = $lockedClasses['UAT-P06C Locked X-1'];
    $lockedXI1 = $lockedClasses['UAT-P06C Locked XI-1'];
    $lockedXII1 = $lockedClasses['UAT-P06C Locked XII-1'];
    $lockedSchoolA = $schools[1];
    $lockedSchoolB = $schools[2];

    // 0071: mutasi masuk aktif yang dapat dikoreksi tanggal/sekolahnya.
    $membership = $place(71, $lockedX1, [
        'student_entry_id' => App\Services\Academic\StudentMutationService::ENTRY_MUTATION_IN,
        'latest_student_entry_date' => '2025-07-15',
        'mutation_education_school_id' => $lockedSchoolA->npsn,
    ]);
    $log(71, $lockedYear, 'in', $lockedX1, $membership, '2025-07-15', $lockedSchoolA->npsn, $reasonIn);
    $raiseVersion(71, 2);

    // 0072: mutasi keluar aktif yang dapat dikoreksi.
    $membership = $place(72, $lockedX1, [
        'student_status_id' => $statusOut->id,
        'latest_student_entry_date' => '2025-07-01',
        'latest_student_exit_date' => '2026-03-10',
        'latest_student_exit_school_id' => $lockedSchoolB->npsn,
        'version' => 2,
    ]);
    $log(72, $lockedYear, 'out', $lockedX1, $membership, '2026-03-10', $lockedSchoolB->npsn, $reasonOut);
    $raiseVersion(72, 2);

    // 0073: membership aktif untuk koreksi exit; 0074: sudah lulus untuk reopen.
    $place(73, $lockedXI1, ['latest_student_entry_date' => '2025-07-01']);
    $place(74, $lockedXII1, [
        'student_status_id' => $statusGraduated->id,
        'latest_student_entry_date' => '2025-07-01',
        'latest_student_graduation_date' => '2026-05-20',
        'version' => 2,
    ]);
    $raiseVersion(74, 2);
    for ($i = 75; $i <= 80; $i++) {
        $place($i, ($i % 2 === 0 ? $lockedXI1 : $lockedX1), ['latest_student_entry_date' => '2025-07-01']);
    }

    // 0081-0100 tetap tanpa membership (kandidat mutasi masuk).
    // 0101-0110 siswa terarsip dengan riwayat membership.
    for ($i = 101; $i <= 110; $i++) {
        $place($i, $sourceX2, ['latest_student_entry_date' => '2026-07-01']);
        if (! $students[$i]->trashed()) {
            $students[$i]->delete();
        }
    }

    // 0111-0120 siswa aktif tanpa mutasi (kandidat mutasi keluar baru).
    for ($i = 111; $i <= 120; $i++) {
        $place($i, $sourceXI2, ['latest_student_entry_date' => '2026-07-01']);
    }

    $p06cStudents = App\Models\Student::withTrashed()->where('name', 'like', 'UAT-P06C Student %')->count();
    $archivedStudents = App\Models\Student::onlyTrashed()->where('name', 'like', 'UAT-P06C Student %')->count();
    $mutationTotal = App\Models\StudentMutation::count();
    $mutationCancelled = App\Models\StudentMutation::where('status', App\Models\StudentMutation::STATUS_CANCELLED)->count();
    $mutationIn = App\Models\StudentMutation::where('type', App\Models\StudentMutation::TYPE_IN)->count();
    $mutationOut = App\Models\StudentMutation::where('type', App\Models\StudentMutation::TYPE_OUT)->count();
    $lockedMutations = App\Models\StudentMutation::where('school_year_id', $lockedYear->id)->count();

    fwrite(STDOUT, 'UAT-P06C students: '.$p06cStudents.' (120 expected; '.$archivedStudents.' archived), synthetic schools: '.count($schools).'.'."\n");
    fwrite(STDOUT, 'Mutation logs: '.$mutationTotal.' total (active '.($mutationTotal - $mutationCancelled).', cancelled '.$mutationCancelled.'; in '.$mutationIn.', out '.$mutationOut.'; locked-year logs '.$lockedMutations.').'."\n");
    fwrite(STDOUT, 'Locked year: 2025/2026 (non-active, locked_at set) with classes UAT-P06C Locked X-1/XI-1/XII-1, one active mutation, one closed membership, one graduation.'."\n");
    fwrite(STDOUT, "Scenario map: 0001-0020 in; 0021-0030 out; 0031-0040 re-entry in/out/in; 0041-0050 cancelled in; 0051-0060 cancelled out; 0061-0070 target-year in; 0071-0080 locked year; 0081-0100 unplaced; 0101-0110 archived; 0111-0120 active for new mutation-out.\n");
    fwrite(STDOUT, 'No 06a/06b data was reset.'."\n");
    exit(0);
}

if ($command === 'status') {
    fwrite(STDOUT, "[ISOLATION GUARD PASS] Dedicated phase-06 SQLite UAT; no MariaDB connection.\n");
    $sourceYear = App\Models\SchoolYear::where('name', '2026/2027')->first();
    $targetYear = App\Models\SchoolYear::where('name', '2027/2028')->first();
    $settings = App\Models\AcademicSetting::find(1);
    fwrite(STDOUT, 'Students: '.App\Models\Student::count().' total; '.App\Models\Student::where('name', 'like', 'UAT-P06%')->count()." UAT-P06\n");
    fwrite(STDOUT, 'Classes: '.App\Models\SchoolRombel::count().' total'."\n");
    foreach ([$sourceYear, $targetYear] as $year) {
        if (! $year) {
            continue;
        }
        $classes = App\Models\SchoolRombel::where('school_year_id', $year->id)->get(['name']);
        fwrite(STDOUT, '  '.$year->name.': '.$classes->pluck('name')->implode(', ')
            .' | locked: '.($year->locked_at ? 'yes' : 'no')."\n");
    }
    fwrite(STDOUT, 'Teachers: '.App\Models\Employee::whereHas('employeeType', fn ($query) => $query->where('name', 'Pendidik'))->whereNull('exit_date')->count()
        .' active, '.App\Models\Employee::whereHas('employeeType', fn ($query) => $query->where('name', 'Pendidik'))->whereNotNull('exit_date')->count()." separated\n");
    fwrite(STDOUT, 'Staff: '.App\Models\Employee::whereHas('employeeType', fn ($query) => $query->where('name', 'Tenaga Kependidikan'))->count()."\n");
    fwrite(STDOUT, 'Active period: '.($settings?->active_school_year_id ?? 'none').' / '.($settings?->active_semester_id ?? 'none')."\n");
    $progressionBatches = Illuminate\Support\Facades\Schema::hasTable('student_progression_batches')
        ? (string) App\Models\StudentProgressionBatch::count()
        : 'not migrated';
    fwrite(STDOUT, 'UAT-P06B: '.App\Models\Student::where('name', 'like', 'UAT-P06B%')->count().' students; progression batches: '.$progressionBatches."\n");
    $lockedYear = App\Models\SchoolYear::where('name', '2025/2026')->first();
    $mutationTotal = Illuminate\Support\Facades\Schema::hasTable('student_mutations') ? App\Models\StudentMutation::count() : 'not migrated';
    fwrite(STDOUT, 'UAT-P06C: '.App\Models\Student::withTrashed()->where('name', 'like', 'UAT-P06C Student %')->count().' students; synthetic schools: '
        .App\Models\EducationSchool::where('name', 'like', 'UAT-P06C School %')->count().'; mutation logs: '.$mutationTotal."\n");
    if ($lockedYear) {
        fwrite(STDOUT, '  Locked fixture year 2025/2026: locked '.($lockedYear->locked_at ? 'yes' : 'no')
            .', active '.((int) ($settings?->active_school_year_id ?? 0) === (int) $lockedYear->id ? 'yes' : 'no')."\n");
    }
    exit(0);
}

throw new RuntimeException('Allowed commands: init, prepare, prepare-progression, prepare-mutations, status. Serve using PHP -S 127.0.0.1:18024 with this router.');
