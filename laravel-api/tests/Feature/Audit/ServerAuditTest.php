<?php

use App\Models\Ability;
use App\Models\Activity;
use App\Models\Role;
use App\Models\Student;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

// ─────────────────────────────────────────────────────────────────────────────
// 1. Otorisasi Pembacaan Audit
// ─────────────────────────────────────────────────────────────────────────────

test('guest cannot access activities endpoint', function () {
    $response = $this->getJson('/api/activities');

    $response->assertStatus(401);
});

test('user without audit.view is denied viewing activities', function () {
    // operator-sekolah by default does not have audit.view
    $operator = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($operator);

    $response = $this->getJson('/api/activities');

    $response->assertStatus(403);
});

test('user with audit.view can list activities with user relation', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    // Create an audit record
    Activity::create([
        'user_id' => $admin->id,
        'table_name' => 'type_users',
        'record_id' => 1,
        'information' => 'Created',
        'new' => ['name' => 'Audit Sample'],
    ]);

    $response = $this->getJson('/api/activities');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $data = $response->json('data.data');
    expect($data)->not->toBeEmpty();
    expect($data[0])->toHaveKey('user');
    expect($data[0]['user'])->toHaveKeys(['id', 'name', 'username']);
});

test('user with audit.view can view single activity detail', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $activity = Activity::create([
        'user_id' => $admin->id,
        'table_name' => 'roles',
        'record_id' => 1,
        'information' => 'Created',
        'new' => ['name' => 'Sample Role'],
    ]);

    $response = $this->getJson("/api/activities/{$activity->id}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $activity->id)
        ->assertJsonPath('data.table_name', 'roles');
});

test('audit read response does not expose password or hash through user relation or serialization', function () {
    $admin = $this->makeUserWithRole('admin-sistem', ['password' => bcrypt('AdminTopSecret123!')]);
    Sanctum::actingAs($admin);

    // Create a target user mutation
    $targetUser = User::factory()->create([
        'name' => 'Target User For Leak Check',
        'password' => bcrypt('TargetTopSecret456!'),
    ]);

    $activity = Activity::where('table_name', 'users')
        ->where('record_id', $targetUser->id)
        ->firstOrFail();

    $responseList = $this->getJson('/api/activities');
    $responseList->assertStatus(200);

    // Verify list response serialization
    $listContent = $responseList->getContent();
    expect($listContent)->not->toContain('AdminTopSecret123!');
    expect($listContent)->not->toContain('TargetTopSecret456!');
    expect($listContent)->not->toContain('$2y$'); // No bcrypt hashes anywhere in response

    $listUser = $responseList->json('data.data.0.user');
    expect($listUser)->toHaveKeys(['id', 'name', 'username']);
    expect($listUser)->not->toHaveKey('password');
    expect($listUser)->not->toHaveKey('remember_token');

    $responseDetail = $this->getJson("/api/activities/{$activity->id}");
    $responseDetail->assertStatus(200);

    $detailContent = $responseDetail->getContent();
    expect($detailContent)->not->toContain('AdminTopSecret123!');
    expect($detailContent)->not->toContain('TargetTopSecret456!');
    expect($detailContent)->not->toContain('$2y$');

    $detailUser = $responseDetail->json('data.user');
    expect($detailUser)->toHaveKeys(['id', 'name', 'username']);
    expect($detailUser)->not->toHaveKey('password');
    expect($detailUser)->not->toHaveKey('remember_token');
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. Penolakan Pemalsuan & Mutasi Klien (Client Mutation / Spoofing / Rollback)
// ─────────────────────────────────────────────────────────────────────────────

test('client cannot create audit log via POST /api/activities (rejected 405)', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $initialCount = Activity::count();

    $response = $this->postJson('/api/activities', [
        'user_id' => 999, // Attempt to spoof actor
        'table_name' => 'users',
        'record_id' => 1,
        'information' => 'Created',
        'new' => ['forged' => true],
    ]);

    $response->assertStatus(405)
        ->assertJson(['message' => 'Audit logs are immutable and cannot be created by clients.']);

    expect(Activity::count())->toBe($initialCount);
});

test('client cannot modify audit log or trigger rollback via PUT/PATCH /api/activities/{id} (rejected 405)', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $activity = Activity::create([
        'user_id' => $admin->id,
        'table_name' => 'type_users',
        'record_id' => 1,
        'information' => 'Updated',
        'before' => ['name' => 'Old Name'],
        'after' => ['name' => 'New Name'],
    ]);

    $putResponse = $this->putJson("/api/activities/{$activity->id}", [
        'information' => 'Rollbacked_Updated',
    ]);
    $putResponse->assertStatus(405)
        ->assertJson(['message' => 'Audit logs are immutable. Rollback has been disabled.']);

    $patchResponse = $this->patchJson("/api/activities/{$activity->id}", [
        'information' => 'Rollbacked_Updated',
    ]);
    $patchResponse->assertStatus(405)
        ->assertJson(['message' => 'Audit logs are immutable. Rollback has been disabled.']);

    // Ensure activity record is unmodified
    expect($activity->fresh()->information)->toBe('Updated');
});

