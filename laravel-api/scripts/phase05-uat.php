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
    exit(0);
}
throw new RuntimeException('Allowed commands: init, prepare, status. Serve using PHP -S 127.0.0.1:18023 with this router.');
