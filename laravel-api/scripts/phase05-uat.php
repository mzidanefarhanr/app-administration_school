<?php

declare(strict_types=1);

$base = str_replace('\\', '/', dirname(__DIR__));
$runtime = dirname($base).'/.uat-phase05';
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
    foreach (['', '/storage/app', '/storage/framework/cache/data', '/storage/framework/sessions', '/storage/framework/views', '/storage/logs', '/samples'] as $directory) {
        if (! is_dir($runtime.$directory) && ! mkdir($runtime.$directory, 0700, true)) {
            throw new RuntimeException('Unable to create isolated runtime directories.');
        }
    }
    touch($runtime.'/database.sqlite');
    $key = 'base64:'.base64_encode(random_bytes(32));
    $password = 'uat-'.bin2hex(random_bytes(12));
    file_put_contents($runtime.'/account-password.txt', $password);
    $env = [
        'APP_NAME' => 'School UAT Phase 05', 'APP_ENV' => 'uat', 'APP_KEY' => $key, 'APP_DEBUG' => 'false',
        'APP_URL' => 'http://127.0.0.1:18023', 'FRONTEND_URL' => 'http://127.0.0.1:14023',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $runtime.'/database.sqlite', 'DB_FOREIGN_KEYS' => 'true',
        'CACHE_DRIVER' => 'file', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'school_phase05_session',
        'SESSION_DOMAIN' => '127.0.0.1', 'SANCTUM_STATEFUL_DOMAINS' => '127.0.0.1:14023',
        'FILESYSTEM_DISK' => 'local', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'single',
    ];
    file_put_contents($runtime.'/.env', implode("\n", array_map(fn ($key, $value) => $key.'="'.$value.'"', array_keys($env), $env))."\n");
    fwrite(STDOUT, "Isolated SQLite UAT (phase 05) initialized. Credentials remain in .uat-phase05/account-password.txt (ignored).\n");
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
    || config('database.connections.sqlite.url') || config('app.url') !== 'http://127.0.0.1:18023'
    || config('session.cookie') !== 'school_phase05_session') {
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
    foreach ([
        App\Models\EducationLevel::class => Database\Seeders\EducationLevelSeeder::class,
        App\Models\Profession::class => Database\Seeders\ProfessionSeeder::class,
        App\Models\BloodType::class => Database\Seeders\BloodTypeSeeder::class,
        App\Models\FamilyStatus::class => Database\Seeders\FamilyStatusSeeder::class,
    ] as $model => $seeder) {
        if ($model::withTrashed()->count() !== 0) {
            continue;
        }
        $exit = $kernel->call('db:seed', ['--class' => $seeder, '--force' => true]);
        fwrite(STDOUT, $kernel->output());
        if ($exit !== 0) {
            exit($exit);
        }
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
    foreach ([Database\Seeders\SyntheticDataSeeder::class, Database\Seeders\EmployeeSeeder::class] as $seeder) {
        $exit = $kernel->call('db:seed', ['--class' => $seeder, '--force' => true]);
        fwrite(STDOUT, $kernel->output());
        if ($exit !== 0) {
            exit($exit);
        }
    }

    // DEC-25 D7: minimal 300 siswa tambahan untuk menguji impor/daftar.
    $genderMale = App\Models\Gender::where('name', 'Laki-Laki')->firstOrFail();
    $genderFemale = App\Models\Gender::where('name', 'Perempuan')->firstOrFail();
    for ($i = 1; $i <= 300; $i++) {
        App\Models\Student::firstOrCreate(
            ['nik' => '05'.str_pad((string) $i, 14, '0', STR_PAD_LEFT)],
            [
                'name' => 'Phase05 Student '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'birthplace' => 'Synthetic City',
                'birthdate' => '2011-02-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT),
                'gender_id' => ($i % 2 === 0 ? $genderFemale : $genderMale)->id,
                'nis' => '1'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nisn' => '05'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            ]
        );
    }

    // Contoh berkas impor sintetis (tidak berisi identitas nyata).
    $headers = App\Services\StudentImportService::templateHeaders();
    $validRows = [
        [
            'Name' => 'Synthetic Import One', 'NIK' => '0560000000000001', 'NIS' => '42001', 'NISN' => '0560000001',
            'Gender' => 'Male', 'Birthplace' => 'Synthetic City', 'Birthdate' => '2012-03-01', 'Religion' => 'Islam',
            'Family Status' => 'Both Parents Living', 'Education Level' => 'Primary School or Equivalent',
            'Profession' => 'Private Sector', 'Blood Type' => 'O', 'Address' => 'Synthetic Import Street 1',
            'RT' => '001', 'RW' => '002', 'Zip Code' => '12345', 'Phone' => '081200000099',
        ],
        [
            'Name' => 'Synthetic Import Two', 'NIK' => '0560000000000002', 'NIS' => '42002', 'NISN' => '0560000002',
            'Gender' => 'Female', 'Birthplace' => 'Synthetic City', 'Birthdate' => '2012-04-02', 'Religion' => 'Islam',
            'Family Status' => 'Both Parents Living', 'Education Level' => 'Primary School or Equivalent',
            'Profession' => 'Private Sector', 'Blood Type' => 'A', 'Address' => 'Synthetic Import Street 2',
            'RT' => '003', 'RW' => '004', 'Zip Code' => '12345', 'Phone' => '081200000098',
        ],
        [
            'Name' => 'Synthetic Import Three', 'NIK' => '0560000000000003', 'NIS' => '42003', 'NISN' => '0560000003',
            'Gender' => 'Male', 'Birthplace' => 'Synthetic City', 'Birthdate' => '2012-05-03', 'Religion' => 'Islam',
            'Family Status' => 'Both Parents Living', 'Education Level' => 'Primary School or Equivalent',
            'Profession' => 'Private Sector', 'Blood Type' => 'B', 'Address' => 'Synthetic Import Street 3',
            'RT' => '005', 'RW' => '006', 'Zip Code' => '12345', 'Phone' => '081200000097',
        ],
    ];
    // Distinct identifiers so the mixed sample stays usable after the valid sample is committed.
    $mixedRows = $validRows;
    foreach ($mixedRows as $index => $row) {
        $serial = $index + 1;
        $mixedRows[$index]['Name'] = 'Synthetic Mixed '.$serial;
        $mixedRows[$index]['NIK'] = '057000000000000'.$serial;
        $mixedRows[$index]['NIS'] = '43'.str_pad((string) $serial, 3, '0', STR_PAD_LEFT);
        $mixedRows[$index]['NISN'] = '057000000'.$serial;
    }
    $mixedRows[1]['NIK'] = '12345'; // invalid: NIK must be exactly 16 digits
    $mixedRows[2]['Gender'] = 'Unknown Value'; // invalid: reference not found

    $writeCsv = function (string $path, array $rows) use ($headers): void {
        $stream = fopen($path, 'w');
        fputcsv($stream, $headers, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($header) => $row[$header] ?? '', $headers), ',', '"', '\\');
        }
        fclose($stream);
    };
    $writeCsv($runtime.'/samples/students-import-valid.csv', $validRows);
    $writeCsv($runtime.'/samples/students-import-mixed.csv', $mixedRows);
    // Satu baris update: cocok lewat NIS (bukan nama), mengubah gender dan alamat.
    $writeCsv($runtime.'/samples/students-import-update.csv', [[
        'NIS' => '40001', 'Gender' => 'Female',
        'Address' => 'Synthetic Updated Address 40001', 'Phone' => '081200004001',
    ]]);

    fwrite(STDOUT, 'Active students: '.App\Models\Student::count()."\n");
    fwrite(STDOUT, 'Active employees: '.App\Models\Employee::count()."\n");
    fwrite(STDOUT, 'Sample files: .uat-phase05/samples/students-import-valid.csv, students-import-mixed.csv and students-import-update.csv'."\n");
    exit(0);
}
if ($command === 'prepare-data-quality') {
    $marker = 'UAT-DQ';
    $year = App\Models\SchoolYear::where('name', '2026/2027')->first();
    $rombel = $year
        ? App\Models\SchoolRombel::where('school_year_id', $year->id)->where('name', 'X-1')->first()
        : null;
    if (! $year || ! $rombel) {
        throw new RuntimeException('Run the prepare command first: school year 2026/2027 and rombel X-1 are required.');
    }

    $genderMale = App\Models\Gender::where('name', 'Laki-Laki')->firstOrFail();
    $genderFemale = App\Models\Gender::where('name', 'Perempuan')->firstOrFail();
    $religion = App\Models\Religion::where('name', 'Islam')->firstOrFail();
    $statusActive = App\Models\StudentStatus::where('name', 'Aktif')->firstOrFail();
    $entryNew = App\Models\StudentEntry::where('name', 'Siswa Baru')->firstOrFail();

    $teacherType = App\Models\EmployeeType::where('name', 'Pendidik')->firstOrFail();
    $staffType = App\Models\EmployeeType::where('name', 'Tenaga Kependidikan')->firstOrFail();
    $teacherStatus = App\Models\EmployeeStatus::where('name', 'Guru Tetap Yayasan')->firstOrFail();
    $staffStatus = App\Models\EmployeeStatus::where('name', 'Pegawai Tetap Yayasan')->firstOrFail();
    $teacherEmployment = App\Models\Employment::where('name', 'Guru Mata Pelajaran')->firstOrFail();
    $staffEmployment = App\Models\Employment::where('name', 'Tenaga Administrasi Sekolah')->firstOrFail();
    $district = App\Models\District::where('name', 'Synthetic District')->firstOrFail();
    $maritalStatus = App\Models\MaritalStatus::where('name', 'Belum Menikah')->firstOrFail();
    $subjects = App\Models\Subject::whereIn('name', ['Matematika', 'Bahasa Inggris', 'Ilmu Pengetahuan Alam (IPA)'])
        ->orderBy('id')
        ->get()
        ->keyBy('name');
    if ($subjects->count() !== 3) {
        throw new RuntimeException('Run the prepare command first: synthetic subjects are required.');
    }
    $subjectNames = $subjects->keys()->values()->all();

    $studentSpecs = [];
    for ($serial = 1; $serial <= 60; $serial++) {
        $slot = str_pad((string) $serial, 3, '0', STR_PAD_LEFT);
        $spec = [
            'slot' => $slot,
            'placed' => true,
            'name' => $marker.' Student '.$slot,
            'nik' => '9999'.str_pad((string) $serial, 12, '0', STR_PAD_LEFT),
            'nis' => 'DQ-S-'.$slot,
            'nisn' => '9999'.str_pad((string) $serial, 6, '0', STR_PAD_LEFT),
            'birthdate' => date('Y-m-d', strtotime('2011-01-01 + '.($serial - 1).' days')),
        ];
        if ($serial >= 21 && $serial <= 25) {
            $spec['nik'] = null;
        } elseif ($serial >= 26 && $serial <= 30) {
            $spec['nik'] = '90000'.str_pad((string) $serial, 5, '0', STR_PAD_LEFT);
        } elseif ($serial >= 31 && $serial <= 35) {
            $spec['nisn'] = null;
        } elseif ($serial >= 36 && $serial <= 40) {
            $spec['birthdate'] = null;
        } elseif ($serial >= 41 && $serial <= 50) {
            $spec['nik'] = null;
            $spec['placed'] = false;
        } elseif ($serial >= 51 && $serial <= 60) {
            $pair = intdiv($serial - 51, 2) + 1;
            $spec['name'] = $marker.' Duplicate Pair '.$pair;
            $spec['birthdate'] = '2012-06-0'.$pair;
        }
        $studentSpecs[] = $spec;
    }

    $reservedStudentNiks = array_values(array_filter(array_column($studentSpecs, 'nik')));
    $studentCollision = App\Models\Student::withTrashed()
        ->where(function ($query) use ($reservedStudentNiks) {
            $query->where('nis', 'like', 'DQ-S-%')
                ->orWhere('nisn', 'like', '9999%')
                ->orWhereIn('nik', $reservedStudentNiks);
        })
        ->where('name', 'not like', $marker.'%')
        ->exists();
    if ($studentCollision) {
        throw new RuntimeException('Existing students outside the UAT-DQ fixture use reserved NIK/NIS/NISN values.');
    }

    $employeeSpecs = [];
    $employeeSerial = 0;
    $nikSerial = 0;
    $addEmployee = function (string $name, bool $teacher, array $options = []) use (&$employeeSpecs, &$employeeSerial, &$nikSerial): void {
        $employeeSerial++;
        $withNik = $options['with_nik'] ?? true;
        if ($withNik) {
            $nikSerial++;
        }
        $employeeSpecs[] = [
            'name' => $name,
            'teacher' => $teacher,
            'nuptk' => $options['nuptk'] ?? null,
            'subject_name' => $options['subject_name'] ?? null,
            'nik' => $withNik ? '9998'.str_pad((string) $nikSerial, 12, '0', STR_PAD_LEFT) : null,
            'exit_date' => $options['exit_date'] ?? null,
            'date_of_birth' => $options['date_of_birth'] ?? date('Y-m-d', strtotime('1980-01-01 + '.$employeeSerial.' days')),
            'serial' => $employeeSerial,
        ];
    };

    for ($i = 1; $i <= 10; $i++) {
        $number = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $addEmployee($marker.' Teacher '.$number, true, [
            'nuptk' => 'DQ-NUPTK-'.$number,
            'subject_name' => $subjectNames[($i - 1) % 3],
        ]);
    }
    for ($i = 1; $i <= 10; $i++) {
        $addEmployee($marker.' Staff '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), false);
    }
    for ($i = 21; $i <= 27; $i++) {
        $number = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $addEmployee($marker.' Teacher '.$number, true, [
            'subject_name' => $subjectNames[($i - 21) % 3],
        ]);
    }
    for ($i = 21; $i <= 27; $i++) {
        $addEmployee($marker.' Staff '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), false, ['with_nik' => false]);
    }
    for ($i = 28; $i <= 33; $i++) {
        $number = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $addEmployee($marker.' Teacher '.$number, true, [
            'with_nik' => false,
            'nuptk' => 'DQ-NUPTK-'.$number,
            'subject_name' => $subjectNames[($i - 28) % 3],
        ]);
    }
    for ($i = 34; $i <= 43; $i++) {
        $pair = intdiv($i - 34, 2) + 1;
        $addEmployee($marker.' Teacher '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), true, [
            'nuptk' => 'DQ-NUPTK-DUPLICATE-'.$pair,
            'subject_name' => $subjectNames[($i - 34) % 3],
        ]);
    }
    for ($i = 1; $i <= 10; $i++) {
        $addEmployee($marker.' Separated '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), false, [
            'with_nik' => false,
            'exit_date' => '2020-12-31',
        ]);
    }

    $reservedEmployeeNiks = array_values(array_filter(array_column($employeeSpecs, 'nik')));
    $reservedNuptks = array_values(array_filter(array_column($employeeSpecs, 'nuptk')));
    $employeeCollision = App\Models\Employee::withTrashed()
        ->where(function ($query) use ($reservedEmployeeNiks, $reservedNuptks) {
            $query->whereIn('nik', $reservedEmployeeNiks)->orWhereIn('nuptk', $reservedNuptks);
        })
        ->where('name', 'not like', $marker.'%')
        ->exists();
    if ($employeeCollision) {
        throw new RuntimeException('Existing employees outside the UAT-DQ fixture use reserved NIK/NUPTK values.');
    }

    $createdStudents = 0;
    $markerStudents = collect();
    foreach ($studentSpecs as $spec) {
        $student = App\Models\Student::firstOrCreate(
            ['nis' => $spec['nis']],
            [
                'name' => $spec['name'],
                'nik' => $spec['nik'],
                'birthplace' => 'UAT Synthetic City',
                'birthdate' => $spec['birthdate'],
                'gender_id' => ((int) $spec['slot'] % 2 === 0 ? $genderFemale : $genderMale)->id,
                'religion_id' => $religion->id,
                'nisn' => $spec['nisn'],
            ]
        );
        $createdStudents += $student->wasRecentlyCreated ? 1 : 0;
        $markerStudents->push($student);

        if ($spec['placed']) {
            App\Models\StudentRombel::firstOrCreate(
                ['student_id' => $student->id, 'school_rombel' => $rombel->id],
                [
                    'student_nik' => $student->nik,
                    'student_status_id' => $statusActive->id,
                    'student_entry_id' => $entryNew->id,
                ]
            );
        }
    }

    $createdEmployees = 0;
    foreach ($employeeSpecs as $spec) {
        $subject = $spec['subject_name'] !== null ? $subjects->get($spec['subject_name']) : null;
        $employee = App\Models\Employee::firstOrCreate(
            ['name' => $spec['name']],
            [
                'nik' => $spec['nik'],
                'employee_type_id' => ($spec['teacher'] ? $teacherType : $staffType)->id,
                'employee_status_id' => ($spec['teacher'] ? $teacherStatus : $staffStatus)->id,
                'employment_id' => ($spec['teacher'] ? $teacherEmployment : $staffEmployment)->id,
                'subject_id' => $subject?->id,
                'religion_id' => $religion->id,
                'district_id' => $district->id,
                'marital_status_id' => $maritalStatus->id,
                'gender_id' => ($spec['serial'] % 2 === 0 ? $genderFemale : $genderMale)->id,
                'nuptk' => $spec['nuptk'],
                'date_of_birth' => $spec['date_of_birth'],
                'place_of_birth' => 'UAT Synthetic City',
                'address' => 'UAT Synthetic Address '.$spec['serial'],
                'tmt_employee' => '2020-01-01',
                'exit_date' => $spec['exit_date'],
            ]
        );
        $createdEmployees += $employee->wasRecentlyCreated ? 1 : 0;
    }

    $placedMarker = count(App\Support\StudentDirectory::membershipsFor($markerStudents, (int) $year->id));
    $activeMarker = App\Models\Employee::where('name', 'like', $marker.'%')->whereNull('exit_date')->count();
    $separatedMarker = App\Models\Employee::where('name', 'like', $marker.'%')->whereNotNull('exit_date')->count();

    $studentIssues = app(App\Services\DataQuality\StudentCompletenessInspector::class)
        ->issues((int) $year->id, ['status' => 'all', 'per_page' => 500, 'page' => 1])
        ->items();
    $studentMarkerIssues = array_values(array_filter(
        $studentIssues,
        fn (array $issue): bool => str_starts_with((string) ($issue['subject']['name'] ?? ''), $marker)
    ));
    $employeeIssues = app(App\Services\DataQuality\EmployeeCompletenessInspector::class)
        ->issues(['status' => 'all', 'per_page' => 1000, 'page' => 1])
        ->items();
    $employeeMarkerIssues = array_values(array_filter(
        $employeeIssues,
        fn (array $issue): bool => str_starts_with((string) ($issue['subject']['name'] ?? ''), $marker)
    ));

    $tally = function (array $issues): array {
        $counts = [];
        foreach ($issues as $issue) {
            $key = $issue['type'].':'.$issue['field'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    };

    $expectedStudentTally = [
        'blocked:nik' => 10,
        'duplicate:name_birthdate' => 5,
        'invalid:nik' => 5,
        'missing:birthdate' => 5,
        'missing:nik' => 5,
        'missing:nisn' => 5,
    ];
    $expectedEmployeeTally = [
        'duplicate:nuptk' => 5,
        'missing:nik' => 13,
        'missing:nuptk' => 7,
    ];
    $studentTally = $tally($studentMarkerIssues);
    $employeeTally = $tally($employeeMarkerIssues);

    if ($markerStudents->count() !== 60 || $placedMarker !== 50) {
        throw new RuntimeException('UAT-DQ student fixture verification failed: 60 students (50 placed) expected.');
    }
    if ($separatedMarker !== 10 || $activeMarker !== 50) {
        throw new RuntimeException('UAT-DQ employee fixture verification failed: 50 active and 10 separated employees expected.');
    }
    if ($studentTally !== $expectedStudentTally) {
        throw new RuntimeException('UAT-DQ student issue tally mismatch: '.json_encode($studentTally));
    }
    if ($employeeTally !== $expectedEmployeeTally) {
        throw new RuntimeException('UAT-DQ employee issue tally mismatch: '.json_encode($employeeTally));
    }

    fwrite(STDOUT, 'Data quality fixture ready: 60 students ('.$placedMarker.' placed) and 60 employees ('.$activeMarker.' active, '.$separatedMarker.' separated).'."\n");
    fwrite(STDOUT, 'Created this run: '.$createdStudents.' students, '.$createdEmployees.' employees.'."\n");
    fwrite(STDOUT, 'UAT-DQ student issues ('.count($studentMarkerIssues).'): '.json_encode($studentTally)."\n");
    fwrite(STDOUT, 'UAT-DQ employee issues ('.count($employeeMarkerIssues).'): '.json_encode($employeeTally)."\n");
    exit(0);
}
if ($command === 'status') {
    fwrite(STDOUT, "[ISOLATION GUARD PASS] Dedicated phase-05 SQLite UAT; no MariaDB connection.\n");
    fwrite(STDOUT, 'Students: '.App\Models\Student::count()."\n");
    fwrite(STDOUT, 'Employees: '.App\Models\Employee::count()."\n");
    fwrite(STDOUT, 'Teachers: '.App\Models\Employee::whereHas('employeeType', fn ($query) => $query->where('name', 'Pendidik'))->count()."\n");
    fwrite(STDOUT, 'Staff: '.App\Models\Employee::whereHas('employeeType', fn ($query) => $query->where('name', 'Tenaga Kependidikan'))->count()."\n");
    $importAbility = App\Models\Ability::where('name', 'students.import')->first();
    fwrite(STDOUT, 'students.import ability: '.($importAbility ? 'present' : 'MISSING')."\n");
    if ($importAbility) {
        fwrite(STDOUT, 'Roles with students.import: '.App\Models\Role::whereHas('abilities', fn ($query) => $query->where('abilities.id', $importAbility->id))->pluck('slug')->implode(', ')."\n");
    }
    fwrite(STDOUT, 'Import batches: '.App\Models\ImportBatch::count()."\n");
    fwrite(STDOUT, 'Import files: '.count(glob($runtime.'/storage/app/imports/*/source.csv') ?: [])." batches on disk\n");
    $dqYear = App\Models\SchoolYear::where('name', '2026/2027')->first();
    if ($dqYear) {
        $dqStudents = App\Models\Student::where('name', 'like', 'UAT-DQ%')->get();
        $dqPlaced = count(App\Support\StudentDirectory::membershipsFor($dqStudents, (int) $dqYear->id));
        fwrite(STDOUT, 'Data quality students: '.$dqStudents->count().' ('.$dqPlaced.' placed)'."\n");
        fwrite(STDOUT, 'Data quality employees: '.App\Models\Employee::where('name', 'like', 'UAT-DQ%')->count()
            .' ('.App\Models\Employee::where('name', 'like', 'UAT-DQ%')->whereNull('exit_date')->count().' active)'."\n");
    }
    exit(0);
}
throw new RuntimeException('Allowed commands: init, prepare, prepare-data-quality, status. Serve using PHP -S 127.0.0.1:18023 with this router.');