test('client cannot delete audit log via DELETE /api/activities/{id} (rejected 405)', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $activity = Activity::create([
        'user_id' => $admin->id,
        'table_name' => 'type_users',
        'record_id' => 1,
        'information' => 'Created',
        'new' => ['name' => 'Sample'],
    ]);

    $response = $this->deleteJson("/api/activities/{$activity->id}");

    $response->assertStatus(405)
        ->assertJson(['message' => 'Audit logs are immutable and cannot be deleted.']);

    expect(Activity::find($activity->id))->not->toBeNull();
});

test('audit actor cannot be forged: server strictly derives actor from Auth::id()', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    $decoyUser = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($admin);

    // Create a new master record via API while passing a fake user_id / actor
    $response = $this->postJson('/api/type-users', [
        'name' => 'Actor Test Type',
        'user_id' => $decoyUser->id,
    ]);
    $response->assertStatus(201);

    $createdType = TypeUser::where('name', 'Actor Test Type')->firstOrFail();

    $activity = Activity::where('table_name', 'type_users')
        ->where('record_id', $createdType->id)
        ->where('information', 'Created')
        ->firstOrFail();

    expect((int) $activity->user_id)->toBe((int) $admin->id);
    expect((int) $activity->user_id)->not->toBe((int) $decoyUser->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Penyaringan Rahasia (Secrets Sanitization) & Allowlist
// ─────────────────────────────────────────────────────────────────────────────

test('user creation audits allowlisted attributes and strips password, hashes, and tokens', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $rawPassword = 'SecretPassword123!';

    $response = $this->postJson('/api/users', [
        'name' => 'Audited New User',
        'username' => 'audited_user',
        'email' => 'audited@example.com',
        'nik' => '3201010101010099',
        'password' => $rawPassword,
        'password_confirmation' => $rawPassword,
        'type_user_id' => TypeUser::where('name', 'User')->first()->id,
        'status_user_id' => 1,
    ]);

    $response->assertStatus(201);

    $newUser = User::where('username', 'audited_user')->firstOrFail();

    $activity = Activity::where('table_name', 'users')
        ->where('record_id', $newUser->id)
        ->where('information', 'Created')
        ->firstOrFail();

    $newPayload = $activity->new;
    expect($newPayload)->toBeArray();
    expect($newPayload)->toHaveKeys(['name', 'username', 'email', 'nik']);

    // Secrets MUST NOT be recorded
    expect($newPayload)->not->toHaveKey('password');
    expect($newPayload)->not->toHaveKey('password_confirmation');
    expect($newPayload)->not->toHaveKey('remember_token');

    $fullJson = json_encode($activity);
    expect($fullJson)->not->toContain($rawPassword);
    expect($fullJson)->not->toContain($newUser->password); // Hashed password
});

test('user update audits changed allowlisted fields and strips secrets', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $target = User::factory()->create([
        'name' => 'Original Name',
        'username' => 'orig_user',
        'email' => 'orig@example.com',
        'nik' => '3201010101010088',
        'status_active' => true,
    ]);

    $rawNewPassword = 'BrandNewPassword123!';

    $response = $this->putJson("/api/users/{$target->id}", [
        'name' => 'Changed Name',
        'username' => 'orig_user',
        'email' => 'changed@example.com',
        'nik' => '3201010101010088',
        'type_user_id' => $target->type_user_id,
        'status_user_id' => $target->status_user_id,
        'password_new' => $rawNewPassword,
        'version' => $target->version,
    ]);

    $response->assertStatus(200);

    $activity = Activity::where('table_name', 'users')
        ->where('record_id', $target->id)
        ->where('information', 'Updated')
        ->latest('id')
        ->firstOrFail();

    expect($activity->before)->toBeArray();
    expect($activity->after)->toBeArray();

    expect($activity->before['name'])->toBe('Original Name');
    expect($activity->after['name'])->toBe('Changed Name');
    expect($activity->before['email'])->toBe('orig@example.com');
    expect($activity->after['email'])->toBe('changed@example.com');

    // Secrets MUST NOT be recorded
    expect($activity->before)->not->toHaveKey('password');
    expect($activity->after)->not->toHaveKey('password');
    expect($activity->before)->not->toHaveKey('password_new');
    expect($activity->after)->not->toHaveKey('password_new');
    expect($activity->before)->not->toHaveKey('remember_token');
    expect($activity->after)->not->toHaveKey('remember_token');

    $fullJson = json_encode($activity);
    expect($fullJson)->not->toContain($rawNewPassword);
    expect($fullJson)->not->toContain($target->fresh()->password);
});

