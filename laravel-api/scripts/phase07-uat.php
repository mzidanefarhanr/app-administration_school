<?php

declare(strict_types=1);

$base = str_replace('\\', '/', dirname(__DIR__));
$runtime = dirname($base).'/.uat-phase07';
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
    foreach (['', '/storage/app', '/storage/framework/cache/data', '/storage/framework/sessions', '/storage/framework/views', '/storage/logs', '/tmp', '/manual-files'] as $directory) {
        if (! is_dir($runtime.$directory) && ! mkdir($runtime.$directory, 0700, true)) {
            throw new RuntimeException('Unable to create isolated runtime directories.');
        }
    }
    touch($runtime.'/database.sqlite');
    $key = 'base64:'.base64_encode(random_bytes(32));
    $password = 'uat-'.bin2hex(random_bytes(12));
    file_put_contents($runtime.'/account-password.txt', $password);
    $env = [
        'APP_NAME' => 'School UAT Phase 07', 'APP_ENV' => 'uat', 'APP_KEY' => $key, 'APP_DEBUG' => 'false',
        'APP_URL' => 'http://127.0.0.1:18025', 'FRONTEND_URL' => 'http://127.0.0.1:14025',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $runtime.'/database.sqlite', 'DB_FOREIGN_KEYS' => 'true',
        'CACHE_DRIVER' => 'file', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'school_phase07_session',
        'SESSION_DOMAIN' => '127.0.0.1', 'SANCTUM_STATEFUL_DOMAINS' => '127.0.0.1:14025',
        'FILESYSTEM_DISK' => 'local', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'single',
        'LETTERS_NUMBER_SCHOOL_CODE' => '20512345',
    ];
    file_put_contents($runtime.'/.env', implode("\n", array_map(fn ($key, $value) => $key.'="'.$value.'"', array_keys($env), $env))."\n");
    fwrite(STDOUT, "Isolated SQLite UAT (phase 07) initialized. Credentials remain in .uat-phase07/account-password.txt (ignored).\n");
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
    || config('database.connections.sqlite.url') || config('app.url') !== 'http://127.0.0.1:18025'
    || config('session.cookie') !== 'school_phase07_session') {
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
    fwrite(STDOUT, 'Letter categories: '.implode(', ', config('letters.categories'))."\n");
    fwrite(STDOUT, 'Prepare complete. Run prepare-letters to populate the synthetic agenda.'."\n");
    exit(0);
}

