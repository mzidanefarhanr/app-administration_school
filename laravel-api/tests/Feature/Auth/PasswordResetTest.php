<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create([
        'password' => Hash::make('old-password-123'),
        'status_active' => true,
    ]);

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertStatus(200);

        expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
        expect(Hash::check('old-password-123', $user->fresh()->password))->toBeFalse();

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/reset-password', [
        'token' => 'invalid-token-xyz',
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('password cannot be reset with expired token', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    // Fast-forward past default expiration (60 minutes)
    Carbon::setTestNow(now()->addMinutes(61));

    $response = $this->postJson('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);

    Carbon::setTestNow();
});

test('password cannot be reset twice with same token (reused token)', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $response1 = $this->postJson('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);
    $response1->assertStatus(200);

    // Reusing the same token
    $response2 = $this->postJson('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'another-password-456',
        'password_confirmation' => 'another-password-456',
    ]);
    $response2->assertStatus(422);
    $response2->assertJsonValidationErrors(['email']);
});

test('password reset rejects confirmation mismatch and short password', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    // Confirmation mismatch
    $response1 = $this->postJson('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'mismatch-123',
    ]);
    $response1->assertStatus(422);
    $response1->assertJsonValidationErrors(['password']);

    // Short password (< 8 chars)
    $response2 = $this->postJson('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);
    $response2->assertStatus(422);
    $response2->assertJsonValidationErrors(['password']);
});