test('student model audit captures operational attributes without dumping private data', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $gender = \App\Models\Gender::firstOrCreate(['name' => 'Laki-Laki']);

    $student = Student::create([
        'name' => 'Operational Student',
        'nik' => '3201010101010077',
        'nis' => '12345',
        'nisn' => '0012345678',
        'gender_id' => $gender->id,
        'father_name' => 'Private Father Name',
        'father_income' => 'Private Income 10000000',
        'history_illness' => 'Private Medical Condition',
    ]);

    $activity = Activity::where('table_name', 'students')
        ->where('record_id', $student->id)
        ->where('information', 'Created')
        ->firstOrFail();

    $new = $activity->new;
    expect($new)->toBeArray();
    expect($new)->toHaveKeys(['name', 'nik', 'nis', 'nisn']);

    // Non-allowlisted sensitive personal data must NOT be in audit
    expect($new)->not->toHaveKey('father_name');
    expect($new)->not->toHaveKey('father_income');
    expect($new)->not->toHaveKey('history_illness');
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. Audit Pivot (role_user & ability_role)
// ─────────────────────────────────────────────────────────────────────────────

test('role assignment and revocation generate role_user pivot audit', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    $targetUser = $this->makeUserWithRole('operator-sekolah');
    $wakaRole = $this->role('wakil-kesiswaan');

    Sanctum::actingAs($admin);

    // Assign role
    $assignResponse = $this->postJson("/api/users/{$targetUser->id}/roles", [
        'role_id' => $wakaRole->id,
    ]);
    $assignResponse->assertStatus(200);

    $assignActivity = Activity::where('table_name', 'role_user')
        ->where('record_id', $targetUser->id)
        ->where('information', 'Created')
        ->latest('id')
        ->firstOrFail();

    expect((int) $assignActivity->user_id)->toBe((int) $admin->id);
    expect($assignActivity->new)->toBeArray();
    expect($assignActivity->new['role'])->toBe('wakil-kesiswaan');

    // Revoke role
    $revokeResponse = $this->deleteJson("/api/users/{$targetUser->id}/roles/{$wakaRole->id}");
    $revokeResponse->assertStatus(200);

    $revokeActivity = Activity::where('table_name', 'role_user')
        ->where('record_id', $targetUser->id)
        ->where('information', 'Deleted')
        ->latest('id')
        ->firstOrFail();

    expect((int) $revokeActivity->user_id)->toBe((int) $admin->id);
    expect($revokeActivity->delete)->toBeArray();
    expect($revokeActivity->delete['role'])->toBe('wakil-kesiswaan');
});

