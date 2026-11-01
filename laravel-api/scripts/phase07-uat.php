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
    fwrite(STDOUT, 'Storage root: '.config('filesystems.disks.local.root')."\n");
    exit(0);
}

throw new RuntimeException('Allowed commands: init, prepare, prepare-letters, status. Serve using PHP -S 127.0.0.1:18025 with this router.');
