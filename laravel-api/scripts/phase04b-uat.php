<?php

declare(strict_types=1);

$base = str_replace('\\', '/', dirname(__DIR__));
$runtime = dirname($base).'/.uat-phase04b';
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
        'APP_NAME' => 'School UAT', 'APP_ENV' => 'uat', 'APP_KEY' => $key, 'APP_DEBUG' => 'false',
        'APP_URL' => 'http://127.0.0.1:18022', 'FRONTEND_URL' => 'http://127.0.0.1:14022',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $runtime.'/database.sqlite', 'DB_FOREIGN_KEYS' => 'true',
        'CACHE_DRIVER' => 'file', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'school_phase04b_session',
        'SESSION_DOMAIN' => '127.0.0.1', 'SANCTUM_STATEFUL_DOMAINS' => '127.0.0.1:14022',
        'FILESYSTEM_DISK' => 'local', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'single',
    ];
    file_put_contents($runtime.'/.env', implode("\n", array_map(fn ($key, $value) => $key.'="'.$value.'"', array_keys($env), $env))."\n");
    fwrite(STDOUT, "Isolated SQLite UAT initialized. Credentials remain in .uat-phase04b/account-password.txt (ignored).\n");
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
    || config('database.connections.sqlite.url') || config('app.url') !== 'http://127.0.0.1:18022'
    || config('session.cookie') !== 'school_phase04b_session') {
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
    // Seed only empty catalogs, including archived rows in the check.
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
    // Reuse EmployeeSeeder's synthetic district; never import real schools.
    $district = App\Models\District::where('name', 'Synthetic District')
        ->whereHas('regency', fn ($query) => $query->where('name', 'Synthetic Regency')
            ->whereHas('province', fn ($query) => $query->where('name', 'Synthetic Province')))
        ->firstOrFail();
    $educationLevel = App\Models\EducationLevel::withTrashed()->firstOrCreate(['name' => 'SMP/SLTP/Sederajat']);
    for ($i = 1; $i <= 100; $i++) {
        App\Models\EducationSchool::withTrashed()->firstOrCreate(
            ['npsn' => '04'.str_pad((string) $i, 6, '0', STR_PAD_LEFT)],
            [
                'name' => 'Synthetic UAT School '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'education_level_id' => $educationLevel->id,
                'status_education' => $i % 2 === 0 ? 'Swasta' : 'Negeri',
                'address' => 'Synthetic UAT School Address '.$i,
                'district_id' => $district->id,
            ]
        );
    }
    fwrite(STDOUT, 'Active employees: '.App\Models\Employee::count()."\n");
    exit(0);
}
if ($command === 'status') {
    fwrite(STDOUT, "[ISOLATION GUARD PASS] Dedicated phase-04b SQLite UAT; no MariaDB connection.\n");
    fwrite(STDOUT, 'Students: '.App\Models\Student::count()."\n");
    fwrite(STDOUT, 'EducationSchools: '.App\Models\EducationSchool::count()."\n");
    foreach (App\Models\EmployeeType::all() as $type) {
        $label = match ($type->name) {
            'Pendidik' => 'Teachers',
            'Tenaga Kependidikan' => 'Staff',
            default => 'Other employees',
        };
        fwrite(STDOUT, $label.': '.App\Models\Employee::where('employee_type_id', $type->id)->count()."\n");
    }
    exit(0);
}
throw new RuntimeException('Allowed commands: init, prepare, status. Serve using PHP -S 127.0.0.1:18022 with this router.');
