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

throw new RuntimeException('Allowed commands: init, prepare, prepare-letters, prepare-outgoing, prepare-dispositions, status. Serve using PHP -S 127.0.0.1:18025 with this router.');