if ($command === 'prepare-letters') {
    $exit = $kernel->call('migrate', ['--force' => true]);
    fwrite(STDOUT, $kernel->output());
    if ($exit !== 0) {
        exit($exit);
    }

    $operator = App\Models\User::where('username', 'uat.operator')->first();
    $admin = App\Models\User::where('username', 'uat.admin')->first();
    if (! $operator || ! $admin) {
        throw new RuntimeException('Run prepare first (UAT users are required).');
    }

    /** @var App\Services\Letters\LetterService $letters */
    $letters = $app->make(App\Services\Letters\LetterService::class);

    $topics = [
        'Coordination Meeting Invitation', 'School Operational Funding Notice', 'Student Data Verification Request',
        'Teacher Training Announcement', 'Partnership Proposal', 'National Assessment Schedule',
        'Facility Maintenance Notice', 'Scholarship Information', 'Curriculum Implementation Update',
        'Extracurricular Competition Invitation',
    ];
    $senders = [
        'Dinas Pendidikan Sintetis', 'Yayasan Pendidikan UAT', 'Sekolah Mitra Sintetis Nusantara',
        'Komite Sekolah UAT', 'Balai Teknologi Pendidikan', 'Kantor Wilayah Pendidikan',
    ];
    $categories = config('letters.categories');
    $voidIndexes = [12, 24, 37, 49, 62, 74, 87, 99, 112, 124];
    $targetAttachments = fn (int $i): int => $i === 1 ? 5 : ($i <= 10 ? 2 : ($i <= 50 ? 1 : 0));
    $startingDate = Illuminate\Support\Carbon::create(2026, 1, 5);

    $created = 0;
    $reused = 0;
    $attachmentCount = 0;
    $updated = 0;
    $voided = 0;

    for ($i = 1; $i <= 125; $i++) {
        $subject = sprintf('UAT-P07 Incoming Letter %03d — %s', $i, $topics[($i - 1) % count($topics)]);
        $existing = App\Models\Letter::query()
            ->where('direction', App\Models\Letter::DIRECTION_INCOMING)
            ->where('subject', $subject)
            ->first();

        if ($existing) {
            $reused++;
        } else {
            $received = $startingDate->copy()->addDays(($i - 1) % 295);
            $sourceNumber = null;
            if ($i % 7 !== 0) {
                $sourceNumber = $i % 11 === 0
                    ? 'UAT-P07/DUP/2026/001'
                    : 'UAT-P07/'.sprintf('%04d', $i).'/2026';
            }

            $data = $letters->create([
                'received_date' => $received->format('Y-m-d'),
                'letter_date' => $received->copy()->subDays($i % 5)->format('Y-m-d'),
                'source_number' => $sourceNumber,
                'sender' => $senders[($i - 1) % count($senders)].' '.sprintf('%02d', (($i - 1) % 20) + 1),
                'subject' => $subject,
                'category' => $categories[($i - 1) % count($categories)],
                'notes' => $i % 10 === 0 ? 'Synthetic notes for review scenario '.sprintf('%03d', $i).'.' : null,
            ], $operator);

            $created++;
        }

        $letter = App\Models\Letter::query()
            ->where('direction', App\Models\Letter::DIRECTION_INCOMING)
            ->where('subject', $subject)
            ->firstOrFail();

        $target = $targetAttachments($i);
        $current = (int) $letter->attachments()->count();
        for ($n = $current + 1; $n <= $target; $n++) {
            $isPng = $n % 2 === 0;
            $name = sprintf('UAT-P07-L%03d-A%d.%s', $i, $n, $isPng ? 'png' : 'pdf');
            $path = $runtime.'/tmp/'.$name;
            if ($isPng) {
                file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
            } else {
                $label = 'Synthetic UAT phase 07 attachment '.$i.'-'.$n;
                file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%% ".$label."\n%%EOF\n");
            }
            $upload = new Illuminate\Http\UploadedFile($path, $name, null, null, true);
            $letters->addAttachment($letter->id, $upload, $operator);
            @unlink($path);
            $attachmentCount++;
        }

        $fresh = $letter->fresh();
        if ($i % 9 === 0 && ! in_array($i, $voidIndexes, true) && $fresh && ! $fresh->isVoided()) {
            $letters->update($letter->id, [
                'notes' => 'UAT-P07 updated notes for letter '.sprintf('%03d', $i).'.',
            ], (int) $fresh->version, $operator);
            $updated++;
        }

        if (in_array($i, $voidIndexes, true)) {
            $fresh = $letter->fresh();
            if ($fresh && ! $fresh->isVoided()) {
                $letters->void($letter->id, 'UAT-P07 synthetic void reason for letter '.sprintf('%03d', $i).'.', (int) $fresh->version, $admin);
                $voided++;
            }
        }
    }

    $samplePng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    file_put_contents($runtime.'/manual-files/valid.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%% synthetic UAT upload\n%%EOF\n");
    file_put_contents($runtime.'/manual-files/valid.png', $samplePng);
    file_put_contents($runtime.'/manual-files/oversize.pdf', "%PDF-1.4\n".str_repeat('A', 11 * 1024 * 1024)."\n%%EOF\n");
    file_put_contents($runtime.'/manual-files/shell.php', '<?php echo "synthetic";');
    file_put_contents($runtime.'/manual-files/payload.exe', 'MZ synthetic UAT payload');
    file_put_contents($runtime.'/manual-files/page.html', '<html><body>synthetic</body></html>');
    file_put_contents($runtime.'/manual-files/vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    file_put_contents($runtime.'/manual-files/report.pdf.php', "%PDF-1.4\nsynthetic double extension\n%%EOF\n");

    $missingSource = App\Models\Letter::where('direction', 'incoming')->whereNull('source_number')->count();
    $duplicateSource = App\Models\Letter::where('direction', 'incoming')
        ->where('source_number', 'UAT-P07/DUP/2026/001')->count();

    fwrite(STDOUT, 'Letters: '.App\Models\Letter::where('direction', 'incoming')->count()
        ." total ({$created} created, {$reused} reused), {$updated} updated, {$voided} newly voided.\n");
    fwrite(STDOUT, 'Attachments: '.App\Models\LetterAttachment::count()." rows ({$attachmentCount} added this run).\n");
    fwrite(STDOUT, 'Source numbers: '.$missingSource.' missing, '.$duplicateSource.' sharing UAT-P07/DUP/2026/001.'."\n");
    fwrite(STDOUT, 'Dangerous/invalid upload samples: '.$runtime.'/manual-files (ignored runtime).'."\n");
    exit(0);
}

if ($command === 'prepare-outgoing') {
    $exit = $kernel->call('migrate', ['--force' => true]);
    fwrite(STDOUT, $kernel->output());
    if ($exit !== 0) {
        exit($exit);
    }

    $operator = App\Models\User::where('username', 'uat.operator')->first();
    $principal = App\Models\User::where('username', 'uat.kepsek')->first();
    if (! $operator || ! $principal) {
        throw new RuntimeException('Run prepare first (UAT users are required).');
    }
    if (trim((string) config('letters.numbering.school_code')) === '') {
        throw new RuntimeException('Set LETTERS_NUMBER_SCHOOL_CODE in .uat-phase07/.env before preparing outgoing letters.');
    }

    /** @var App\Services\Letters\OutgoingLetterService $outgoing */
    $outgoing = $app->make(App\Services\Letters\OutgoingLetterService::class);

    $topics = [
        'Official Cooperation Reply', 'Student Exchange Confirmation', 'Budget Realization Report',
        'Teacher Assignment Letter', 'Facility Usage Request', 'National Assessment Readiness',
        'Parent Coordination Notice', 'Scholarship Recommendation', 'Curriculum Report Submission',
        'Competition Registration Follow-up',
    ];
    $recipients = [
        'Dinas Pendidikan Sintetis', 'Yayasan Pendidikan UAT', 'Sekolah Mitra Sintetis Nusantara',
        'Komite Sekolah UAT', 'Balai Teknologi Pendidikan', 'Kantor Wilayah Pendidikan',
    ];
    $categories = config('letters.categories');
    $startingDate = Illuminate\Support\Carbon::create(2026, 1, 5);

    $created = 0;
    $advanced = 0;
    $attachmentsAdded = 0;

    for ($i = 1; $i <= 45; $i++) {
        $subject = sprintf('UAT-P07B Outgoing Letter %03d — %s', $i, $topics[($i - 1) % count($topics)]);
        $letter = App\Models\Letter::query()
            ->where('direction', App\Models\Letter::DIRECTION_OUTGOING)
            ->where('subject', $subject)
            ->first();

        if (! $letter) {
            $date = $startingDate->copy()->addDays(($i - 1) % 295);
            $outgoing->create([
                'received_date' => $date->format('Y-m-d'),
                'letter_date' => $date->copy()->subDays($i % 4)->format('Y-m-d'),
                'sender' => 'Sekolah UAT Sintetis',
                'recipient' => $recipients[($i - 1) % count($recipients)].' '.sprintf('%02d', (($i - 1) % 12) + 1),
                'subject' => $subject,
                'category' => $categories[($i - 1) % count($categories)],
                'notes' => $i % 8 === 0 ? 'Synthetic outgoing notes for review scenario '.sprintf('%03d', $i).'.' : null,
            ], $operator);
            $created++;

            $letter = App\Models\Letter::query()
                ->where('direction', App\Models\Letter::DIRECTION_OUTGOING)
                ->where('subject', $subject)
                ->firstOrFail();
        }

        $mod = $i % 9;
        $target = match (true) {
            in_array($mod, [1, 2, 3], true) => App\Models\Letter::WORKFLOW_DRAFT,
            in_array($mod, [4, 5], true) => App\Models\Letter::WORKFLOW_REVIEW,
            in_array($mod, [6, 7], true) => App\Models\Letter::WORKFLOW_APPROVED,
            $mod === 8 => App\Models\Letter::WORKFLOW_ISSUED,
            default => App\Models\Letter::WORKFLOW_CANCELLED,
        };

        for ($step = 0; $step < 6; $step++) {
            $letter = $letter->fresh();
            if (! $letter || $letter->workflow_status === $target) {
                break;
            }

            $status = (string) $letter->workflow_status;
            $version = (int) $letter->version;
            $reason = 'UAT-P07B synthetic cancellation for outgoing letter '.sprintf('%03d', $i).'.';

            if ($target === App\Models\Letter::WORKFLOW_CANCELLED) {
                if (in_array($status, [App\Models\Letter::WORKFLOW_DRAFT, App\Models\Letter::WORKFLOW_REVIEW], true)) {
                    $outgoing->cancel($letter->id, $reason, $version, $operator);
                } elseif (in_array($status, [App\Models\Letter::WORKFLOW_APPROVED, App\Models\Letter::WORKFLOW_ISSUED], true)) {
                    $outgoing->cancel($letter->id, $reason, $version, $principal);
                } else {
                    break;
                }
            } elseif ($target === App\Models\Letter::WORKFLOW_ISSUED) {
                if ($status === App\Models\Letter::WORKFLOW_DRAFT) {
                    $outgoing->submit($letter->id, $version, $operator);
                } elseif ($status === App\Models\Letter::WORKFLOW_REVIEW) {
                    $outgoing->approve($letter->id, 'UAT-P07B synthetic approval note.', $version, $principal);
                } elseif ($status === App\Models\Letter::WORKFLOW_APPROVED) {
                    $outgoing->issue($letter->id, $version, $principal);
                } else {
                    break;
                }
            } elseif ($target === App\Models\Letter::WORKFLOW_APPROVED) {
                if ($status === App\Models\Letter::WORKFLOW_DRAFT) {
                    $outgoing->submit($letter->id, $version, $operator);
                } elseif ($status === App\Models\Letter::WORKFLOW_REVIEW) {
                    $outgoing->approve($letter->id, 'UAT-P07B synthetic approval note.', $version, $principal);
                } else {
                    break;
                }
            } elseif ($target === App\Models\Letter::WORKFLOW_REVIEW) {
                if ($status === App\Models\Letter::WORKFLOW_DRAFT) {
                    $outgoing->submit($letter->id, $version, $operator);
                } else {
                    break;
                }
            } else {
                break;
            }

            $advanced++;
        }

        if ($target === App\Models\Letter::WORKFLOW_DRAFT && $i % 9 === 1) {
            $letter = $letter->fresh();
            $name = sprintf('UAT-P07B-OUT-%03d-A1.pdf', $i);
            if ($letter && $letter->workflow_status === App\Models\Letter::WORKFLOW_DRAFT
                && ! $letter->attachments()->where('original_name', $name)->exists()) {
                $path = $runtime.'/tmp/'.$name;
                file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%% ".$name."\n%%EOF\n");
                $upload = new Illuminate\Http\UploadedFile($path, $name, null, null, true);
                $outgoing->addAttachment($letter->id, $upload, $operator);
                @unlink($path);
                $attachmentsAdded++;
            }
        }
    }

    $outgoingQuery = App\Models\Letter::where('direction', App\Models\Letter::DIRECTION_OUTGOING);
    $byStatus = (clone $outgoingQuery)->pluck('workflow_status')->countBy();
    $counter = App\Models\LetterNumberCounter::query()
        ->where('scope', 'outgoing')
        ->where('period', Illuminate\Support\Carbon::now('Asia/Jakarta')->format('Y'))
        ->first();

    fwrite(STDOUT, 'Outgoing letters: '.(clone $outgoingQuery)->count()
        ." total ({$created} created, {$advanced} transitions), {$attachmentsAdded} attachments added.\n");
    fwrite(STDOUT, 'By workflow: draft '.($byStatus['draft'] ?? 0).', review '.($byStatus['review'] ?? 0)
        .', approved '.($byStatus['approved'] ?? 0).', issued '.($byStatus['issued'] ?? 0)
        .', cancelled '.($byStatus['cancelled'] ?? 0).".\n");
    fwrite(STDOUT, 'Official numbers: '.(clone $outgoingQuery)->whereNotNull('official_number')->count()
        .' issued, '.(clone $outgoingQuery)->whereNotNull('official_number')->distinct()->count('official_number')
        .' unique; counter '.$counter?->period.' = '.($counter?->last_number ?? 0).".\n");
    exit(0);
}

if ($command === 'prepare-dispositions') {
    $exit = $kernel->call('migrate', ['--force' => true]);
    fwrite(STDOUT, $kernel->output());
    if ($exit !== 0) {
        exit($exit);
    }

    $operator = App\Models\User::where('username', 'uat.operator')->first();
    if (! $operator) {
        throw new RuntimeException('Run prepare first (UAT users are required).');
    }

    $province = App\Models\Province::firstOrCreate(['name' => 'DKI Jakarta UAT']);
    $regency = App\Models\Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Utara UAT']);
    $district = App\Models\District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Koja UAT']);
    $gender = App\Models\Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $religion = App\Models\Religion::firstOrCreate(['name' => 'Islam']);
    $maritalStatus = App\Models\MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);
    $status = App\Models\EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap Yayasan'], ['abbreviation' => 'PTY']);
    $teacherType = App\Models\EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $staffType = App\Models\EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
    $employment = App\Models\Employment::firstOrCreate(['name' => 'Tetap Yayasan'], ['description' => 'Tetap']);
    $subject = App\Models\Subject::firstOrCreate(['name' => 'Matematika'], ['description' => 'Matematika']);

    $employeeBase = [
        'employee_status_id' => $status->id,
        'employment_id' => $employment->id,
        'subject_id' => $subject->id,
        'religion_id' => $religion->id,
        'district_id' => $district->id,
        'marital_status_id' => $maritalStatus->id,
        'gender_id' => $gender->id,
        'tmt_employee' => '2020-01-01',
    ];

    $activeEmployees = [];
    $createdEmployees = 0;
    for ($i = 1; $i <= 12; $i++) {
        $name = sprintf('UAT P07B Employee %02d', $i);
        $employee = App\Models\Employee::where('name', $name)->first();
        if (! $employee) {
            $employee = App\Models\Employee::create(array_merge($employeeBase, [
                'name' => $name,
                'employee_type_id' => $i % 2 === 0 ? $staffType->id : $teacherType->id,
            ]));
            $createdEmployees++;
        }
        $activeEmployees[] = $employee;
    }
    for ($i = 1; $i <= 2; $i++) {
        $name = sprintf('UAT P07B Exited %02d', $i);
        if (! App\Models\Employee::where('name', $name)->exists()) {
            App\Models\Employee::create(array_merge($employeeBase, [
                'name' => $name,
                'employee_type_id' => $teacherType->id,
                'exit_date' => '2026-10-31',
            ]));
            $createdEmployees++;
        }
    }

    /** @var App\Services\Letters\LetterDispositionService $dispositions */
    $dispositions = $app->make(App\Services\Letters\LetterDispositionService::class);

    $letters = App\Models\Letter::query()
        ->where('direction', App\Models\Letter::DIRECTION_INCOMING)
        ->where('status', App\Models\Letter::STATUS_ACTIVE)
        ->orderBy('id')
        ->take(24)
        ->get();
    if ($letters->count() < 12) {
        throw new RuntimeException('Run prepare-letters first (active incoming letters are required).');
    }

    $today = Illuminate\Support\Carbon::today('Asia/Jakarta');
    $created = 0;
    $reused = 0;

    for ($n = 1; $n <= 32; $n++) {
        $letter = $letters->get(($n - 1) % $letters->count());
        $employee = $activeEmployees[($n - 1) % count($activeEmployees)];
        $existing = App\Models\LetterDisposition::query()
            ->where('letter_id', $letter->id)
            ->where('recipient_employee_id', $employee->id)
            ->first();

        if ($existing) {
            $reused++;

            continue;
        }

        $mod = $n % 4;
        $overdue = in_array($mod, [1, 2], true) && $n % 3 === 0;
        $due = $overdue ? $today->copy()->subDays(5) : $today->copy()->addDays(7 + ($n % 10));

        $dispositions->create($letter->id, [
            'recipients' => [$employee->id],
            'due_date' => $due->format('Y-m-d'),
            'note' => sprintf('UAT-P07B synthetic disposition %02d for follow-up review.', $n),
        ], $operator);
        $created++;

        $row = App\Models\LetterDisposition::query()
            ->where('letter_id', $letter->id)
            ->where('recipient_employee_id', $employee->id)
            ->firstOrFail();

        if ($mod === 2) {
            $dispositions->update($letter->id, $row->id, ['status' => App\Models\LetterDisposition::STATUS_IN_PROGRESS], (int) $row->version, $operator);
        } elseif ($mod === 3) {
            $dispositions->update($letter->id, $row->id, ['status' => App\Models\LetterDisposition::STATUS_IN_PROGRESS], (int) $row->version, $operator);
            $row = $row->fresh();
            $dispositions->update($letter->id, $row->id, [
                'status' => App\Models\LetterDisposition::STATUS_COMPLETED,
                'follow_up_note' => 'UAT-P07B synthetic follow-up outcome recorded for fixture '.sprintf('%02d', $n).'.',
            ], (int) $row->version, $operator);
        } elseif ($mod === 0) {
            $dispositions->cancel(
                $letter->id,
                $row->id,
                'UAT-P07B synthetic cancellation reason for fixture '.sprintf('%02d', $n).'.',
                (int) $row->version,
                $operator,
            );
        }
    }

    $multiCreated = 0;
    foreach ([22, 23] as $index) {
        $letter = $letters->get($index);
        if (! $letter) {
            continue;
        }

        $recipientIds = [$activeEmployees[0]->id, $activeEmployees[1]->id, $activeEmployees[2]->id];
        $exists = App\Models\LetterDisposition::query()
            ->where('letter_id', $letter->id)
            ->whereIn('recipient_employee_id', $recipientIds)
            ->exists();

        if ($exists) {
            continue;
        }

        $dispositions->create($letter->id, [
            'recipients' => $recipientIds,
            'due_date' => $today->copy()->addDays(14)->format('Y-m-d'),
            'note' => 'UAT-P07B multi-recipient disposition for committee follow-up.',
        ], $operator);
        $multiCreated += count($recipientIds);
    }
    $created += $multiCreated;

    $dispoQuery = App\Models\LetterDisposition::query();
    $byStatus = (clone $dispoQuery)->pluck('status')->countBy();
    $overdueCount = (clone $dispoQuery)
        ->whereIn('status', [App\Models\LetterDisposition::STATUS_PENDING, App\Models\LetterDisposition::STATUS_IN_PROGRESS])
        ->whereDate('due_date', '<', $today->format('Y-m-d'))
        ->count();

    fwrite(STDOUT, 'Employees: '.App\Models\Employee::query()->count()
        .' total, '.App\Models\Employee::whereNull('exit_date')->count().' active ('
        .$createdEmployees." created this run).\n");
    fwrite(STDOUT, 'Dispositions: '.(clone $dispoQuery)->count()
        ." total ({$created} created, {$reused} reused); multi-recipient rows added {$multiCreated}.\n");
    fwrite(STDOUT, 'By status: pending '.($byStatus['pending'] ?? 0).', in_progress '.($byStatus['in_progress'] ?? 0)
        .', completed '.($byStatus['completed'] ?? 0).', cancelled '.($byStatus['cancelled'] ?? 0)
        ."; overdue {$overdueCount}.\n");
    exit(0);
}

if ($command === 'prepare-documents') {
    $exit = $kernel->call('migrate', ['--force' => true]);
    fwrite(STDOUT, $kernel->output());
    if ($exit !== 0) {
        exit($exit);
    }

    $operator = App\Models\User::where('username', 'uat.operator')->first();
    $principal = App\Models\User::where('username', 'uat.kepsek')->first();
    $admin = App\Models\User::where('username', 'uat.admin')->first();
    if (! $operator || ! $principal || ! $admin) {
        throw new RuntimeException('Run prepare first (UAT users are required).');
    }

    /** @var App\Services\Letters\LetterSettingsService $settingsService */
    $settingsService = $app->make(App\Services\Letters\LetterSettingsService::class);

    // 1. Konteks sekolah aktif (tahun ajaran 2026/2027) dan profil.
    $educationLevel = App\Models\EducationLevel::firstOrCreate(['name' => 'Sekolah Menengah Atas']);
    $province = App\Models\Province::firstOrCreate(['name' => 'DKI Jakarta UAT']);
    $regency = App\Models\Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Utara UAT']);
    $district = App\Models\District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Koja UAT']);

    $school = App\Models\EducationSchool::withTrashed()->firstOrNew(['npsn' => '20512345']);
    $school->fill([
        'name' => 'SMA Negeri 1 UAT Sintetis',
        'education_level_id' => $educationLevel->id,
        'status_education' => 'Negeri',
        'address' => 'Jl. Sintetis No. 1, Jakarta Utara UAT',
        'district_id' => $district->id,
    ]);
    $school->deleted_at = null;
    $school->save();

    $destination = App\Models\EducationSchool::withTrashed()->firstOrNew(['npsn' => '20599999']);
    $destination->fill([
        'name' => 'SMA Tujuan UAT Sintetis',
        'education_level_id' => $educationLevel->id,
        'status_education' => 'Swasta',
        'address' => 'Jl. Tujuan No. 2, Jakarta Utara UAT',
        'district_id' => $district->id,
    ]);
    $destination->deleted_at = null;
    $destination->save();

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
        throw new RuntimeException('The active school year is already set to another year; reset it before preparing document fixtures.');
    }
    $academic->active_school_year_id = $year->id;
    $academic->updated_by = $admin->id;
    $academic->save();

    $statusPrincipalId = App\Models\StatusUser::where('name', 'Aktif')->value('id');
    if ($statusPrincipalId === null) {
        throw new RuntimeException('The active principal status reference is missing.');
    }

    $profile = App\Models\SchoolProfile::withTrashed()->firstOrNew(['school_year_id' => $year->id]);
    $profile->fill([
        'education_school_npsn' => $school->npsn,
        'principal_id' => $principal->id,
        'status_principal_id' => $statusPrincipalId,
    ]);
    $profile->deleted_at = null;
    $profile->save();

    // 2. Kop/penandatangan sintetis dengan aset PNG privat.
    $syntheticPng = function (string $path, int $width, int $height, string $label): void {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, $width, $height, $transparent);
        imagealphablending($image, true);
        $ink = imagecolorallocate($image, 15, 23, 42);
        $font = 5;
        $x = max(4, (int) (($width - imagefontwidth($font) * strlen($label)) / 2));
        $y = (int) (($height - imagefontheight($font)) / 2);
        imagestring($image, $font, $x, $y, $label, $ink);
        imagepng($image, $path);
        imagedestroy($image);
    };
    $upload = function (string $path, string $name): Illuminate\Http\UploadedFile {
        return new Illuminate\Http\UploadedFile($path, $name, 'image/png', null, true);
    };

    $readiness = $settingsService->readiness();
    if (! $readiness['header']) {
        $settings = $settingsService->context()['settings'];
        $header = [
            'government_line' => 'PEMERINTAH PROVINSI UAT SINTETIS',
            'school_name' => 'SMA NEGERI 1 UAT SINTETIS',
            'address_line' => 'Jl. Sintetis No. 1, Jakarta Utara UAT',
            'contact_line' => '(021) 0000000 - uat@example.invalid',
            'place_of_issue' => 'Jakarta',
        ];
        if ($settings === null || ! $settings->logo_path) {
            $logoPath = $runtime.'/tmp/uat-p07c-logo.png';
            $syntheticPng($logoPath, 400, 160, 'UAT SYNTHETIC LOGO');
            $header['logo'] = $upload($logoPath, 'uat-p07c-logo.png');
        }
        $settingsService->updateHeader($header, (int) ($settings?->version ?? 0), $admin);
        @unlink($runtime.'/tmp/uat-p07c-logo.png');
    }

    $readiness = $settingsService->readiness();
    if (! $readiness['signatory'] || ! $readiness['signature']) {
        $settings = $settingsService->context()['settings'];
        $signatory = [
            'signatory_name' => 'UAT Kepala Sekolah',
            'signatory_title' => 'Kepala Sekolah',
            'signatory_identifier_label' => 'NIP',
            'signatory_identifier' => '190000000000000001',
        ];
        if ($settings === null || ! $settings->signature_path) {
            $signaturePath = $runtime.'/tmp/uat-p07c-signature.png';
            $syntheticPng($signaturePath, 360, 120, 'UAT SYNTHETIC SIGNATURE');
            $signatory['signature'] = $upload($signaturePath, 'uat-p07c-signature.png');
        }
        if ($settings === null || ! $settings->stamp_path) {
            $stampPath = $runtime.'/tmp/uat-p07c-stamp.png';
            $syntheticPng($stampPath, 200, 200, 'UAT SYNTHETIC STAMP');
            $signatory['stamp'] = $upload($stampPath, 'uat-p07c-stamp.png');
        }
        $settingsService->updateSignatory($signatory, (int) ($settings?->version ?? 0), $principal);
        @unlink($runtime.'/tmp/uat-p07c-signature.png');
        @unlink($runtime.'/tmp/uat-p07c-stamp.png');
    }

    // 3. 120 siswa sintetis: 100 anggota aktif + 20 subjek mutasi keluar.
    $schoolLevel = App\Models\SchoolLevel::firstOrCreate(['name' => 'UAT Level']);
    $classNames = ['X-A', 'X-B', 'XI-A', 'XI-B'];
    $classes = [];
    foreach ($classNames as $suffix) {
        $classes[$suffix] = App\Models\SchoolRombel::firstOrCreate(
            ['school_year_id' => $year->id, 'name' => 'UAT-P07C '.$suffix],
            ['school_level_id' => $schoolLevel->id]
        );
    }
    $statusActive = App\Models\StudentStatus::firstOrCreate(['name' => 'Aktif']);
    $statusMutationOut = App\Models\StudentStatus::firstOrCreate(['name' => 'Mutasi Keluar']);
    if ((int) $statusMutationOut->id !== App\Services\Academic\StudentMutationService::STATUS_MUTATION_OUT) {
        throw new RuntimeException('The mutation-out student status does not match the canonical id 2 baseline.');
    }
    $entryNew = App\Models\StudentEntry::firstOrCreate(['name' => 'Siswa Baru']);
    $male = App\Models\Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $female = App\Models\Gender::firstOrCreate(['name' => 'Perempuan']);

    $students = [];
    $memberships = [];
    $studentsCreated = 0;
    for ($i = 1; $i <= 120; $i++) {
        $nik = '07'.str_pad((string) $i, 14, '0', STR_PAD_LEFT);
        $nis = '0710'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $nisn = '0071'.str_pad((string) $i, 6, '0', STR_PAD_LEFT);

        $existing = App\Models\Student::withTrashed()->where('nis', $nis)->first();
        if (! $existing) {
            $studentsCreated++;
        }

        $student = App\Models\Student::withTrashed()->updateOrCreate(['nis' => $nis], [
            'name' => 'UAT-P07C Student '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'nik' => $nik,
            'nisn' => $nisn,
            'birthplace' => 'Synthetic City',
            'birthdate' => '2010-01-01',
            'gender_id' => $i % 2 === 0 ? $female->id : $male->id,
            'deleted_at' => null,
        ]);

        $classKey = $classNames[(int) floor(($i - 1) / 30)];
        $membership = App\Models\StudentRombel::withTrashed()->firstOrNew([
            'student_id' => $student->id,
            'school_rombel' => $classes[$classKey]->id,
        ]);
        $membership->student_nik = $student->nik;
        if (! $membership->exists || ($membership->latest_student_exit_date === null
            && (int) $membership->student_status_id !== App\Services\Academic\StudentMutationService::STATUS_MUTATION_OUT)) {
            $membership->student_status_id = $statusActive->id;
            $membership->student_entry_id = $entryNew->id;
            $membership->latest_student_entry_date = '2026-07-01';
        }
        $membership->archived_at = null;
        $membership->deleted_at = null;
        $membership->save();

        $students[$i] = $student;
        $memberships[$i] = $membership;
    }

    // 4. 20 mutasi keluar melalui service agar status membership konsisten.
    /** @var App\Services\Academic\StudentMutationService $mutations */
    $mutations = $app->make(App\Services\Academic\StudentMutationService::class);
    $mutationsCreated = 0;
    for ($i = 101; $i <= 120; $i++) {
        $activeOut = App\Models\StudentMutation::query()
            ->where('student_rombel_id', $memberships[$i]->id)
            ->where('type', App\Models\StudentMutation::TYPE_OUT)
            ->where('status', App\Models\StudentMutation::STATUS_ACTIVE)
            ->exists();

        if ($activeOut) {
            continue;
        }

        $student = $students[$i]->fresh();
        $membership = $memberships[$i]->fresh();
        $mutations->mutationOut($student->id, [
            'student_rombel_id' => $membership->id,
            'effective_date' => '2026-10-'.str_pad((string) (($i - 101) % 28 + 1), 2, '0', STR_PAD_LEFT),
            'school_npsn' => $destination->npsn,
            'reason' => 'UAT-P07C synthetic mutation-out for document fixture '.sprintf('%03d', $i).'.',
            'expected_student_version' => (int) $student->version,
            'expected_membership_version' => (int) $membership->version,
        ], $operator);
        $mutationsCreated++;
    }

    $mutationRows = App\Models\StudentMutation::query()
        ->whereIn('student_id', collect(range(101, 120))->map(fn (int $i) => $students[$i]->id))
        ->where('type', App\Models\StudentMutation::TYPE_OUT)
        ->where('status', App\Models\StudentMutation::STATUS_ACTIVE)
        ->orderBy('id')
        ->get();

    // 5. Dua puluh surat bertipe dengan lintas status dan subjek.
    /** @var App\Services\Letters\OutgoingLetterService $outgoing */
    $outgoing = $app->make(App\Services\Letters\OutgoingLetterService::class);

    $activeStudents = [];
    for ($i = 1; $i <= 100; $i++) {
        $activeStudents[] = $students[$i];
    }

    $activeEmployees = App\Models\Employee::query()
        ->whereNull('exit_date')
        ->orderBy('id')
        ->take(4)
        ->get()
        ->all();
    if (count($activeEmployees) < 4 || $mutationRows->count() < 4) {
        throw new RuntimeException('Run prepare-outgoing and prepare-dispositions first (employees and mutations are required).');
    }

    $longDuty = trim(str_repeat('Melaksanakan tugas sintetis pengelolaan administrasi sekolah untuk kebutuhan pengujian dokumen. ', 14));

    $specs = [];
    $studentTargets = ['draft', 'draft', 'draft', 'draft', 'review', 'review', 'approved', 'approved', 'issued', 'issued', 'issued', 'cancelled'];
    foreach ($studentTargets as $index => $target) {
        $specs[] = [
            'type' => 'student_active',
            'subject_id' => (int) $activeStudents[$index]->id,
            'fields' => ['purpose' => 'UAT-P07C synthetic purpose for active student certificate '.sprintf('%03d', $index + 1).'.'],
            'target' => $target,
            'cancel_after_issue' => $target === 'cancelled',
        ];
    }

    foreach (['draft', 'approved', 'issued', 'cancelled'] as $index => $target) {
        $specs[] = [
            'type' => 'student_mutation',
            'subject_id' => (int) $mutationRows[$index]->id,
            'fields' => [],
            'target' => $target,
            'cancel_after_issue' => $target === 'cancelled',
        ];
    }

    foreach (['draft', 'review', 'approved', 'issued'] as $index => $target) {
        $specs[] = [
            'type' => 'employee_duty',
            'subject_id' => (int) $activeEmployees[$index]->id,
            'fields' => [
                'duty_description' => $index === 3 ? $longDuty : 'Melaksanakan pendampingan kegiatan sekolah sintetis untuk pengujian dokumen.',
                'start_date' => '2026-11-02',
                'end_date' => '2026-11-30',
                'location' => 'SMA Negeri 1 UAT Sintetis',
            ],
            'target' => $target,
            'cancel_after_issue' => false,
        ];
    }

    $categories = config('letters.categories');
    $advanceTo = function (App\Models\Letter $letter, string $target, bool $cancelAfterIssue) use ($outgoing, $operator, $principal, &$transitions): void {
        for ($step = 0; $step < 8; $step++) {
            $letter = $letter->fresh();
            if (! $letter) {
                return;
            }

            $status = (string) $letter->workflow_status;

            if ($target === 'cancelled') {
                if ($status === App\Models\Letter::WORKFLOW_CANCELLED) {
                    return;
                }
                if ($status === App\Models\Letter::WORKFLOW_ISSUED) {
                    $outgoing->cancel($letter->id, 'UAT-P07C synthetic cancellation after issue.', (int) $letter->version, $principal);
                    $transitions++;

                    continue;
                }
                if (! $cancelAfterIssue && in_array($status, [App\Models\Letter::WORKFLOW_DRAFT, App\Models\Letter::WORKFLOW_REVIEW], true)) {
                    $outgoing->cancel($letter->id, 'UAT-P07C synthetic cancellation before issue.', (int) $letter->version, $operator);
                    $transitions++;

                    continue;
                }
            } elseif ($status === $target) {
                return;
            }

            $version = (int) $letter->version;
            if ($status === App\Models\Letter::WORKFLOW_DRAFT) {
                $outgoing->submit($letter->id, $version, $operator);
            } elseif ($status === App\Models\Letter::WORKFLOW_REVIEW) {
                $outgoing->approve($letter->id, 'UAT-P07C synthetic approval note.', $version, $principal);
            } elseif ($status === App\Models\Letter::WORKFLOW_APPROVED) {
                $outgoing->issue($letter->id, $version, $principal);
            } else {
                return;
            }
            $transitions++;
        }
    };

    $createdTyped = 0;
    $reusedTyped = 0;
    $transitions = 0;

    foreach ($specs as $index => $spec) {
        $n = $index + 1;
        $subjectLabel = match ($spec['type']) {
            'student_active' => 'Student Active',
            'student_mutation' => 'Student Mutation',
            default => 'Employee Duty',
        };
        $subject = sprintf('UAT-P07C Typed Letter %03d — %s', $n, $subjectLabel);

        $letter = App\Models\Letter::query()
            ->where('direction', App\Models\Letter::DIRECTION_OUTGOING)
            ->where('subject', $subject)
            ->first();

        if ($letter) {
            $reusedTyped++;
        } else {
            $date = Illuminate\Support\Carbon::create(2026, 10, 1)->addDays(($n - 1) % 28);
            $outgoing->create([
                'received_date' => $date->format('Y-m-d'),
                'letter_date' => $date->copy()->subDay()->format('Y-m-d'),
                'sender' => 'SMA Negeri 1 UAT Sintetis',
                'recipient' => 'Dinas Pendidikan Sintetis',
                'subject' => $subject,
                'category' => $categories[($n - 1) % count($categories)],
                'notes' => 'UAT-P07C synthetic typed letter fixture '.sprintf('%03d', $n).'.',
                'letter_type' => $spec['type'],
                'document_subject_id' => $spec['subject_id'],
                'document_fields' => $spec['fields'],
            ], $operator);
            $createdTyped++;

            $letter = App\Models\Letter::query()
                ->where('direction', App\Models\Letter::DIRECTION_OUTGOING)
                ->where('subject', $subject)
                ->firstOrFail();
        }

        $advanceTo($letter, (string) $spec['target'], (bool) $spec['cancel_after_issue']);
    }

    $typedQuery = App\Models\Letter::query()->whereNotNull('letter_type');
    $typedByType = (clone $typedQuery)->pluck('letter_type')->countBy();
    $typedByStatus = (clone $typedQuery)->pluck('workflow_status')->countBy();
    $documentFiles = Illuminate\Support\Facades\Storage::disk('local')->allFiles('attachments/letter-documents');
    $counter = App\Models\LetterNumberCounter::query()
        ->where('scope', 'outgoing')
        ->where('period', Illuminate\Support\Carbon::now('Asia/Jakarta')->format('Y'))
        ->first();

    fwrite(STDOUT, 'School context: '.$school->name.' (NPSN '.$school->npsn.'), year '.$year->name
        .', profile #'.$profile->id.', settings ready '.($settingsService->readiness()['ready'] ? 'yes' : 'no').".\n");
    fwrite(STDOUT, 'Students: '.App\Models\Student::count().' total ('.$studentsCreated.' created this run); '
        .App\Models\StudentRombel::query()->whereNull('archived_at')->whereNull('latest_student_exit_date')
            ->whereHas('schoolRombel', fn ($query) => $query->where('school_year_id', $year->id))->count()
        .' active memberships in '.$year->name.'.'."\n");
    fwrite(STDOUT, 'Mutations: '.App\Models\StudentMutation::count().' total, '
        .App\Models\StudentMutation::where('status', App\Models\StudentMutation::STATUS_ACTIVE)->count()
        ." active ({$mutationsCreated} created this run).\n");
    fwrite(STDOUT, 'Typed letters: '.(clone $typedQuery)->count()." total ({$createdTyped} created, {$reusedTyped} reused, {$transitions} transitions); "
        .'student_active '.($typedByType['student_active'] ?? 0).', student_mutation '.($typedByType['student_mutation'] ?? 0)
        .', employee_duty '.($typedByType['employee_duty'] ?? 0).".\n");
    fwrite(STDOUT, 'Typed workflow: draft '.($typedByStatus['draft'] ?? 0).', review '.($typedByStatus['review'] ?? 0)
        .', approved '.($typedByStatus['approved'] ?? 0).', issued '.($typedByStatus['issued'] ?? 0)
        .', cancelled '.($typedByStatus['cancelled'] ?? 0).".\n");
    fwrite(STDOUT, 'Letter documents: '.App\Models\LetterDocument::count().' rows, '.count($documentFiles)
        .' private files; counter '.$counter?->period.' = '.($counter?->last_number ?? 0).".\n");
    exit(0);
}

