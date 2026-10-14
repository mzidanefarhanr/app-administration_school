<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->guardAgainstUnsafeTestDatabase($app);

        return $app;
    }

    /**
     * Fail-safe: batalkan seluruh suite jika koneksi database uji bukan
     * SQLite in-memory. Mencegah test/migrasi/seed menulis ke database
     * operasional (mis. MySQL backdata_administrations).
     */
    protected function guardAgainstUnsafeTestDatabase(Application $app): void
    {
        // Hanya berlaku saat menjalankan test (APP_ENV=testing), bukan runtime normal.
        if ($app->environment() !== 'testing') {
            throw new RuntimeException(sprintf(
                'Refused to run tests: APP_ENV is "%s", expected "testing".',
                $app->environment()
            ));
        }

        $default = $app['config']->get('database.default');
        $connection = $app['config']->get("database.connections.{$default}", []);
        $driver = $connection['driver'] ?? null;
        $database = $connection['database'] ?? null;

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(sprintf(
                'Refused to run tests against an unsafe database. '.
                'Expected driver=sqlite database=:memory:, got driver=%s database=%s. '.
                'Set DB_CONNECTION=sqlite and DB_DATABASE=:memory: for the test environment. '.
                'Never point tests at the operational database.',
                (string) $driver,
                (string) $database
            ));
        }
    }
}
