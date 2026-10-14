<?php

namespace App\Providers;

use App\Models\User;
use App\Support\AbilityCatalog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Registrasi terpusat penegakan kemampuan (named abilities).
 *
 * Semua pemeriksaan izin di backend harus melalui Gate ini:
 *   Gate::allows('students.export')
 *   $this->authorize('letters.approve')
 *   Route::middleware('can:roles.manage')
 *
 * Gate::before mencocokkan nama kemampuan pengguna dari tabel roles/abilities.
 * TIDAK ada bypass "admin boleh segalanya": kemampuan persetujuan/penandatanganan
 * hanya berlaku bila benar-benar diberikan lewat konfigurasi peran.
 */
class AccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Daftarkan kemampuan bernama sebagai Gate ability. Mengembalikan true bila
        // pengguna punya kemampuan; null agar policy (jika ada) tetap dievaluasi.
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            return $user->hasAbility($ability) ? true : null;
        });
    }

    public function register(): void
    {
        //
    }

    /**
     * Helper dipakai command/seed untuk menyelaraskan katalog kemampuan ke DB.
     * Disediakan statis agar dapat dipanggil command tanpa duplikasi logika.
     */
    public static function catalogNames(): array
    {
        return array_keys(AbilityCatalog::definitions());
    }

    /**
     * Cek ketersediaan tabel (untuk guard saat boot tanpa memaksa migrasi).
     */
    public static function tablesExist(): bool
    {
        try {
            return Schema::hasTable('abilities') && Schema::hasTable('roles');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
