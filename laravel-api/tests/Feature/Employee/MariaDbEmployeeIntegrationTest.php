<?php

use App\Services\EmployeeBackfillService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Credentials are supplied out-of-band (env or a file outside the repository).
    $pass = (string) getenv('SCHOOL_04A_DB_PASSWORD');
    $passFile = (string) getenv('SCHOOL_04A_DB_PASSWORD_FILE');

    if ($pass === '' && $passFile !== '' && is_file($passFile)) {
        $pass = trim((string) file_get_contents($passFile));
    }

    if ($pass === '') {
        $this->markTestSkipped('SCHOOL_04A_DB_PASSWORD or SCHOOL_04A_DB_PASSWORD_FILE is required for MariaDB integration tests.');
    }

    config([
        'database.connections.mariadb_04a' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'school_04a_integration',
            'username' => 'school_04a',
            'password' => $pass,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ],
    ]);

    try {
        DB::connection('mariadb_04a')->getPdo();
    } catch (\Throwable $e) {
        $this->markTestSkipped('MariaDB local instance not reachable for 04a integration: '.$e->getMessage());
    }

    $currentDb = DB::connection('mariadb_04a')->getDatabaseName();
    if ($currentDb !== 'school_04a_integration') {
        throw new \RuntimeException("Safety Guard: Aborted. Cannot run tests on non-disposable database: {$currentDb}");
    }

    // Run migrations on isolated disposable database
    Artisan::call('migrate', [
        '--database' => 'mariadb_04a',
        '--force' => true,
    ]);

    $conn = DB::connection('mariadb_04a');

    // Ensure reference records exist
    if (! $conn->table('type_users')->where('id', 1)->exists()) {
        $conn->table('type_users')->insert(['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('type_users')->where('id', 2)->exists()) {
        $conn->table('type_users')->insert(['id' => 2, 'name' => 'User', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('status_users')->where('id', 1)->exists()) {
        $conn->table('status_users')->insert(['id' => 1, 'name' => 'Aktif', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('employee_statuses')->where('id', 1)->exists()) {
        $conn->table('employee_statuses')->insert(['id' => 1, 'name' => 'Pegawai Tetap Yayasan', 'abbreviation' => 'PTY', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('employee_types')->where('id', 1)->exists()) {
        $conn->table('employee_types')->insert(['id' => 1, 'name' => 'Tenaga Kependidikan', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('employee_types')->where('id', 2)->exists()) {
        $conn->table('employee_types')->insert(['id' => 2, 'name' => 'Pendidik', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('employments')->where('id', 1)->exists()) {
        $conn->table('employments')->insert(['id' => 1, 'name' => 'Tetap Yayasan', 'description' => 'Tetap', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('subjects')->where('id', 1)->exists()) {
        $conn->table('subjects')->insert(['id' => 1, 'name' => 'Umum', 'description' => 'Umum', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('provinces')->where('id', 1)->exists()) {
        $conn->table('provinces')->insert(['id' => 1, 'name' => 'DKI Jakarta', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('regencies')->where('id', 1)->exists()) {
        $conn->table('regencies')->insert(['id' => 1, 'province_id' => 1, 'name' => 'Jakarta Utara', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('districts')->where('id', 1)->exists()) {
        $conn->table('districts')->insert(['id' => 1, 'regency_id' => 1, 'name' => 'Koja', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('religions')->where('id', 1)->exists()) {
        $conn->table('religions')->insert(['id' => 1, 'name' => 'Islam', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('marital_statuses')->where('id', 1)->exists()) {
        $conn->table('marital_statuses')->insert(['id' => 1, 'name' => 'Belum Menikah', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('genders')->where('id', 1)->exists()) {
        $conn->table('genders')->insert(['id' => 1, 'name' => 'Laki-Laki', 'created_at' => now(), 'updated_at' => now()]);
    }
});

test('mariadb integration: 04a migration establishes nullable user_id, nullable user_nik, nullable subject_id and version on employees table', function () {
    $conn = DB::connection('mariadb_04a');

    $cols = $conn->select('SHOW COLUMNS FROM employees');
    $colMap = [];
    foreach ($cols as $col) {
        $colMap[$col->Field] = $col;
    }

    // 1. user_id
    expect($colMap)->toHaveKey('user_id');
    expect($colMap['user_id']->Null)->toBe('YES');

    // 2. user_id_resolved_at & account_unlinked_at
    expect($colMap)->toHaveKey('user_id_resolved_at');
    expect($colMap['user_id_resolved_at']->Null)->toBe('YES');
    expect($colMap)->toHaveKey('account_unlinked_at');
    expect($colMap['account_unlinked_at']->Null)->toBe('YES');

    // 3. version
    expect($colMap)->toHaveKey('version');
    expect($colMap['version']->Default)->toBe('1');

    // 4. user_nik nullable
    expect($colMap)->toHaveKey('user_nik');
    expect($colMap['user_nik']->Null)->toBe('YES');

    // 5. subject_id nullable
    expect($colMap)->toHaveKey('subject_id');
    expect($colMap['subject_id']->Null)->toBe('YES');

    // 6. Check unique index on user_id
    $indexes = $conn->select("SHOW INDEXES FROM employees WHERE Column_name = 'user_id'");
    expect(count($indexes))->toBeGreaterThan(0);
    expect((int) $indexes[0]->Non_unique)->toBe(0, 'user_id must be UNIQUE index');

    // 7. Legacy marker added by the follow-up additive migration
    expect($colMap)->toHaveKey('legacy_profile_at');
    expect($colMap['legacy_profile_at']->Null)->toBe('YES');
});

test('mariadb integration: multiple employees can have NULL user_id without violating unique constraint', function () {
    $conn = DB::connection('mariadb_04a');

    $id1 = $conn->table('employees')->insertGetId([
        'user_id' => null,
        'user_nik' => null,
        'employee_status_id' => 1,
        'employee_type_id' => 1, // Tenaga Kependidikan
        'employment_id' => 1,
        'subject_id' => null,
        'religion_id' => 1,
        'district_id' => 1,
        'marital_status_id' => 1,
        'gender_id' => 1,
        'tmt_employee' => '2026-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $id2 = $conn->table('employees')->insertGetId([
        'user_id' => null,
        'user_nik' => null,
        'employee_status_id' => 1,
        'employee_type_id' => 1,
        'employment_id' => 1,
        'subject_id' => null,
        'religion_id' => 1,
        'district_id' => 1,
        'marital_status_id' => 1,
        'gender_id' => 1,
        'tmt_employee' => '2026-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($id1)->toBeGreaterThan(0);
    expect($id2)->toBeGreaterThan(0);
    expect($id1)->not->toBe($id2);

    $count = $conn->table('employees')->whereIn('id', [$id1, $id2])->whereNull('user_id')->count();
    expect($count)->toBe(2);
});

test('mariadb integration: unique user_id blocks assigning same user to two employees including soft-deleted profile', function () {
    $conn = DB::connection('mariadb_04a');

    $nik = '8000'.bin2hex(random_bytes(6));
    $userId = $conn->table('users')->insertGetId([
        'name' => 'MariaDB Test User',
        'username' => 'mdb_emp_user_'.bin2hex(random_bytes(4)),
        'email' => 'mdb_emp_'.bin2hex(random_bytes(4)).'@test.local',
        'password' => bcrypt('Password12345!'),
        'nik' => $nik,
        'type_user_id' => 2,
        'status_user_id' => 1,
        'first_login_at' => 0,
        'status_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // First employee linked to user
    $empId1 = $conn->table('employees')->insertGetId([
        'user_id' => $userId,
        'user_nik' => $nik,
        'employee_status_id' => 1,
        'employee_type_id' => 2,
        'employment_id' => 1,
        'subject_id' => 1,
        'religion_id' => 1,
        'district_id' => 1,
        'marital_status_id' => 1,
        'gender_id' => 1,
        'tmt_employee' => '2026-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Second employee trying to link same user_id must fail unique constraint
    $duplicateBlocked = false;
    try {
        $conn->table('employees')->insert([
            'user_id' => $userId,
            'user_nik' => $nik,
            'employee_status_id' => 1,
            'employee_type_id' => 2,
            'employment_id' => 1,
            'subject_id' => 1,
            'religion_id' => 1,
            'district_id' => 1,
            'marital_status_id' => 1,
            'gender_id' => 1,
            'tmt_employee' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Throwable $e) {
        if ($e->getCode() == '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
            $duplicateBlocked = true;
        }
    }
    expect($duplicateBlocked)->toBeTrue('Duplicate user_id must be blocked by MariaDB unique key');

    // Soft delete first employee
    $conn->table('employees')->where('id', $empId1)->update(['deleted_at' => now()]);

    // Attempting to reuse user_id on another employee while first is soft deleted must still be blocked
    $softDeletedReuseBlocked = false;
    try {
        $conn->table('employees')->insert([
            'user_id' => $userId,
            'user_nik' => $nik,
            'employee_status_id' => 1,
            'employee_type_id' => 2,
            'employment_id' => 1,
            'subject_id' => 1,
            'religion_id' => 1,
            'district_id' => 1,
            'marital_status_id' => 1,
            'gender_id' => 1,
            'tmt_employee' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Throwable $e) {
        if ($e->getCode() == '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
            $softDeletedReuseBlocked = true;
        }
    }
    expect($softDeletedReuseBlocked)->toBeTrue('User ID cannot be reused even if prior employee profile is soft-deleted');
});

test('mariadb integration: hard delete of user sets employee user_id to NULL via nullOnDelete constraint', function () {
    $conn = DB::connection('mariadb_04a');

    $nik = '8100'.bin2hex(random_bytes(6));
    $userId = $conn->table('users')->insertGetId([
        'name' => 'Hard Delete Target User',
        'username' => 'hard_del_'.bin2hex(random_bytes(4)),
        'email' => 'hard_del_'.bin2hex(random_bytes(4)).'@test.local',
        'password' => bcrypt('Password12345!'),
        'nik' => $nik,
        'type_user_id' => 2,
        'status_user_id' => 1,
        'first_login_at' => 0,
        'status_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $empId = $conn->table('employees')->insertGetId([
        'user_id' => $userId,
        'user_nik' => null, // null user_nik so legacy foreign key does not restrict delete
        'employee_status_id' => 1,
        'employee_type_id' => 1,
        'employment_id' => 1,
        'subject_id' => null,
        'religion_id' => 1,
        'district_id' => 1,
        'marital_status_id' => 1,
        'gender_id' => 1,
        'tmt_employee' => '2026-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($conn->table('employees')->where('id', $empId)->value('user_id'))->toBe($userId);

    // Hard delete user row directly in MariaDB
    $conn->table('users')->where('id', $userId)->delete();

    // MariaDB ON DELETE SET NULL must have nulled the user_id column
    $updatedUserId = $conn->table('employees')->where('id', $empId)->value('user_id');
    expect($updatedUserId)->toBeNull();
});

test('mariadb integration: EmployeeBackfillService successfully resolves matching NIK on MariaDB', function () {
    $conn = DB::connection('mariadb_04a');

    $nik1 = '77'.str_pad((string) mt_rand(10000000, 99999999).mt_rand(100000, 999999), 14, '0', STR_PAD_LEFT);
    $userId1 = $conn->table('users')->insertGetId([
        'name' => 'MariaDB Backfill User 1',
        'username' => 'bf_user1_'.bin2hex(random_bytes(4)),
        'email' => 'bf_user1_'.bin2hex(random_bytes(4)).'@test.local',
        'password' => bcrypt('Password12345!'),
        'nik' => $nik1,
        'type_user_id' => 2,
        'status_user_id' => 1,
        'first_login_at' => 0,
        'status_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $empId1 = $conn->table('employees')->insertGetId([
        'user_id' => null,
        'legacy_profile_at' => now(),
        'user_nik' => $nik1,
        'employee_status_id' => 1,
        'employee_type_id' => 2,
        'employment_id' => 1,
        'subject_id' => 1,
        'religion_id' => 1,
        'district_id' => 1,
        'marital_status_id' => 1,
        'gender_id' => 1,
        'tmt_employee' => '2026-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $prevDefault = DB::getDefaultConnection();
    DB::setDefaultConnection('mariadb_04a');
    try {
        $service = new EmployeeBackfillService;
        $stats = $service->backfill(false);

        expect($stats['matched'])->toBe(1);
        expect($conn->table('employees')->where('id', $empId1)->value('user_id'))->toBe($userId1);
        expect($conn->table('employees')->where('id', $empId1)->value('user_id_resolved_at'))->not->toBeNull();
    } finally {
        DB::setDefaultConnection($prevDefault);
    }
});

test('mariadb integration: backfill ignores profiles created after 04a even when NIK matches', function () {
    $conn = DB::connection('mariadb_04a');

    $nik = '78'.str_pad((string) mt_rand(10000000, 99999999).mt_rand(100000, 999999), 14, '0', STR_PAD_LEFT);
    $userId = $conn->table('users')->insertGetId([
        'name' => 'MariaDB Non Legacy User',
        'username' => 'bf_nonlegacy_'.bin2hex(random_bytes(4)),
        'email' => 'bf_nonlegacy_'.bin2hex(random_bytes(4)).'@test.local',
        'password' => bcrypt('Password12345!'),
        'nik' => $nik,
        'type_user_id' => 2,
        'status_user_id' => 1,
        'first_login_at' => 0,
        'status_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $empId = $conn->table('employees')->insertGetId([
        'user_id' => null,
        'legacy_profile_at' => null,
        'user_nik' => $nik,
        'employee_status_id' => 1,
        'employee_type_id' => 1,
        'employment_id' => 1,
        'subject_id' => null,
        'religion_id' => 1,
        'district_id' => 1,
        'marital_status_id' => 1,
        'gender_id' => 1,
        'tmt_employee' => '2026-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $prevDefault = DB::getDefaultConnection();
    DB::setDefaultConnection('mariadb_04a');
    try {
        $stats = (new EmployeeBackfillService)->backfill(false);
    } finally {
        DB::setDefaultConnection($prevDefault);
    }

    expect($stats['skipped_non_legacy'])->toBeGreaterThanOrEqual(1);
    expect($conn->table('employees')->where('id', $empId)->value('user_id'))->toBeNull();
    expect($conn->table('employees')->where('id', $empId)->value('user_id_resolved_at'))->toBeNull();
});
