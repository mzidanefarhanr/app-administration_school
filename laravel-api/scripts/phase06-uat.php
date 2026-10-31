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
    exit(0);
}

throw new RuntimeException('Allowed commands: init, prepare, status. Serve using PHP -S 127.0.0.1:18024 with this router.');
