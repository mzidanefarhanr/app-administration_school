<?php

declare(strict_types=1);

$base = str_replace('\\', '/', dirname(__DIR__));
$runtime = dirname($base).'/.uat-phase09';
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
        'APP_NAME' => 'School UAT Phase 09', 'APP_ENV' => 'uat', 'APP_KEY' => $key, 'APP_DEBUG' => 'false',
        'APP_URL' => 'http://127.0.0.1:18027', 'FRONTEND_URL' => 'http://127.0.0.1:14027',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $runtime.'/database.sqlite', 'DB_FOREIGN_KEYS' => 'true',
        'CACHE_DRIVER' => 'file', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'school_phase09_session',
        'SESSION_DOMAIN' => '127.0.0.1', 'SANCTUM_STATEFUL_DOMAINS' => '127.0.0.1:14027',
        'FILESYSTEM_DISK' => 'local', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'single',
    ];
    file_put_contents($runtime.'/.env', implode("\n", array_map(fn ($key, $value) => $key.'="'.$value.'"', array_keys($env), $env))."\n");
    fwrite(STDOUT, "Isolated SQLite UAT (phase 09) initialized. Credentials remain in .uat-phase09/account-password.txt (ignored).\n");
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
    || config('database.connections.sqlite.url') || config('app.url') !== 'http://127.0.0.1:18027'
    || config('session.cookie') !== 'school_phase09_session') {
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

    $archivistRole = App\Models\Role::withTrashed()->firstOrNew(['slug' => 'uat-archivist']);
    $archivistRole->fill([
        'name' => 'UAT Archivist',
        'description' => 'Read-only archive reviewer for isolated UAT (archives.view only).',
        'is_protected' => false,
    ]);
    $archivistRole->deleted_at = null;
    $archivistRole->save();

    $viewAbility = App\Models\Ability::where('name', 'archives.view')->firstOrFail();
    $archivistRole->abilities()->sync([$viewAbility->id]);

    $archivist = App\Models\User::withTrashed()->firstOrNew(['username' => 'uat.archivist']);
    $archivist->fill([
        'name' => 'UAT Archivist',
        'email' => 'uat.archivist@uat.local',
        'email_verified_at' => now(),
        'password' => bcrypt($password),
        'nik' => '9000000000000007',
        'type_user_id' => $userType->id,
        'status_user_id' => $activeStatus->id,
        'first_login_at' => 0,
        'status_active' => true,
    ]);
    $archivist->deleted_at = null;
    $archivist->save();
    $archivist->roles()->sync([$archivistRole->id]);

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

    fwrite(STDOUT, 'Users: '.App\Models\User::withTrashed()->count().' (uat.admin, uat.operator, uat.kepsek, uat.waka, uat.guru, uat.inactive, uat.archivist).'."\n");
    fwrite(STDOUT, 'uat.archivist role abilities: '.$archivistRole->abilities()->pluck('name')->implode(', ').".\n");
    fwrite(STDOUT, 'Prepare complete. Run prepare-archives to populate the synthetic archive agenda.'."\n");
    exit(0);
}

