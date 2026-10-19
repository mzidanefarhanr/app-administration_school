<?php

use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

test('bootstrap admin command fails if active access manager already exists', function () {
    $this->seedAccessReferenceData();

    // Create an existing active administrator who can manage access
    $this->makeUserWithRole('admin-sistem');

    $this->artisan('app:bootstrap-admin')
        ->expectsOutput('An active user with access management capabilities already exists. Bootstrap aborted.')
        ->assertExitCode(1);
});

test('bootstrap admin command validates short password and confirmation mismatch', function () {
    $this->seedAccessReferenceData();

    // Short password (< 12 chars)
    $this->artisan('app:bootstrap-admin', [
        '--name' => 'Initial Admin',
        '--username' => 'init.admin',
        '--email' => 'admin@school.test',
        '--nik' => '0123456789012345',
    ])
        ->expectsQuestion('Enter password (min 12 characters)', 'short-pass')
        ->expectsQuestion('Confirm password', 'short-pass')
        ->assertExitCode(1);

    // Password confirmation mismatch
    $this->artisan('app:bootstrap-admin', [
        '--name' => 'Initial Admin',
        '--username' => 'init.admin',
        '--email' => 'admin@school.test',
        '--nik' => '0123456789012345',
    ])
        ->expectsQuestion('Enter password (min 12 characters)', 'secure-password-123')
        ->expectsQuestion('Confirm password', 'mismatched-password-456')
        ->assertExitCode(1);

    expect(User::where('username', 'init.admin')->exists())->toBeFalse();
});

test('bootstrap admin command validates nik format', function () {
    $this->seedAccessReferenceData();

    // Invalid NIK (15 digits)
    $this->artisan('app:bootstrap-admin', [
        '--name' => 'Initial Admin',
        '--username' => 'init.admin',
        '--email' => 'admin@school.test',
        '--nik' => '123456789012345', // 15 digits
    ])
        ->expectsQuestion('Enter password (min 12 characters)', 'secure-password-123')
        ->expectsQuestion('Confirm password', 'secure-password-123')
        ->assertExitCode(1);

    expect(User::where('username', 'init.admin')->exists())->toBeFalse();
});

test('bootstrap admin command successfully creates system administrator on empty database', function () {
    $this->seedAccessReferenceData();

    $this->artisan('app:bootstrap-admin', [
        '--name' => 'First Administrator',
        '--username' => 'super.admin',
        '--email' => 'super.admin@school.test',
        '--nik' => '0987654321098765',
    ])
        ->expectsQuestion('Enter password (min 12 characters)', 'secure-super-admin-pass-2026')
        ->expectsQuestion('Confirm password', 'secure-super-admin-pass-2026')
        ->expectsConfirmation('Proceed with creating the administrator account?', 'yes')
        ->expectsOutput("System administrator account 'super.admin' successfully created.")
        ->assertExitCode(0);

    $admin = User::where('username', 'super.admin')->first();
    expect($admin)->not->toBeNull();
    expect($admin->name)->toBe('First Administrator');
    expect($admin->email)->toBe('super.admin@school.test');
    expect($admin->nik)->toBe('0987654321098765');
    expect(str_starts_with($admin->nik, '0'))->toBeTrue();
    expect($admin->status_active)->toBeTrue();
    expect(Hash::check('secure-super-admin-pass-2026', $admin->password))->toBeTrue();
    expect($admin->canManageAccess())->toBeTrue();
    expect($admin->roles()->where('slug', 'admin-sistem')->exists())->toBeTrue();
});

test('bootstrap admin command cancels cleanly when confirmation is denied', function () {
    $this->seedAccessReferenceData();

    $this->artisan('app:bootstrap-admin', [
        '--name' => 'Cancelled Admin',
        '--username' => 'cancelled.admin',
        '--email' => 'cancelled@school.test',
        '--nik' => '0987654321098765',
    ])
        ->expectsQuestion('Enter password (min 12 characters)', 'secure-super-admin-pass-2026')
        ->expectsQuestion('Confirm password', 'secure-super-admin-pass-2026')
        ->expectsConfirmation('Proceed with creating the administrator account?', 'no')
        ->expectsOutput('Bootstrap cancelled by operator.')
        ->assertExitCode(0);

    expect(User::where('username', 'cancelled.admin')->exists())->toBeFalse();
});

