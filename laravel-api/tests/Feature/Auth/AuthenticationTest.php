<?php

use App\Models\User;

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create(['status_active' => true]);

    $response = $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertNoContent();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create(['status_active' => true]);

    $this->post('/login', [
        'username' => $user->username,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('inactive users can not authenticate', function () {
    $user = User::factory()->create(['status_active' => false]);

    $response = $this->postJson('/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['username']);
    expect($response->json('errors.username.0'))->toContain('Your account is inactive');
});

test('post-login check-auth returns expected identity and ability contract', function () {
    $user = User::factory()->create([
        'name' => 'Test Operator',
        'username' => 'test.operator',
        'status_active' => true,
        'status_user_id' => 1,
    ]);

    $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
    ])->assertNoContent();

    $checkAuth = $this->getJson('/api/check-auth');
    $checkAuth->assertOk();
    $checkAuth->assertJson([
        'authenticated' => true,
        'user' => [
            'id' => $user->id,
            'name' => 'Test Operator',
            'username' => 'test.operator',
            'status_active' => true,
            'status_user_id' => 1,
        ],
    ]);
    expect($checkAuth->json('user.abilities'))->toBeArray();
    expect($checkAuth->json('user.roles'))->toBeArray();
});

test('unauthenticated check-auth returns false and null user', function () {
    $response = $this->getJson('/api/check-auth');

    $response->assertOk();
    $response->assertExactJson([
        'authenticated' => false,
        'user' => null,
    ]);
});

test('users can logout', function () {
    $user = User::factory()->create(['status_active' => true]);

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertNoContent();
});
