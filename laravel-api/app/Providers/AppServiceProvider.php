<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Standar password aplikasi: min 12 karakter tanpa aturan komposisi wajib (DEC-16)
        Password::defaults(function () {
            return Password::min(12);
        });
    }
}
