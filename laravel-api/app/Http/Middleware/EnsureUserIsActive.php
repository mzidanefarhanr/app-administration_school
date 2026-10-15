<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan status akun aktif pada setiap request terautentikasi.
 *
 * Tujuan: perubahan status akun (mis. dinonaktifkan) berlaku pada request
 * berikutnya, termasuk sesi yang sudah login. Akun yang tidak aktif ditolak
 * dan sesinya diakhiri.
 *
 * Aturan status (DEC-03 / perilaku autentikasi): akun dianggap aktif bila
 * `status_active` bernilai true (boolean baseline). Pemetaan status_user_id
 * belum dikonfirmasi, sehingga middleware ini memakai `status_active` saja
 * dan dicatat sebagai keputusan terbuka.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->status_active) {
            // Akhiri sesi pengguna nonaktif.
            auth()->guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your account is inactive. Please contact the administrator.',
                ], 403);
            }

            return redirect('/auth/login');
        }

        return $next($request);
    }
}