if ($command === 'prepare-archives') {
    $exit = $kernel->call('migrate', ['--force' => true]);
    fwrite(STDOUT, $kernel->output());
    if ($exit !== 0) {
        exit($exit);
    }

    $operator = App\Models\User::where('username', 'uat.operator')->first();
    if (! $operator) {
        throw new RuntimeException('Run prepare first (uat.operator is required).');
    }

    // 1. Tahun ajaran aktif (menentukan rentang izin siswa).
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
        throw new RuntimeException('The active school year is already set to another year; reset it before preparing archive fixtures.');
    }
    $academic->active_school_year_id = $year->id;
    $academic->updated_by = $operator->id;
    $academic->save();

    // 2. Referensi minimum.
    $studentStatus = App\Models\StudentStatus::firstOrCreate(['name' => 'Aktif']);
    if ((int) $studentStatus->id !== 1) {
        throw new RuntimeException('The active student status does not match the canonical id 1 baseline.');
    }
    $studentEntry = App\Models\StudentEntry::firstOrCreate(['name' => 'Siswa Baru']);
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
    $level = App\Models\SchoolLevel::firstOrCreate(['name' => 'UAT Level']);

    // 3. 120 siswa dalam 3 rombel.
    $classNames = ['X-A', 'X-B', 'XI-A'];
    $classes = [];
    foreach ($classNames as $suffix) {
        $classes[$suffix] = App\Models\SchoolRombel::withTrashed()->firstOrNew([
            'school_year_id' => $year->id,
            'name' => 'UAT-P09 '.$suffix,
        ]);
        $classes[$suffix]->fill(['school_level_id' => $level->id]);
        $classes[$suffix]->deleted_at = null;
        $classes[$suffix]->save();
    }

    $students = [];
    $studentsCreated = 0;
    for ($i = 1; $i <= 120; $i++) {
        $nis = 'P09'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $student = App\Models\Student::withTrashed()->where('nis', $nis)->first();
        if (! $student) {
            $student = App\Models\Student::factory()->create([
                'nis' => $nis,
                'nik' => '09'.str_pad((string) $i, 14, '0', STR_PAD_LEFT),
                'nisn' => '0090'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'name' => 'UAT-P09 Student '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'birthplace' => 'Synthetic City',
                'birthdate' => '2010-01-01',
                'gender_id' => $i % 2 === 0 ? $female->id : $male->id,
            ]);
            $studentsCreated++;
        } else {
            $student->fill([
                'name' => 'UAT-P09 Student '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'deleted_at' => null,
            ]);
            $student->save();
        }

        $classKey = $classNames[(int) floor(($i - 1) / 40)];
        $membership = App\Models\StudentRombel::withTrashed()->firstOrNew([
            'student_id' => $student->id,
            'school_rombel' => $classes[$classKey]->id,
        ]);
        $membership->student_nik = $student->nik;
        $membership->student_status_id = $studentStatus->id;
        $membership->student_entry_id = $studentEntry->id;
        $membership->latest_student_entry_date = '2026-07-01';
        $membership->latest_student_exit_date = null;
        $membership->archived_at = null;
        $membership->deleted_at = null;
        $membership->save();

        $students[$i] = $student;
    }

    // 4. 20 pegawai sintetis.
    $employees = [];
    $employeesCreated = 0;
    for ($i = 1; $i <= 20; $i++) {
        $name = sprintf('UAT-P09 Employee %02d', $i);
        $employee = App\Models\Employee::withTrashed()->firstOrNew(['name' => $name]);
        if (! $employee->exists) {
            $employeesCreated++;
        }
        $employee->fill([
            'employee_status_id' => $employeeStatus->id,
            'employee_type_id' => $i <= 12 ? $teacherType->id : $staffType->id,
            'employment_id' => $employment->id,
            'subject_id' => $i <= 12 ? $subject->id : null,
            'religion_id' => $religion->id,
            'district_id' => $district->id,
            'marital_status_id' => $marital->id,
            'gender_id' => $i % 2 === 0 ? $female->id : $male->id,
            'nuptk' => 'P09'.str_pad((string) $i, 13, '0', STR_PAD_LEFT),
            'place_of_birth' => 'Synthetic City',
            'date_of_birth' => '1990-01-01',
            'address' => 'Jl. Sintetis UAT No. '.$i,
            'email' => 'uat.employee'.sprintf('%02d', $i).'.p09@uat.local',
            'tmt_employee' => '2020-01-01',
            'exit_date' => null,
        ]);
        $employee->deleted_at = null;
        $employee->save();
        $employees[$i] = $employee;
    }

    // 5. 20 surat masuk sintetis.
    $letters = [];
    $lettersCreated = 0;
    for ($i = 1; $i <= 20; $i++) {
        $sourceNumber = sprintf('P09/LETTER/%03d', $i);
        $letter = App\Models\Letter::query()->where('source_number', $sourceNumber)->first();
        if (! $letter) {
            $letter = App\Models\Letter::create([
                'direction' => App\Models\Letter::DIRECTION_INCOMING,
                'received_date' => Carbon\Carbon::create(2026, 7, 1)->addDays($i)->format('Y-m-d'),
                'letter_date' => Carbon\Carbon::create(2026, 6, 25)->addDays($i)->format('Y-m-d'),
                'source_number' => $sourceNumber,
                'sender' => 'Synthetic UAT Institution '.$i,
                'subject' => sprintf('UAT-P09 Incoming Letter Subject %03d', $i),
                'category' => $i % 3 === 0 ? 'Circular' : 'General',
                'status' => App\Models\Letter::STATUS_ACTIVE,
                'created_by' => $operator->id,
            ]);
            $lettersCreated++;
        }
        $letters[$i] = $letter;
    }

    // 6. 20 izin sintetis (10 siswa, 10 pegawai) sebagai subjek arsip.
    /** @var App\Services\Leaves\LeaveRequestService $leaveService */
    $leaveService = $app->make(App\Services\Leaves\LeaveRequestService::class);
    $studentTypes = array_keys((array) config('leaves.types.student'));
    $employeeTypes = array_keys((array) config('leaves.types.employee'));
    $leaves = [];
    $leavesCreated = 0;
    for ($i = 1; $i <= 20; $i++) {
        $kind = $i <= 10 ? 'student' : 'employee';
        $reason = sprintf('UAT-P09 synthetic archive leave fixture %03d (%s).', $i, $kind);
        $existing = App\Models\LeaveRequest::query()->where('reason', $reason)->first();
        if ($existing) {
            $leaves[$i] = $existing;

            continue;
        }

        $start = Carbon\Carbon::create(2026, 7, 6)->addDays(($i - 1) * 7);
        $leave = $leaveService->create([
            'subject_type' => $kind,
            'student_id' => $kind === 'student' ? (int) $students[$i]->id : null,
            'employee_id' => $kind === 'employee' ? (int) $employees[$i - 10]->id : null,
            'type' => $kind === 'student'
                ? $studentTypes[($i - 1) % count($studentTypes)]
                : $employeeTypes[($i - 1) % count($employeeTypes)],
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->copy()->addDay()->format('Y-m-d'),
            'reason' => $reason,
        ], $operator);
        $leaves[$i] = App\Models\LeaveRequest::findOrFail((int) $leave['id']);
        $leavesCreated++;
    }

    // 7. 120 dokumen arsip + 160 versi.
    /** @var App\Services\Archives\ArchiveDocumentService $archiveService */
    $archiveService = $app->make(App\Services\Archives\ArchiveDocumentService::class);
    $today = Carbon\Carbon::now('Asia/Jakarta')->startOfDay();
    $samplePng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $sources = [];
    for ($i = 1; $i <= 40; $i++) {
        $sources[] = ['kind' => 'student', 'subject_id' => (int) $students[$i]->id, 'category' => 'student_record'];
    }
    for ($i = 1; $i <= 20; $i++) {
        $sources[] = ['kind' => 'employee', 'subject_id' => (int) $employees[$i]->id, 'category' => 'employee_record'];
    }
    for ($i = 1; $i <= 20; $i++) {
        $sources[] = ['kind' => 'letter', 'subject_id' => (int) $letters[$i]->id, 'category' => 'letter'];
    }
    for ($i = 1; $i <= 20; $i++) {
        $sources[] = ['kind' => 'leave', 'subject_id' => (int) $leaves[$i]->id, 'category' => 'leave'];
    }
    for ($i = 1; $i <= 20; $i++) {
        $sources[] = ['kind' => 'general', 'subject_id' => null, 'category' => $i % 2 === 0 ? 'school_document' : 'other'];
    }

    $documentsCreated = 0;
    $documentsReused = 0;
    $versionsCreated = 0;

    foreach ($sources as $index => $source) {
        $number = (int) $index + 1;
        $documentNumber = sprintf('P09/ARCHIVE/%03d', $number);
        $existing = App\Models\ArchiveDocument::query()->where('document_number', $documentNumber)->first();
        if ($existing) {
            $documentsReused++;

            continue;
        }

        $validity = $number % 5;
        $validFrom = null;
        $validUntil = null;
        if ($validity === 1) {
            $validFrom = $today->copy()->addDays(30)->format('Y-m-d');
            $validUntil = $today->copy()->addDays(395)->format('Y-m-d');
        } elseif ($validity === 2) {
            $validFrom = $today->copy()->subDays(30)->format('Y-m-d');
            $validUntil = $today->copy()->addDays(180)->format('Y-m-d');
        } elseif ($validity === 3) {
            $validFrom = $today->copy()->subDays(90)->format('Y-m-d');
            $validUntil = $today->copy()->addDays(15)->format('Y-m-d');
        } elseif ($validity === 4) {
            $validFrom = $today->copy()->subDays(365)->format('Y-m-d');
            $validUntil = $today->copy()->subDays(30)->format('Y-m-d');
        }

        $payload = [
            'category' => $source['category'],
            'title' => sprintf('UAT-P09 Archive Document %03d (%s)', $number, str_replace('_', ' ', $source['category'])),
            'document_number' => $documentNumber,
            'document_date' => $today->copy()->subDays($number % 60)->format('Y-m-d'),
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'description' => $number % 4 === 0
                ? sprintf('Synthetic archive metadata for UAT phase 09; document %03d.', $number)
                : null,
        ];

        if ($source['kind'] !== 'general') {
            $payload['subject_type'] = $source['kind'];
            $payload['subject_id'] = $source['subject_id'];
        }

        $file = $number % 3 === 0
            ? phase09Upload('UAT-P09-Document-'.$number.'.png', $samplePng)
            : phase09Upload('UAT-P09-Document-'.$number.'.pdf', phase09Pdf($number));
        $document = $archiveService->create($payload, $file, $operator);
        @unlink($file->getRealPath());
        $documentsCreated++;

        $documentId = (int) $document['id'];
        $expectedVersion = 1;

        if ($number % 4 === 0) {
            $second = phase09Upload(
                'UAT-P09-Document-'.$number.'-revision-2.pdf',
                phase09Pdf($number * 100 + 2)
            );
            $document = $archiveService->addVersion(
                $documentId,
                $second,
                sprintf('UAT-P09 synthetic revision 2 for document %03d.', $number),
                $expectedVersion,
                $operator
            );
            @unlink($second->getRealPath());
            $expectedVersion = 2;
            $versionsCreated++;
        }

        if ($number % 12 === 0) {
            $third = phase09Upload(
                'UAT-P09-Document-'.$number.'-revision-3.png',
                $samplePng
            );
            $archiveService->addVersion(
                $documentId,
                $third,
                sprintf('UAT-P09 synthetic revision 3 for document %03d.', $number),
                $expectedVersion,
                $operator
            );
            @unlink($third->getRealPath());
            $versionsCreated++;
        }
    }

    $bySubject = App\Models\ArchiveDocument::query()
        ->selectRaw("COALESCE(subject_type, 'general') as subject_key, COUNT(*) as aggregate")
        ->groupBy('subject_key')
        ->pluck('aggregate', 'subject_key');
    $byCategory = App\Models\ArchiveDocument::query()->pluck('category')->countBy();
    $byValidity = App\Models\ArchiveDocument::query()->get()
        ->map(fn (App\Models\ArchiveDocument $document) => $document->validityStatus())
        ->countBy();
    $attachmentFiles = Illuminate\Support\Facades\Storage::disk((string) config('archives.attachment.disk'))
        ->allFiles((string) config('archives.attachment.path'));

    fwrite(STDOUT, 'School year: '.$year->name.' ('.$year->start_date->format('Y-m-d').' to '
        .$year->end_date->format('Y-m-d').'), active id '.$academic->active_school_year_id.".\n");
    fwrite(STDOUT, 'Students: '.App\Models\Student::count().' total ('.$studentsCreated.' created this run); '
        .App\Models\SchoolRombel::query()->where('school_year_id', $year->id)->count().' rombels.'."\n");
    fwrite(STDOUT, 'Employees: '.App\Models\Employee::count().' total ('.$employeesCreated.' created this run).'."\n");
    fwrite(STDOUT, 'Letters: '.App\Models\Letter::count().' total ('.$lettersCreated.' created this run).'."\n");
    fwrite(STDOUT, 'Leave requests: '.App\Models\LeaveRequest::count().' total ('.$leavesCreated.' created this run).'."\n");
    fwrite(STDOUT, 'Archive documents: '.App\Models\ArchiveDocument::count()." total ({$documentsCreated} created, {$documentsReused} reused).\n");
    fwrite(STDOUT, 'Version files: '.App\Models\ArchiveFileVersion::count().' total ('.$versionsCreated.' added this run); '
        .count($attachmentFiles).' private files.'."\n");
    fwrite(STDOUT, 'By subject: '.collect($bySubject)->map(fn ($count, $key) => $key.' '.$count)->implode(', ').".\n");
    fwrite(STDOUT, 'By category: '.$byCategory->map(fn ($count, $key) => $key.' '.$count)->implode(', ').".\n");
    fwrite(STDOUT, 'By validity: '.$byValidity->map(fn ($count, $key) => $key.' '.$count)->implode(', ').".\n");
    fwrite(STDOUT, 'Accounts: uat.admin, uat.operator, uat.kepsek, uat.waka, uat.guru, uat.inactive, uat.archivist.'."\n");
    exit(0);
}

