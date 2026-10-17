<?php

use App\Models\StatusUser;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

test('two users with identical name are accepted (name uniqueness removed)', function () {
    $this->seedAccessReferenceData();
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $typeUser = TypeUser::where('name', 'User')->first();
    $statusUser = StatusUser::where('name', 'Aktif')->first();

    // First user named John Doe
    $res1 = $this->postJson('/api/users', [
        'name' => 'John Doe',
        'email' => 'johndoe1@school.test',
        'username' => 'johndoe1',
        'nik' => '0123456789012341',
        'password' => 'securepassword123',
        'type_user_id' => $typeUser->id,
        'status_user_id' => $statusUser->id,
    ]);
    $res1->assertStatus(201);

    // Second user with the exact same name John Doe, but different username/email/nik
    $res2 = $this->postJson('/api/users', [
        'name' => 'John Doe',
        'email' => 'johndoe2@school.test',
        'username' => 'johndoe2',
        'nik' => '0123456789012342',
        'password' => 'securepassword123',
        'type_user_id' => $typeUser->id,
        'status_user_id' => $statusUser->id,
    ]);
    $res2->assertStatus(201);

    expect(User::where('name', 'John Doe')->count())->toBe(2);
});

test('nik must be exactly 16 digits and preserves leading zeros', function () {
    $this->seedAccessReferenceData();
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $typeUser = TypeUser::where('name', 'User')->first();
    $statusUser = StatusUser::where('name', 'Aktif')->first();

    // 16-digit NIK starting with 0
    $res = $this->postJson('/api/users', [
        'name' => 'Student Operator',
        'email' => 'student.op@school.test',
        'username' => 'student.op',
        'nik' => '0001234567890123',
        'password' => 'securepassword123',
        'type_user_id' => $typeUser->id,
        'status_user_id' => $statusUser->id,
    ]);
    $res->assertStatus(201);

    $user = User::where('username', 'student.op')->first();
    expect($user->nik)->toBe('0001234567890123');
    expect(str_starts_with($user->nik, '000'))->toBeTrue();

    // Invalid NIK: 15 digits
    $resShort = $this->postJson('/api/users', [
        'name' => 'Invalid NIK User',
        'email' => 'invalid.nik@school.test',
        'username' => 'invalid.nik',
        'nik' => '123456789012345',
        'password' => 'securepassword123',
        'type_user_id' => $typeUser->id,
        'status_user_id' => $statusUser->id,
    ]);
    $resShort->assertStatus(422)
        ->assertJsonValidationErrors(['nik']);

    // Invalid NIK: non-digits
    $resAlpha = $this->postJson('/api/users', [
        'name' => 'Invalid NIK Alpha',
        'email' => 'invalid.alpha@school.test',
        'username' => 'invalid.alpha',
        'nik' => '012345678901234A',
        'password' => 'securepassword123',
        'type_user_id' => $typeUser->id,
        'status_user_id' => $statusUser->id,
    ]);
    $resAlpha->assertStatus(422)
        ->assertJsonValidationErrors(['nik']);
});

test('password creation rejects under 12 characters and accepts 12 or more without composition rules', function () {
    $this->seedAccessReferenceData();
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $typeUser = TypeUser::where('name', 'User')->first();
    $statusUser = StatusUser::where('name', 'Aktif')->first();

    // 11 characters password -> rejected
    $res11 = $this->postJson('/api/users', [
        'name' => 'Short Pass User',
        'email' => 'shortpass@school.test',
        'username' => 'shortpass',
        'nik' => '0123456789012399',
        'password' => '12345678901', // 11 chars
        'type_user_id' => $typeUser->id,
        'status_user_id' => $statusUser->id,
    ]);
    $res11->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    // 12 characters all lowercase -> accepted (no composition requirement)
    $res12 = $this->postJson('/api/users', [
        'name' => 'Valid Pass User',
        'email' => 'validpass@school.test',
        'username' => 'validpass',
        'nik' => '0123456789012398',
        'password' => 'simplelength', // 12 chars
        'type_user_id' => $typeUser->id,
        'status_user_id' => $statusUser->id,
    ]);
    $res12->assertStatus(201);
});

test('user update validates password_new min 12 characters when provided', function () {
    $this->seedAccessReferenceData();
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $target = $this->makeUserWithRole('operator-sekolah');

    // Update with 11-char password_new -> rejected
    $resShort = $this->patchJson("/api/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'username' => $target->username,
        'nik' => $target->nik,
        'password_new' => 'shortpass11', // 11 chars
    ]);
    $resShort->assertStatus(422)
        ->assertJsonValidationErrors(['password_new']);

    // Update with 12-char password_new -> accepted
    $resValid = $this->patchJson("/api/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'username' => $target->username,
        'nik' => $target->nik,
        'password_new' => 'twelvechars1', // 12 chars
    ]);
    $resValid->assertStatus(200);

    expect(Hash::check('twelvechars1', $target->fresh()->password))->toBeTrue();
});

test('user creation validates foreign keys for type_user and status_user', function () {
    $this->seedAccessReferenceData();
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $res = $this->postJson('/api/users', [
        'name' => 'Bad FK User',
        'email' => 'badfk@school.test',
        'username' => 'badfk',
        'nik' => '0123456789012999',
        'password' => 'securepassword123',
        'type_user_id' => 999999, // non-existent
        'status_user_id' => 999999, // non-existent
    ]);
    $res->assertStatus(422)
        ->assertJsonValidationErrors(['type_user_id', 'status_user_id']);
});