test('role ability sync generates ability_role pivot audit', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    // Create a new custom role with abilities
    $createResponse = $this->postJson('/api/roles', [
        'name' => 'Custom Audit Role',
        'slug' => 'custom-audit-role',
        'description' => 'Test role for pivot audit',
        'abilities' => ['master.view', 'students.view'],
    ]);
    $createResponse->assertStatus(201);

    $roleId = $createResponse->json('data.id');

    // Verify ability_role creation audit
    $createActivity = Activity::where('table_name', 'ability_role')
        ->where('record_id', $roleId)
        ->where('information', 'Created')
        ->firstOrFail();

    expect($createActivity->new['abilities'])->toContain('master.view');
    expect($createActivity->new['abilities'])->toContain('students.view');

    // Update abilities on the role
    $updateResponse = $this->putJson("/api/roles/{$roleId}", [
        'abilities' => ['master.view', 'students.view', 'employees.view'],
    ]);
    $updateResponse->assertStatus(200);

    // Verify ability_role update audit
    $updateActivity = Activity::where('table_name', 'ability_role')
        ->where('record_id', $roleId)
        ->where('information', 'Updated')
        ->latest('id')
        ->firstOrFail();

    expect($updateActivity->before['abilities'])->not->toContain('employees.view');
    expect($updateActivity->after['abilities'])->toContain('employees.view');
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. Konsistensi Transaksi (Atomicity & Failure Rollback)
// ─────────────────────────────────────────────────────────────────────────────

test('transaction failure rolls back both domain mutation and audit record', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $initialTypeCount = TypeUser::count();
    $initialActivityCount = Activity::count();

    // Decoy record that must stay untouched
    $decoy = TypeUser::create(['name' => 'Decoy Type For Transaction Test']);

    try {
        DB::transaction(function () {
            TypeUser::create(['name' => 'Type Inside Failing Tx']);
            // Intentionally throw exception to trigger transaction rollback
            throw new \RuntimeException('Simulated failure after mutation');
        });
    } catch (\RuntimeException $e) {
        // Expected exception caught
    }

    // Verify neither domain model nor audit record persisted
    expect(TypeUser::where('name', 'Type Inside Failing Tx')->exists())->toBeFalse();
    expect(Activity::where('table_name', 'type_users')->where('new->name', 'Type Inside Failing Tx')->exists())->toBeFalse();

    // Decoy is still intact
    expect($decoy->fresh())->not->toBeNull();
});

test('audit insert failure rolls back user domain mutation and preserves database state', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    // Decoy user that must remain untouched
    $decoy = User::factory()->create(['name' => 'Decoy User Intact', 'username' => 'decoy_user_intact']);

    // Hook to fail on audit insert for users table
    Activity::$testCreatingHook = function ($activity) {
        if ($activity->table_name === 'users') {
            throw new \RuntimeException('Simulated audit insert failure for users table');
        }
    };

    try {
        $response = $this->postJson('/api/users', [
            'name' => 'Failed User Should Rollback',
            'username' => 'failed_rollback_user',
            'email' => 'failed_user@example.com',
            'nik' => '3201010101010055',
            'password' => 'Password12345!',
            'password_confirmation' => 'Password12345!',
            'type_user_id' => TypeUser::where('name', 'User')->first()->id,
            'status_user_id' => 1,
        ]);
        $response->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    // Assert database state: user was rolled back and does not exist in DB
    expect(User::where('username', 'failed_rollback_user')->exists())->toBeFalse();
    expect(Activity::where('table_name', 'users')->where('new->username', 'failed_rollback_user')->exists())->toBeFalse();

    // Decoy remains untouched
    expect($decoy->fresh()->name)->toBe('Decoy User Intact');
});

