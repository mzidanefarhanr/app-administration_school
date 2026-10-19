<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Role;
use App\Models\StatusUser;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Note: password is never accepted via arguments or options to prevent leakage.
     *
     * @var string
     */
    protected $signature = 'app:bootstrap-admin
                            {--name= : Full name of the administrator}
                            {--username= : Login username of the administrator}
                            {--email= : Email address of the administrator}
                            {--nik= : 16-digit national identification number (NIK)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bootstrap the initial system administrator account';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // 1. Check if an active access manager already exists
        $hasActiveAccessManager = User::query()
            ->where('status_active', true)
            ->get()
            ->contains(fn (User $user) => $user->canManageAccess());

        if ($hasActiveAccessManager) {
            $this->error('An active user with access management capabilities already exists. Bootstrap aborted.');

            return Command::FAILURE;
        }

        // 2. Gather non-sensitive inputs (option or interactive prompt)
        $name = $this->option('name') ?: $this->ask('Enter administrator name');
        $username = $this->option('username') ?: $this->ask('Enter administrator username');
        $email = $this->option('email') ?: $this->ask('Enter administrator email');
        $nik = $this->option('nik') ?: $this->ask('Enter 16-digit NIK');

        // Password MUST be gathered via secret prompts (never via CLI flags)
        $password = $this->secret('Enter password (min 12 characters)');
        $passwordConfirmation = $this->secret('Confirm password');

        // 3. Validate inputs strictly
        $validator = Validator::make([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'nik' => $nik,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]+$/', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['required', 'string', 'email:rfc', 'max:100', 'unique:users,email'],
            'nik' => ['required', 'string', 'digits:16', 'unique:users,nik'],
            'password' => ['required', 'string', Password::defaults(), 'same:password_confirmation'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return Command::FAILURE;
        }

        // 4. Display environment and target database without credentials
        $env = app()->environment();
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $this->line("Target Environment: {$env}");
        $this->line("Target Database Connection: {$connection} ({$database})");

        if (! $this->confirm('Proceed with creating the administrator account?', true)) {
            $this->info('Bootstrap cancelled by operator.');

            return Command::SUCCESS;
        }

        // 5. Ensure required reference models exist
        $adminType = TypeUser::where('name', 'Admin')->first()
            ?? TypeUser::firstOrCreate(['name' => 'Admin']);

        $activeStatus = StatusUser::where('name', 'Aktif')->first()
            ?? StatusUser::firstOrCreate(['name' => 'Aktif']);

        $adminRole = Role::where('slug', 'admin-sistem')->first();
        if (! $adminRole) {
            $this->error("Required role 'admin-sistem' does not exist. Please run reference data seeder first.");

            return Command::FAILURE;
        }

        // 6. Atomically create user, assign role, and record audit with system:cli actor
        DB::transaction(function () use ($name, $username, $email, $nik, $password, $adminType, $activeStatus, $adminRole) {
            $user = User::create([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'email_verified_at' => now(),
                'nik' => $nik,
                'password' => Hash::make($password),
                'type_user_id' => $adminType->id,
                'status_user_id' => $activeStatus->id,
                'status_active' => true,
                'first_login_at' => false,
            ]);

            $user->roles()->sync([
                $adminRole->id => ['assigned_by' => null],
            ]);

            // Audit record for initial admin account creation by system/CLI (no password/hash/secret)
            Activity::create([
                'user_id' => null,
                'table_name' => 'users',
                'record_id' => $user->id,
                'information' => 'Created',
                'before' => null,
                'after' => null,
                'new' => [
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'nik' => $nik,
                    'type_user_id' => $adminType->id,
                    'status_user_id' => $activeStatus->id,
                    'status_active' => true,
                    'first_login_at' => false,
                    'actor' => 'system:cli',
                ],
                'delete' => null,
            ]);

            // Audit record for initial admin role assignment by system/CLI
            Activity::create([
                'user_id' => null,
                'table_name' => 'role_user',
                'record_id' => $user->id,
                'information' => 'Created',
                'before' => null,
                'after' => null,
                'new' => [
                    'action' => 'bootstrap',
                    'user_id' => $user->id,
                    'role' => $adminRole->slug,
                    'actor' => 'system:cli',
                ],
                'delete' => null,
            ]);
        });

        $this->info("System administrator account '{$username}' successfully created.");

        return Command::SUCCESS;
    }
}