if ($command === 'status') {
    fwrite(STDOUT, "[ISOLATION GUARD PASS] Dedicated phase-07 SQLite UAT; no MariaDB connection.\n");
    $letters = App\Models\Letter::where('direction', 'incoming');
    $attachmentFiles = Illuminate\Support\Facades\Storage::disk('local')->allFiles('attachments/letters');
    fwrite(STDOUT, 'Letters: '.(clone $letters)->count().' total; active '.(clone $letters)->where('status', 'active')->count()
        .', void '.(clone $letters)->where('status', 'void')->count()."\n");
    fwrite(STDOUT, 'Attachments: '.App\Models\LetterAttachment::count().' rows ('
        .App\Models\LetterAttachment::onlyTrashed()->count().' removed), '.count($attachmentFiles)." files on private storage.\n");
    fwrite(STDOUT, 'Events: '.App\Models\LetterEvent::count().' (created '
        .App\Models\LetterEvent::where('event', 'created')->count().', updated '
        .App\Models\LetterEvent::where('event', 'updated')->count().', voided '
        .App\Models\LetterEvent::where('event', 'voided')->count().', attachments '
        .App\Models\LetterEvent::whereIn('event', ['attachment_added', 'attachment_removed'])->count().")\n");
    fwrite(STDOUT, 'Source numbers: '.App\Models\Letter::where('direction', 'incoming')->whereNull('source_number')->count()
        .' missing, '.App\Models\Letter::where('direction', 'incoming')->where('source_number', 'UAT-P07/DUP/2026/001')->count()
        ." duplicated.\n");
    fwrite(STDOUT, 'Users: '.App\Models\User::count().' (uat.admin, uat.operator, uat.kepsek, uat.waka).'."\n");
    $outgoing = App\Models\Letter::where('direction', App\Models\Letter::DIRECTION_OUTGOING);
    $outgoingByStatus = (clone $outgoing)->pluck('workflow_status')->countBy();
    fwrite(STDOUT, 'Outgoing letters: '.(clone $outgoing)->count().' total; draft '.($outgoingByStatus['draft'] ?? 0)
        .', review '.($outgoingByStatus['review'] ?? 0).', approved '.($outgoingByStatus['approved'] ?? 0)
        .', issued '.($outgoingByStatus['issued'] ?? 0).', cancelled '.($outgoingByStatus['cancelled'] ?? 0)
        .'; '.(clone $outgoing)->whereNotNull('official_number')->count()." official numbers.\n");
    $counter = App\Models\LetterNumberCounter::query()->where('scope', 'outgoing')
        ->orderByDesc('period')->first();
    fwrite(STDOUT, 'Number counter: '.($counter ? $counter->period.' = '.$counter->last_number : 'none')."\n");
    if (Illuminate\Support\Facades\Schema::hasTable('letter_settings')
        && Illuminate\Support\Facades\Schema::hasTable('letter_documents')
        && Illuminate\Support\Facades\Schema::hasColumn('letters', 'letter_type')) {
        /** @var App\Services\Letters\LetterSettingsService $settingsService */
        $settingsService = $app->make(App\Services\Letters\LetterSettingsService::class);
        $context = $settingsService->context();
        $readiness = $settingsService->readiness($context);
        $settings = $context['settings'];

        fwrite(STDOUT, 'School context: year '.($context['school_year']?->name ?? 'none')
            .', profile '.($context['profile'] ? '#'.$context['profile']->id.' ('.$context['profile']->education_school_npsn.')' : 'none')
            .', letter settings '.($settings ? 'v'.$settings->version : 'none')
            .', ready '.($readiness['ready'] ? 'yes' : 'no').', missing '
            .($readiness['missing'] === [] ? 'none' : implode(', ', $readiness['missing'])).".\n");
        $typed = App\Models\Letter::query()->whereNotNull('letter_type');
        $typedByType = (clone $typed)->pluck('letter_type')->countBy();
        $typedByStatus = (clone $typed)->pluck('workflow_status')->countBy();
        fwrite(STDOUT, 'Typed letters: '.(clone $typed)->count().' total; student_active '.($typedByType['student_active'] ?? 0)
            .', student_mutation '.($typedByType['student_mutation'] ?? 0).', employee_duty '.($typedByType['employee_duty'] ?? 0)
            .'; draft '.($typedByStatus['draft'] ?? 0).', review '.($typedByStatus['review'] ?? 0)
            .', approved '.($typedByStatus['approved'] ?? 0).', issued '.($typedByStatus['issued'] ?? 0)
            .', cancelled '.($typedByStatus['cancelled'] ?? 0).".\n");
        $documentFiles = Illuminate\Support\Facades\Storage::disk('local')->allFiles('attachments/letter-documents');
        $documentPages = App\Models\LetterDocument::query()->sum('page_count');
        fwrite(STDOUT, 'Letter documents: '.App\Models\LetterDocument::count().' rows ('.$documentPages
            .' pages), '.count($documentFiles)." private PDF files.\n");
        fwrite(STDOUT, 'Students: '.App\Models\Student::count().' total; '
            .App\Models\StudentRombel::query()->whereNull('archived_at')->whereNull('latest_student_exit_date')
                ->whereHas('schoolRombel', fn ($query) => $query->where('school_year_id', $context['school_year']?->id))->count()
            .' active memberships in the active year; '.App\Models\StudentMutation::count().' mutation logs ('
            .App\Models\StudentMutation::where('status', App\Models\StudentMutation::STATUS_ACTIVE)->count()." active).\n");
    } else {
        fwrite(STDOUT, "Typed documents: 07c migration not applied yet (run prepare-documents).\n");
    }
    $dispositions = App\Models\LetterDisposition::query();
    $dispositionByStatus = (clone $dispositions)->pluck('status')->countBy();
    fwrite(STDOUT, 'Dispositions: '.(clone $dispositions)->count().' total; pending '.($dispositionByStatus['pending'] ?? 0)
        .', in_progress '.($dispositionByStatus['in_progress'] ?? 0).', completed '.($dispositionByStatus['completed'] ?? 0)
        .', cancelled '.($dispositionByStatus['cancelled'] ?? 0).'; overdue '.(clone $dispositions)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereDate('due_date', '<', Illuminate\Support\Carbon::today('Asia/Jakarta')->format('Y-m-d'))->count()."\n");
    fwrite(STDOUT, 'Employees: '.App\Models\Employee::query()->count().' total; '
        .App\Models\Employee::whereNull('exit_date')->count()." active.\n");
    fwrite(STDOUT, 'Storage root: '.config('filesystems.disks.local.root')."\n");
    exit(0);
}

throw new RuntimeException('Allowed commands: init, prepare, prepare-letters, prepare-outgoing, prepare-dispositions, prepare-documents, status. Serve using PHP -S 127.0.0.1:18025 with this router.');
