<?php

// Fase 01a: pendaftaran publik ditutup (keputusan pemilik 2026-10-15).
// Handler RegisteredUserController tidak lagi diekspos sebagai route publik.
// Akun dibuat oleh Admin Sistem melalui pengelolaan akun (users.manage).

test('public registration route is not available', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    // Route dihapus → 404 (atau 405 bila method lain). Bukan 2xx/redirect sukses.
    expect($response->status())->toBeIn([404, 405]);
    $this->assertGuest();
});