test('bootstrap admin command records audit with system actor and without secrets', function () {
    $this->seedAccessReferenceData();

    $this->artisan('app:bootstrap-admin', [
        '--name' => 'Audited Admin',
        '--username' => 'audited.admin',
        '--email' => 'audited.admin@school.test',
        '--nik' => '0987654321098765',
    ])
        ->expectsQuestion('Enter password (min 12 characters)', 'super-secret-admin-pass-2026')
        ->expectsQuestion('Confirm password', 'super-secret-admin-pass-2026')
        ->expectsConfirmation('Proceed with creating the administrator account?', 'yes')
        ->assertExitCode(0);

    $admin = User::where('username', 'audited.admin')->firstOrFail();

    // Verify user audit record
    $userActivity = Activity::where('table_name', 'users')
        ->where('record_id', $admin->id)
        ->firstOrFail();

    expect($userActivity->user_id)->toBeNull(); // Explicitly system/CLI actor (no user logged in)
    expect($userActivity->information)->toBe('Created');
    expect($userActivity->new['actor'])->toBe('system:cli');
    expect($userActivity->new)->toHaveKeys(['name', 'username', 'email', 'nik']);
    expect($userActivity->new)->not->toHaveKey('password');
    expect($userActivity->new)->not->toHaveKey('password_confirmation');
    expect(json_encode($userActivity))->not->toContain('super-secret-admin-pass-2026');
    expect(json_encode($userActivity))->not->toContain($admin->password); // No bcrypt hash

    // Verify role_user audit record
    $pivotActivity = Activity::where('table_name', 'role_user')
        ->where('record_id', $admin->id)
        ->firstOrFail();

    expect($pivotActivity->user_id)->toBeNull();
    expect($pivotActivity->new['actor'])->toBe('system:cli');
    expect($pivotActivity->new['role'])->toBe('admin-sistem');
});

test('audit insert failure rolls back bootstrap user, role assignment, and audit records', function () {
    $this->seedAccessReferenceData();

    // Hook to fail on audit insert during bootstrap
    Activity::$testCreatingHook = function ($activity) {
        if ($activity->table_name === 'role_user') {
            throw new \RuntimeException('Simulated audit insert failure during bootstrap');
        }
    };

    try {
        $this->artisan('app:bootstrap-admin', [
            '--name' => 'Failed Bootstrap Admin',
            '--username' => 'failed.admin',
            '--email' => 'failed.admin@school.test',
            '--nik' => '0987654321098765',
        ])
            ->expectsQuestion('Enter password (min 12 characters)', 'super-secret-admin-pass-2026')
            ->expectsQuestion('Confirm password', 'super-secret-admin-pass-2026')
            ->expectsConfirmation('Proceed with creating the administrator account?', 'yes');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toBe('Simulated audit insert failure during bootstrap');
    } finally {
        Activity::$testCreatingHook = null;
    }

    // State check: user creation and role assignment rolled back
    expect(User::where('username', 'failed.admin')->exists())->toBeFalse();
    expect(DB::table('role_user')->count())->toBe(0);
    expect(Activity::count())->toBe(0);
});

test('rejected bootstrap when admin already exists does not generate any audit records', function () {
    $this->seedAccessReferenceData();
    $this->makeUserWithRole('admin-sistem');

    $initialActivityCount = Activity::count();

    $this->artisan('app:bootstrap-admin')
        ->expectsOutput('An active user with access management capabilities already exists. Bootstrap aborted.')
        ->assertExitCode(1);

    expect(Activity::count())->toBe($initialActivityCount);
});
