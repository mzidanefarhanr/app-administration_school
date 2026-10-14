<?php

// Fase 01a — bukti isolasi database uji (BLK-01).
// Menegaskan suite berjalan pada SQLite in-memory dan BUKAN database operasional.

test('test database is isolated sqlite in-memory', function () {
    $connection = config('database.default');
    $config = config("database.connections.{$connection}");

    expect(config('app.env'))->toBe('testing');
    expect($config['driver'])->toBe('sqlite');
    expect($config['database'])->toBe(':memory:');
});

test('operational mysql database is never the test connection', function () {
    $connection = config('database.default');
    $config = config("database.connections.{$connection}");

    // Baca nama database operasional langsung dari file .env (bukan config,
    // karena config sudah di-override oleh phpunit.xml pada lingkungan uji).
    $envPath = base_path('.env');
    $operationalDb = null;
    if (is_file($envPath)) {
        foreach (file($envPath) as $line) {
            if (preg_match('/^DB_DATABASE=(.+)$/', trim($line), $m)) {
                $operationalDb = trim($m[1]);
            }
        }
    }

    // Tidak boleh memakai database operasional pada koneksi uji.
    if ($operationalDb !== null) {
        expect($config['database'])->not->toBe($operationalDb);
    }
    expect($config['driver'])->not->toBe('mysql');
    expect($config['database'])->toBe(':memory:');
});