if ($command === 'prepare-09b') {
    $exit = $kernel->call('migrate', ['--force' => true]);
    fwrite(STDOUT, $kernel->output());
    if ($exit !== 0) {
        exit($exit);
    }

    $operator = App\Models\User::where('username', 'uat.operator')->first();
    if (! $operator) {
        throw new RuntimeException('Run prepare first (uat.operator is required).');
    }

    $legacyCount = App\Models\ArchiveDocument::query()->where('document_number', 'like', 'P09/ARCHIVE/%')->count();
    if ($legacyCount === 0) {
        throw new RuntimeException('Run prepare-archives first; prepare-09b is additive on the accepted 09a fixtures and never resets them.');
    }

    $archiver = App\Models\User::query()
        ->where('username', 'uat.operator')
        ->whereHas('roles.abilities', fn ($query) => $query->where('name', 'archives.manage'))
        ->first();
    if (! $archiver) {
        throw new RuntimeException('uat.operator must hold archives.manage; re-run prepare before prepare-09b.');
    }

    $auditor = App\Models\User::where('username', 'uat.admin')->first();
    if (! $auditor) {
        throw new RuntimeException('uat.admin is required for the synthetic access-history fixtures.');
    }

    $student = App\Models\Student::query()->where('nis', 'P09001')->first();
    if (! $student) {
        throw new RuntimeException('The 09a student fixture (nis P09001) is missing; re-run prepare-archives.');
    }

    /** @var App\Services\Archives\ArchiveDocumentService $archiveService */
    $archiveService = $app->make(App\Services\Archives\ArchiveDocumentService::class);

    $today = Carbon\Carbon::now('Asia/Jakarta')->startOfDay();
    $samplePng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    // Fixture 09b: batas expiring_soon, kedaluwarsa, upcoming, arsip, probe file, dan retensi.
    $fixtures = [
        ['category' => 'school_document', 'title' => 'UAT-P09B Expiring Tomorrow', 'from' => '-30 days', 'until' => '+1 day', 'file' => 'pdf'],
        ['category' => 'school_document', 'title' => 'UAT-P09B Expiring In Fifteen Days', 'from' => '-15 days', 'until' => '+15 days', 'file' => 'pdf'],
        ['category' => 'school_document', 'title' => 'UAT-P09B Expiring On The Thirty Day Boundary', 'from' => '-10 days', 'until' => '+30 days', 'file' => 'pdf'],
        ['category' => 'school_document', 'title' => 'UAT-P09B Valid Just Past The Window', 'from' => '-10 days', 'until' => '+31 days', 'file' => 'pdf'],
        ['category' => 'school_document', 'title' => 'UAT-P09B Expired Exactly Yesterday', 'from' => '-120 days', 'until' => '-1 day', 'file' => 'pdf'],
        ['category' => 'school_document', 'title' => 'UAT-P09B Expired Thirty Days Ago', 'from' => '-200 days', 'until' => '-30 days', 'file' => 'pdf'],
        ['category' => 'school_document', 'title' => 'UAT-P09B Upcoming Document', 'from' => '+30 days', 'until' => '+395 days', 'file' => 'pdf'],
        ['category' => 'school_document', 'title' => 'UAT-P09B Archived With Reason', 'from' => '-90 days', 'until' => '+90 days', 'file' => 'pdf', 'archive' => 'Synthetic retention review; the paper original was moved to the off-site store.'],
        ['category' => 'school_document', 'title' => 'UAT-P09B Archived But Still Downloadable', 'from' => '-60 days', 'until' => '+60 days', 'file' => 'pdf', 'archive' => 'Archived fixture that keeps its file so an authorized download still succeeds.'],
        ['category' => 'school_document', 'title' => 'UAT-P09B Missing File Probe', 'from' => '-45 days', 'until' => '+45 days', 'file' => 'pdf', 'probe' => 'missing'],
        ['category' => 'school_document', 'title' => 'UAT-P09B Corrupted File Probe', 'from' => '-45 days', 'until' => '+45 days', 'file' => 'pdf', 'probe' => 'corrupt'],
        ['category' => 'school_document', 'title' => 'UAT-P09B Image Preview Fixture', 'from' => '-20 days', 'until' => '+180 days', 'file' => 'png'],
        ['category' => 'student_record', 'title' => 'UAT-P09B Student Linked Access History', 'from' => '-20 days', 'until' => '+180 days', 'file' => 'pdf', 'subject' => ['student', (int) $student->id]],
        ['category' => 'school_document', 'title' => 'UAT-P09B Retention Candidate Past Ten Years', 'from' => null, 'until' => null, 'document_date' => '-11 years', 'file' => 'pdf'],
        ['category' => 'leave', 'title' => 'UAT-P09B Retention Candidate Past Two Years', 'from' => null, 'until' => null, 'document_date' => '-3 years', 'file' => 'pdf'],
    ];

    $created = 0;
    $reused = 0;
    $documentIds = [];

    foreach ($fixtures as $index => $fixture) {
        $number = $index + 1;
        $documentNumber = sprintf('P09B/ARCHIVE/%03d', $number);

        $existing = App\Models\ArchiveDocument::query()->where('document_number', $documentNumber)->first();
        if ($existing) {
            $reused++;
            $documentIds[$number] = (int) $existing->id;

            continue;
        }

        $payload = [
            'category' => $fixture['category'],
            'title' => $fixture['title'],
            'document_number' => $documentNumber,
            'document_date' => ($fixture['document_date'] ?? null) === null
                ? $today->copy()->subDays($number)->format('Y-m-d')
                : $today->copy()->modify($fixture['document_date'])->format('Y-m-d'),
            'valid_from' => ($fixture['from'] ?? null) === null
                ? null
                : $today->copy()->modify($fixture['from'])->format('Y-m-d'),
            'valid_until' => ($fixture['until'] ?? null) === null
                ? null
                : $today->copy()->modify($fixture['until'])->format('Y-m-d'),
            'description' => $number % 2 === 0
                ? sprintf('Synthetic 09b fixture %02d used only for the isolated archive review.', $number)
                : null,
        ];

        if (isset($fixture['subject'])) {
            $payload['subject_type'] = $fixture['subject'][0];
            $payload['subject_id'] = $fixture['subject'][1];
        }

        $file = $fixture['file'] === 'png'
            ? phase09Upload('UAT-P09B-Document-'.$number.'.png', $samplePng)
            : phase09Upload('UAT-P09B-Document-'.$number.'.pdf', phase09Pdf(9000 + $number));
        $document = $archiveService->create($payload, $file, $operator);
        @unlink($file->getRealPath());
        $created++;
        $documentIds[$number] = (int) $document['id'];
    }

    // Lifecycle: arsipkan dua dokumen dengan alasan (idempoten; tidak mengarsipkan massal).
    $archivedApplied = 0;
    foreach ($fixtures as $index => $fixture) {
        if (! isset($fixture['archive'])) {
            continue;
        }
        $document = App\Models\ArchiveDocument::find($documentIds[$index + 1]);
        if ($document && $document->archived_at === null) {
            $archiveService->archive((int) $document->id, $fixture['archive'], (int) $document->version, $archiver);
            $archivedApplied++;
        }
    }

    // Probe file: dijalankan ulang agar tetap destruktif tanpa menambah baris.
    $disk = Illuminate\Support\Facades\Storage::disk((string) config('archives.attachment.disk'));
    $probes = [];
    foreach ($fixtures as $index => $fixture) {
        if (! isset($fixture['probe'])) {
            continue;
        }
        $number = $index + 1;
        $document = App\Models\ArchiveDocument::find($documentIds[$number]);
        $version = App\Models\ArchiveFileVersion::query()
            ->where('archive_document_id', $document->id)
            ->where('version_number', (int) $document->current_version)
            ->firstOrFail();

        if ($fixture['probe'] === 'missing') {
            if ($disk->exists($version->path)) {
                $disk->delete($version->path);
            }
            $probes[] = sprintf('P09B/ARCHIVE/%03d -> 404 (document %d, version v%d)', $number, $document->id, $version->version_number);

            continue;
        }

        $contents = $disk->get($version->path);
        if ($contents !== null && hash_equals((string) $version->checksum_sha256, hash('sha256', $contents))) {
            $disk->put($version->path, $contents.'CORRUPTED');
        }
        $probes[] = sprintf('P09B/ARCHIVE/%03d -> 500 (document %d, version v%d)', $number, $document->id, $version->version_number);
    }

    // Riwayat akses sintetis untuk dua aktor (append-only; diisi sekali saja).
    $request = Illuminate\Http\Request::create('http://127.0.0.1:18027/api/archives', 'GET', [], [], [], [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'UAT-P09B synthetic access fixture',
    ]);
    /** @var App\Services\Archives\ArchiveAccessService $accessService */
    $accessService = $app->make(App\Services\Archives\ArchiveAccessService::class);
    $logFixtures = [
        [12, App\Models\ArchiveAccessLog::ACTION_PREVIEW, $archiver],
        [12, App\Models\ArchiveAccessLog::ACTION_DOWNLOAD, $auditor],
        [13, App\Models\ArchiveAccessLog::ACTION_DOWNLOAD, $archiver],
        [13, App\Models\ArchiveAccessLog::ACTION_PREVIEW, $auditor],
        [13, App\Models\ArchiveAccessLog::ACTION_PREVIEW, $archiver],
    ];

    $logsCreated = 0;
    foreach ($logFixtures as [$fixtureNumber, $action, $actor]) {
        $documentId = $documentIds[$fixtureNumber];
        $alreadyLogged = App\Models\ArchiveAccessLog::query()
            ->where('archive_document_id', $documentId)
            ->where('action', $action)
            ->where('user_id', $actor->id)
            ->exists();
        if ($alreadyLogged) {
            continue;
        }
        $document = App\Models\ArchiveDocument::findOrFail($documentId);
        $version = App\Models\ArchiveFileVersion::query()
            ->where('archive_document_id', $documentId)
            ->where('version_number', (int) $document->current_version)
            ->firstOrFail();
        $accessService->record($document, $version, $action, $actor, $request);
        $logsCreated++;
    }

    $byValidity = App\Models\ArchiveDocument::query()->get()
        ->map(fn (App\Models\ArchiveDocument $document) => $document->validityStatus())
        ->countBy();
    $byStatus = App\Models\ArchiveDocument::query()->get()
        ->map(fn (App\Models\ArchiveDocument $document) => $document->status())
        ->countBy();

    fwrite(STDOUT, '09a baseline preserved: '.$legacyCount.' documents with number P09/ARCHIVE/*; total archive documents now '
        .App\Models\ArchiveDocument::count().' ('.$created.' created, '.$reused.' reused).'."\n");
    fwrite(STDOUT, 'Version files: '.App\Models\ArchiveFileVersion::count().' total.'."\n");
    fwrite(STDOUT, 'Archived transitions applied this run: '.$archivedApplied.'; by status: '
        .$byStatus->map(fn ($count, $key) => $key.' '.$count)->implode(', ').".\n");
    fwrite(STDOUT, 'By validity: '.$byValidity->map(fn ($count, $key) => $key.' '.$count)->implode(', ').".\n");
    fwrite(STDOUT, 'Access log rows: '.App\Models\ArchiveAccessLog::count().' total ('.$logsCreated.' created this run) for uat.operator and uat.admin.'."\n");
    fwrite(STDOUT, 'File probes: '.($probes === [] ? 'none' : implode('; ', $probes)).".\n");
    fwrite(STDOUT, 'Dry-run review: php scripts/phase09-uat.php retention-dry-run (isolated runtime; read-only).'."\n");
    fwrite(STDOUT, '09b fixtures ready. Start the API on 127.0.0.1:18027 and the SPA on 127.0.0.1:14027 for the browser review.'."\n");
    exit(0);
}

