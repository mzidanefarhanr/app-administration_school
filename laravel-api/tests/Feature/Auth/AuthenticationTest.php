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

    $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create(['status_active' => true]);

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertNoContent();
});
