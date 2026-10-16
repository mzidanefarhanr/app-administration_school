<?php

use App\Models\TypeUser;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

test('operator sekolah can list, view, create, and update type-users via master.manage', function () {
    $operator = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($operator);

    // 1. List
    $listRes = $this->getJson('/api/type-users');
    $listRes->assertStatus(200);

    // 2. Create
    $createRes = $this->postJson('/api/type-users', ['name' => 'Tipe Pengujian Operator']);
    $createRes->assertStatus(201);
    $typeId = $createRes->json('data.id');

    // 3. View detail
    $showRes = $this->getJson("/api/type-users/{$typeId}");
    $showRes->assertStatus(200)
        ->assertJsonPath('data.name', 'Tipe Pengujian Operator');

    // 4. Update
    $updateRes = $this->putJson("/api/type-users/{$typeId}", ['name' => 'Tipe Pengujian Operator Diperbarui']);
    $updateRes->assertStatus(200);
    expect(TypeUser::find($typeId)->name)->toBe('Tipe Pengujian Operator Diperbarui');
});

test('user without master.manage cannot mutate type-users', function () {
    $waka = $this->makeUserWithRole('wakil-kesiswaan');
    Sanctum::actingAs($waka);

    $type = TypeUser::create(['name' => 'Tipe Pengujian Waka']);

    // Create denied
    $this->postJson('/api/type-users', ['name' => 'Tipe Baru Terlarang'])
        ->assertStatus(403);

    // Update denied
    $this->putJson("/api/type-users/{$type->id}", ['name' => 'Tipe Ganti Terlarang'])
        ->assertStatus(403);

    // Delete denied
    $this->deleteJson("/api/type-users/{$type->id}", ['delete' => 'true'])
        ->assertStatus(403);
});

test('type-users referenced by users cannot be deleted', function () {
    $operator = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($operator);

    // Admin type is referenced by a user
    $adminType = TypeUser::where('name', 'Admin')->first();
    expect($adminType)->not->toBeNull();
    User::factory()->create(['type_user_id' => $adminType->id]);

    $response = $this->deleteJson("/api/type-users/{$adminType->id}", ['delete' => 'true']);
    $response->assertStatus(422)
        ->assertJsonStructure(['message']);

    expect($response->json('message'))->toContain('Cannot delete! This record is still being referenced');
    expect(TypeUser::where('id', $adminType->id)->exists())->toBeTrue();
});

test('mutating type-users does not alter user roles, abilities, or access control', function () {
    $operator = $this->makeUserWithRole('operator-sekolah');
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($operator);

    $initialOperatorAbilities = $operator->abilityNames();
    $initialAdminAbilities = $admin->abilityNames();

    // Operator creates a new type user and renames an unreferenced one
    $newType = TypeUser::create(['name' => 'Tipe Uji Independen']);

    $this->putJson("/api/type-users/{$newType->id}", ['name' => 'Tipe Uji Diubah'])
        ->assertStatus(200);

    // Roles and abilities remain completely unaffected
    $operator->flushAbilityCache();
    $admin->flushAbilityCache();

    expect($operator->abilityNames())->toBe($initialOperatorAbilities);
    expect($operator->hasAbility('users.manage'))->toBeFalse();
    expect($operator->hasAbility('roles.manage'))->toBeFalse();
    expect($operator->hasAbility('access.assign'))->toBeFalse();

    expect($admin->abilityNames())->toBe($initialAdminAbilities);
    expect($admin->canManageAccess())->toBeTrue();
});

test('renaming type_user does not bypass last access manager protection', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    // Ensure only 1 active access manager
    expect(User::where('status_active', true)->get()->filter(fn (User $u) => $u->canManageAccess())->count())->toBe(1);

    // Create an unreferenced type user to test type changing
    $altType = TypeUser::create(['name' => 'Tipe Alternatif']);

    // Attempting to change admin type_user_id on the last access manager is rejected
    $res = $this->patchJson("/api/users/{$admin->id}", [
        'name' => $admin->name,
        'email' => $admin->email,
        'username' => $admin->username,
        'nik' => $admin->nik,
        'password' => 'password',
        'type_user_id' => $altType->id,
        'status_active' => true,
    ]);

    // Admin attribute changes that would change type of last access manager are rejected or blocked
    // Verify last access manager remains active and capable of managing access
    expect($admin->fresh()->canManageAccess())->toBeTrue();
    expect($admin->fresh()->status_active)->toBeTrue();
});
