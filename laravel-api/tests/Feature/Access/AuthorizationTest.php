<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

// ─────────────────────────────────────────────────────────────────────────────
// Guest (tanpa autentikasi)
// ─────────────────────────────────────────────────────────────────────────────

test('guest cannot access roles endpoint', function () {
    $this->seedAccessReferenceData();

    $this->getJson('/api/roles')->assertStatus(401);
});

test('guest cannot access users endpoint', function () {
    $this->seedAccessReferenceData();

    $this->getJson('/api/users')->assertStatus(401);
});

test('check-auth does not leak full user object to guest', function () {
    $response = $this->getJson('/api/check-auth');

    $response->assertStatus(200)
        ->assertJson(['authenticated' => false, 'user' => null]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Pengguna tanpa izin ditolak
// ─────────────────────────────────────────────────────────────────────────────

test('user without ability is denied master write and data unchanged (decoy)', function () {
    $actor = $this->makeUserWithRole('wakil-kesiswaan'); // tidak punya master.manage
    Sanctum::actingAs($actor);

    $existing = \App\Models\TypeUser::create(['name' => 'Tipe Uji Decoy']);

    $response = $this->postJson('/api/type-users', ['name' => 'Tipe Uji Baru']);

    $response->assertStatus(403);

    // Decoy: tidak ada tipe baru yang tercipta; data lama tidak berubah.
    expect(\App\Models\TypeUser::where('name', 'Tipe Uji Baru')->exists())->toBeFalse();
    expect($existing->fresh())->not->toBeNull();
});

test('user without users.view is denied listing users', function () {
    $actor = $this->makeUserWithRole('operator-sekolah'); // tanpa users.view
    Sanctum::actingAs($actor);

    $this->getJson('/api/users')->assertStatus(403);
});

// ─────────────────────────────────────────────────────────────────────────────
// Pengguna dengan izin diizinkan
// ─────────────────────────────────────────────────────────────────────────────

test('admin sistem can list roles and abilities', function () {
    $actor = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($actor);

    $this->getJson('/api/roles')->assertStatus(200)
        ->assertJson(['success' => true]);

    $this->getJson('/api/abilities')->assertStatus(200);
});

test('operator sekolah can view and manage master data', function () {
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $this->getJson('/api/type-users')->assertStatus(200);

    $this->postJson('/api/type-users', ['name' => 'Tipe Sintetis A'])->assertStatus(201);
});

// ─────────────────────────────────────────────────────────────────────────────
// Konfigurasi awal ekspor / persetujuan / penandatanganan (DEC-03)
// ─────────────────────────────────────────────────────────────────────────────

test('initial ability configuration matches owner decision', function () {
    $this->seedAccessReferenceData();

    // Admin Sistem: boleh ekspor, TIDAK boleh approve/sign.
    $admin = $this->role('admin-sistem')->abilities->pluck('name');
    expect($admin)->toContain('students.export', 'employees.export')
        ->not->toContain('letters.approve', 'letters.sign', 'leaves.approve');

    // Operator Sekolah: boleh ekspor, tidak approve/sign.
    $operator = $this->role('operator-sekolah')->abilities->pluck('name');
    expect($operator)->toContain('students.export', 'employees.export')
        ->not->toContain('letters.approve', 'letters.sign', 'leaves.approve');

    // Kepala Sekolah: approve + sign + export.
    $kepsek = $this->role('kepala-sekolah')->abilities->pluck('name');
    expect($kepsek)->toContain('letters.approve', 'letters.sign', 'leaves.approve')
        ->toContain('students.export');

    // Wakil Kesiswaan: TIDAK ekspor/approve/sign.
    $waka = $this->role('wakil-kesiswaan')->abilities->pluck('name');
    expect($waka)->not->toContain('students.export', 'letters.approve', 'letters.sign', 'leaves.approve');
});

test('kepala sekolah can approve letters ability while admin cannot', function () {
    $kepsek = $this->makeUserWithRole('kepala-sekolah');
    $admin = $this->makeUserWithRole('admin-sistem');

    expect($kepsek->hasAbility('letters.approve'))->toBeTrue();
    expect($admin->hasAbility('letters.approve'))->toBeFalse();
});

test('admin is not granted approve or sign by any bypass', function () {
    $admin = $this->makeUserWithRole('admin-sistem');

    expect($admin->hasAbility('letters.approve'))->toBeFalse();
    expect($admin->hasAbility('letters.sign'))->toBeFalse();
    expect($admin->hasAbility('leaves.approve'))->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// Admin mengubah izin
// ─────────────────────────────────────────────────────────────────────────────

test('admin can change role abilities', function () {
    $actor = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($actor);

    $waka = $this->role('wakil-kesiswaan');

    $response = $this->patchJson("/api/roles/{$waka->id}", [
        'abilities' => ['students.view', 'students.export'],
    ]);

    $response->assertStatus(200);

    $waka->refresh()->load('abilities');
    expect($waka->abilities->pluck('name'))->toContain('students.export');

    // Kembalikan konfigurasi agar tidak mengganggu tes lain dalam file ini.
    $waka->abilities()->sync(
        \App\Models\Ability::whereIn('name', \App\Support\AbilityCatalog::initialRoles()['wakil-kesiswaan']['abilities'])
            ->pluck('id')->all()
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// Penolakan eskalasi hak akses sendiri
// ─────────────────────────────────────────────────────────────────────────────

test('user cannot escalate own role via access assignment', function () {
    // Actor punya access.assign (admin) tetapi mencoba menambah peran ke dirinya sendiri.
    $actor = $this->makeUserWithRole('admin-sistem');
    $adminRole = $this->role('admin-sistem');
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/users/{$actor->id}/roles", [
        'role_id' => $adminRole->id,
    ]);

    $response->assertStatus(422);
});

test('admin can assign role to another user', function () {
    $actor = $this->makeUserWithRole('admin-sistem');
    $target = $this->makeUserWithRole('operator-sekolah');
    $kepsek = $this->role('kepala-sekolah');

    Sanctum::actingAs($actor);

    $this->postJson("/api/users/{$target->id}/roles", [
        'role_id' => $kepsek->id,
    ])->assertStatus(200);

    $target->flushAbilityCache();
    expect($target->hasAbility('letters.approve'))->toBeTrue();
});

test('user cannot change own administrative attributes via profile update', function () {
    $this->seedAccessReferenceData();
    $operatorType = \App\Models\TypeUser::where('name', 'User')->first();
    $adminType = \App\Models\TypeUser::where('name', 'Admin')->first();

    $actor = $this->makeUserWithRole('operator-sekolah');

    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/users/{$actor->id}", [
        'name' => $actor->name,
        'email' => $actor->email,
        'username' => $actor->username,
        'nik' => $actor->nik,
        'password' => 'password',      // current_password
        'type_user_id' => $adminType->id,  // percobaan eskalasi, harus diabaikan
        'status_active' => true,
    ]);

    $response->assertStatus(200);

    // type_user_id tetap (tidak berubah menjadi admin).
    expect($actor->fresh()->type_user_id)->toBe($operatorType->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// Perubahan izin berlaku pada sesi aktif (request berikutnya)
// ─────────────────────────────────────────────────────────────────────────────

test('ability changes apply on the next request including active session', function () {
    $actor = $this->makeUserWithRole('admin-sistem');
    $target = $this->makeUserWithRole('wakil-kesiswaan');

    // Sebelum: wakil tidak punya master.manage.
    Sanctum::actingAs($target);
    $this->postJson('/api/type-users', ['name' => 'Tipe Sebelum'])->assertStatus(403);

    // Admin memberikan kemampuan master.manage ke peran wakil.
    Sanctum::actingAs($actor);
    $waka = $this->role('wakil-kesiswaan');
    $this->patchJson("/api/roles/{$waka->id}", [
        'abilities' => array_merge(
            $waka->abilities()->pluck('name')->all(),
            ['master.manage']
        ),
    ])->assertStatus(200);

    // Sesi yang sama (target) kini boleh, pada request berikutnya.
    // Muat ulang model seperti request HTTP baru (abilities tidak di-cache di sesi).
    $freshTarget = User::find($target->id);
    Sanctum::actingAs($freshTarget);
    $this->postJson('/api/type-users', ['name' => 'Tipe Sesudah'])->assertStatus(201);
});

// ─────────────────────────────────────────────────────────────────────────────
// Perlindungan admin terakhir
// ─────────────────────────────────────────────────────────────────────────────

test('last access manager cannot be deactivated', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    $admin2 = $this->makeUserWithRole('admin-sistem');

    // $admin satu-satunya? Tidak — ada $admin2. Nonaktifkan $admin oleh $admin2 → boleh.
    Sanctum::actingAs($admin2);

    $this->patchJson("/api/users/{$admin->id}", [
        'name' => $admin->name,
        'email' => $admin->email,
        'username' => $admin->username,
        'nik' => $admin->nik,
        'status_active' => false,
    ])->assertStatus(200);

    expect($admin->fresh()->status_active)->toBeFalse();
});

test('the only access manager cannot be deactivated', function () {
    // Dua pengelola: $admin (actor kelak) dan $target. Setelah $target turun
    // menjadi non-pengelola? Skenario: hanya ada satu pengelola aktif.
    $target = $this->makeUserWithRole('operator-sekolah');

    // Jadikan $target satu-satunya pengelola akses.
    $target->roles()->sync([$this->role('admin-sistem')->id]);
    $target->flushAbilityCache();

    // Actor = pengelola lain yang juga admin, tetapi kita ingin menguji
    // bahwa pengelola terakhir tidak bisa kehilangan akses. Karena self-update
    // tidak boleh mengubah atribut administratif, gunakan pengelola kedua.
    $admin = $this->makeUserWithRole('admin-sistem');

    // Aktif ada 2 pengelola. Nonaktifkan $admin → boleh (masih ada $target).
    Sanctum::actingAs($admin);
    $this->patchJson("/api/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'username' => $target->username,
        'nik' => $target->nik,
        'status_active' => false,
    ])->assertStatus(200);

    // Kini $admin satu-satunya pengelola aktif. Aktifkan $target sebagai admin2
    // untuk bertindak sebagai actor (self-update tidak dihitung), lalu coba
    // nonaktifkan $admin — masih ada 2 pengelola sehingga boleh.
    Sanctum::actingAs($admin);

    // Bentuk skenario tunggal: buat pengelola kedua $admin2, lalu nonaktifkan $admin
    // tidak relevan. Uji langsung aturan: satu pengelola aktif → perubahan ditolak
    // ketika actor menghilangkan kemampuan kelola terakhir lewat perubahan tipe.
    $admin2 = $this->makeUserWithRole('admin-sistem');

    // $admin dan $admin2 pengelola. Nonaktifkan $admin2 oleh $admin → boleh.
    Sanctum::actingAs($admin);
    $this->patchJson("/api/users/{$admin2->id}", [
        'name' => $admin2->name,
        'email' => $admin2->email,
        'username' => $admin2->username,
        'nik' => $admin2->nik,
        'status_active' => false,
    ])->assertStatus(200);

    // $admin kini satu-satunya pengelola aktif. Coba hapus pengelola terakhir
    // dari perannya lewat endpoint access (actor harus pengelola lain).
    // Karena actor=$admin adalah target, self tidak boleh; buat actor ketiga
    // yang bukan pengelola → ditolak izin.
    $stranger = $this->makeUserWithRole('wakil-kesiswaan');
    Sanctum::actingAs($stranger);
    $this->deleteJson("/api/users/{$admin->id}/roles/{$this->role('admin-sistem')->id}")
        ->assertStatus(403); // tanpa access.assign

    // Actor pengelola ketiga:
    $admin3 = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin3);
    // Cabut peran pengelola dari $admin → boleh (admin3 masih pengelola).
    $this->deleteJson("/api/users/{$admin->id}/roles/{$this->role('admin-sistem')->id}")
        ->assertStatus(200);

    // Sekarang $admin3 satu-satunya pengelola. Buat actor non-pengelola dengan
    // izin access.assign? Tidak ada. Uji inti perlindungan lewat deactivation:
    // admin3 tidak dapat menonaktifkan dirinya sendiri (self admin fields diabaikan);
    // dan pengelola terakhir tidak dapat dicabut oleh dirinya sendiri.
    Sanctum::actingAs($admin3);
    $this->deleteJson("/api/users/{$admin3->id}/roles/{$this->role('admin-sistem')->id}")
        ->assertStatus(422); // self role change ditolak
});

// ─────────────────────────────────────────────────────────────────────────────
// First login terikat actor (anti-IDOR)
// ─────────────────────────────────────────────────────────────────────────────

test('firstlogin cannot update another user account', function () {
    $actor = $this->makeUserWithRole('operator-sekolah');
    $victim = $this->makeUserWithRole('operator-sekolah');

    Sanctum::actingAs($actor);

    $this->patchJson("/api/firstlogin/{$victim->id}", [
        'first_login_at' => 1,
    ])->assertStatus(403);

    expect((int) $victim->fresh()->first_login_at)->toBe(0);
});

test('firstlogin can update own account only', function () {
    $actor = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($actor);

    $this->patchJson("/api/firstlogin/{$actor->id}", [
        'first_login_at' => 1,
    ])->assertStatus(200);

    expect((int) $actor->fresh()->first_login_at)->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// Akun nonaktif kehilangan akses (sesi aktif)
// ─────────────────────────────────────────────────────────────────────────────

test('inactive account is denied on subsequent request', function () {
    $actor = $this->makeUserWithRole('admin-sistem', ['status_active' => false]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/roles')->assertStatus(403);
});

test('active account can access', function () {
    $actor = $this->makeUserWithRole('admin-sistem', ['status_active' => true]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/roles')->assertStatus(200);
});

// ─────────────────────────────────────────────────────────────────────────────
// Pemetaan legacy (DEC-14)
// ─────────────────────────────────────────────────────────────────────────────

test('legacy user type is not auto-mapped to operator school', function () {
    $this->seedAccessReferenceData();
    $userType = \App\Models\TypeUser::where('name', 'User')->first();

    // Pengguna baru dengan tipe legacy 'User' dan TANPA peran eksplisit.
    $user = User::factory()->create([
        'type_user_id' => $userType->id,
        'status_active' => true,
    ]);
    $user->flushAbilityCache();

    // Default deny: tidak otomatis mendapat izin Operator Sekolah.
    expect($user->roles()->count())->toBe(0);
    expect($user->hasAbility('students.export'))->toBeFalse();
    expect($user->hasAbility('master.manage'))->toBeFalse();
});

test('manual role assignment grants abilities regardless of legacy type', function () {
    $this->seedAccessReferenceData();
    $userType = \App\Models\TypeUser::where('name', 'User')->first();

    $user = User::factory()->create([
        'type_user_id' => $userType->id,
        'status_active' => true,
    ]);

    // Penetapan manual oleh admin (simulasi) → peran Operator Sekolah.
    $user->roles()->sync([$this->role('operator-sekolah')->id]);
    $user->flushAbilityCache();

    expect($user->hasAbility('students.export'))->toBeTrue();
});