test('audit insert failure rolls back role_user pivot mutation and preserves database state', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    $targetUser = $this->makeUserWithRole('operator-sekolah');
    $wakaRole = $this->role('wakil-kesiswaan');

    Sanctum::actingAs($admin);

    // Hook to fail on audit insert for role_user pivot table
    Activity::$testCreatingHook = function ($activity) {
        if ($activity->table_name === 'role_user') {
            throw new \RuntimeException('Simulated audit insert failure for role_user');
        }
    };

    // 1. Assign failure test
    try {
        $assignResponse = $this->postJson("/api/users/{$targetUser->id}/roles", [
            'role_id' => $wakaRole->id,
        ]);
        $assignResponse->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    // Database state check: role was NOT assigned (pivot insert rolled back)
    expect($targetUser->fresh()->roles()->where('slug', 'wakil-kesiswaan')->exists())->toBeFalse();
    expect($targetUser->fresh()->roles()->where('slug', 'operator-sekolah')->exists())->toBeTrue();
    expect(Activity::where('table_name', 'role_user')->exists())->toBeFalse();

    // Now cleanly assign the role without hook
    $targetUser->roles()->attach($wakaRole->id, ['assigned_by' => $admin->id]);
    expect($targetUser->fresh()->roles()->where('slug', 'wakil-kesiswaan')->exists())->toBeTrue();

    // 2. Revoke failure test
    Activity::$testCreatingHook = function ($activity) {
        if ($activity->table_name === 'role_user') {
            throw new \RuntimeException('Simulated audit insert failure for role_user revoke');
        }
    };

    try {
        $revokeResponse = $this->deleteJson("/api/users/{$targetUser->id}/roles/{$wakaRole->id}");
        $revokeResponse->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    // Database state check: role was NOT revoked (pivot delete rolled back)
    expect($targetUser->fresh()->roles()->where('slug', 'wakil-kesiswaan')->exists())->toBeTrue();
});

test('audit insert failure rolls back ability_role pivot mutation and preserves database state', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    // Create a role with specific initial abilities
    $role = Role::create([
        'name' => 'Pivot Test Role',
        'slug' => 'pivot-test-role',
        'description' => 'Initial role for atomic test',
        'is_protected' => false,
    ]);
    $initialAbilities = ['master.view', 'students.view'];
    $role->abilities()->sync(Ability::whereIn('name', $initialAbilities)->pluck('id')->all());

    $originalAbilities = $role->abilities()->pluck('name')->sort()->values()->all();

    // Hook to fail on audit insert for ability_role pivot
    Activity::$testCreatingHook = function ($activity) {
        if ($activity->table_name === 'ability_role') {
            throw new \RuntimeException('Simulated audit insert failure for ability_role');
        }
    };

    try {
        $response = $this->putJson("/api/roles/{$role->id}", [
            'abilities' => ['master.view', 'students.view', 'employees.view'],
        ]);
        $response->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    // Database state check: abilities on the role remain UNCHANGED
    $currentAbilities = $role->fresh()->abilities()->pluck('name')->sort()->values()->all();
    expect($currentAbilities)->toBe($originalAbilities);
    expect($currentAbilities)->not->toContain('employees.view');
});

test('audit insert failure rolls back type_user creation on POST /api/type-users endpoint and preserves state', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $decoy = TypeUser::create(['name' => 'Decoy Master Type For Tx Check']);

    Activity::$testCreatingHook = function ($activity) {
        if ($activity->table_name === 'type_users') {
            throw new \RuntimeException('Simulated audit insert failure for type_users create');
        }
    };

    try {
        $response = $this->postJson('/api/type-users', [
            'name' => 'Failing Rollback Type User',
        ]);
        $response->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    // Database state check: model was rolled back and does not exist in DB
    expect(TypeUser::where('name', 'Failing Rollback Type User')->exists())->toBeFalse();
    expect(Activity::where('table_name', 'type_users')->where('new->name', 'Failing Rollback Type User')->exists())->toBeFalse();

    // Decoy remains untouched
    expect($decoy->fresh()->name)->toBe('Decoy Master Type For Tx Check');
});

test('audit insert failure rolls back type_user update on PUT /api/type-users/{id} endpoint and preserves state', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $type = TypeUser::create(['name' => 'Original Type Name']);

    Activity::$testCreatingHook = function ($activity) {
        if ($activity->table_name === 'type_users') {
            throw new \RuntimeException('Simulated audit insert failure for type_users update');
        }
    };

    try {
        $response = $this->putJson("/api/type-users/{$type->id}", [
            'name' => 'Attempted New Type Name',
        ]);
        $response->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    // Database state check: model was rolled back to original name
    expect($type->fresh()->name)->toBe('Original Type Name');
    expect(Activity::where('table_name', 'type_users')->where('after->name', 'Attempted New Type Name')->exists())->toBeFalse();
});

test('audit insert failure rolls back type_user deletion on DELETE /api/type-users/{id} endpoint and preserves state', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $type = TypeUser::create(['name' => 'Type Target Delete']);

    Activity::$testCreatingHook = function ($activity) {
        if ($activity->table_name === 'type_users') {
            throw new \RuntimeException('Simulated audit insert failure for type_users delete');
        }
    };

    try {
        $response = $this->deleteJson("/api/type-users/{$type->id}", [
            'delete' => 'true',
        ]);
        $response->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    // Database state check: model was NOT deleted (not soft deleted)
    expect($type->fresh()->trashed())->toBeFalse();
    expect(Activity::where('table_name', 'type_users')->where('record_id', $type->id)->where('information', 'Deleted')->exists())->toBeFalse();
});

test('audit insert failure rolls back firstlogin status update on PUT /api/firstlogin/{id} endpoint and preserves state', function () {
    $user = $this->makeUserWithRole('operator-sekolah', ['first_login_at' => false]);
    Sanctum::actingAs($user);

    Activity::$testCreatingHook = function ($activity) {
        if ($activity->table_name === 'users') {
            throw new \RuntimeException('Simulated audit failure on firstlogin update');
        }
    };

    try {
        $response = $this->putJson("/api/firstlogin/{$user->id}", [
            'first_login_at' => true,
        ]);
        $response->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    // Database state check: first_login_at remained false
    expect((bool) $user->fresh()->first_login_at)->toBeFalse();
    expect(Activity::where('table_name', 'users')->where('record_id', $user->id)->where('information', 'Updated')->exists())->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// 6. Pencegahan Rekursi / Double Logging
// ─────────────────────────────────────────────────────────────────────────────

test('reentrancy guard prevents recursive audit creation', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $initialCount = Activity::count();

    // Mutating a model triggers exactly 1 audit record
    $type = TypeUser::create(['name' => 'Reentrancy Test Type']);

    $activities = Activity::where('table_name', 'type_users')
        ->where('record_id', $type->id)
        ->get();

    expect($activities->count())->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// 7. Keutuhan Riwayat Audit (Immutability & Soft Delete Protection)
// ─────────────────────────────────────────────────────────────────────────────

test('activity model throws RuntimeException when update or delete is attempted directly', function () {
    $admin = $this->makeUserWithRole('admin-sistem');

    $activity = Activity::create([
        'user_id' => $admin->id,
        'table_name' => 'type_users',
        'record_id' => 1,
        'information' => 'Created',
        'new' => ['name' => 'Protected Record'],
    ]);

    // Direct update on Eloquent model must throw
    expect(fn () => $activity->update(['information' => 'Updated']))
        ->toThrow(\RuntimeException::class, 'Audit logs are immutable and cannot be updated.');

    // Direct delete on Eloquent model must throw
    expect(fn () => $activity->delete())
        ->toThrow(\RuntimeException::class, 'Audit logs are immutable and cannot be deleted.');

    // Verify record remains untouched
    expect($activity->fresh()->information)->toBe('Created');
});

test('activity user relation resolves soft-deleted user historically via withTrashed', function () {
    $user = $this->makeUserWithRole('operator-sekolah');

    $activity = Activity::create([
        'user_id' => $user->id,
        'table_name' => 'type_users',
        'record_id' => 1,
        'information' => 'Created',
        'new' => ['name' => 'Historical Action'],
    ]);

    // Soft delete the user
    $user->delete();
    expect($user->trashed())->toBeTrue();

    // Activity user relation should still return the user object (historical integrity)
    $loaded = Activity::find($activity->id);
    expect($loaded->user)->not->toBeNull();
    expect((int) $loaded->user->id)->toBe((int) $user->id);
});