if ($command === 'status') {
    $counts = [
        'users' => App\Models\User::withTrashed()->count(),
        'students' => App\Models\Student::withTrashed()->count(),
        'employees' => App\Models\Employee::withTrashed()->count(),
        'letters' => App\Models\Letter::count(),
        'leave_requests' => App\Models\LeaveRequest::count(),
        'archive_documents' => App\Models\ArchiveDocument::count(),
        'archive_versions' => App\Models\ArchiveFileVersion::count(),
    ];
    $files = Illuminate\Support\Facades\Storage::disk((string) config('archives.attachment.disk'))
        ->allFiles((string) config('archives.attachment.path'));

    fwrite(STDOUT, 'Environment: '.$app->environment().'; database: '.config('database.connections.sqlite.database')."\n");
    foreach ($counts as $key => $count) {
        fwrite(STDOUT, str_replace('_', ' ', ucfirst($key)).': '.$count."\n");
    }
    fwrite(STDOUT, 'Private archive files: '.count($files)."\n");
    exit(0);
}

if ($command === 'retention-dry-run') {
    $exit = $kernel->call('app:archives-retention', ['--dry-run' => true]);
    fwrite(STDOUT, $kernel->output());
    exit($exit);
}

fwrite(STDERR, "Unknown command. Use init, prepare, prepare-archives, prepare-09b, retention-dry-run, status, or start the HTTP server with this script.\n");
exit(2);

function phase09Pdf(int $seed): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%% UAT-P09 synthetic archive file {$seed}\n%%EOF\n";
}

function phase09Upload(string $name, string $content): Illuminate\Http\UploadedFile
{
    $path = sys_get_temp_dir().'/'.uniqid('p09-', true).'-'.$name;
    file_put_contents($path, $content);

    return new Illuminate\Http\UploadedFile($path, $name, null, null, true);
}
