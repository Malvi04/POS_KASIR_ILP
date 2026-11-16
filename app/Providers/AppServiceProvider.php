<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // Rate limiter for staff login (anti-brute-force)
        RateLimiter::for('login', function (Request $request) {
            $identifier = $request->input('identifier') ?? $request->input('username') ?? $request->ip();
            $throttleKey = Str::transliterate(Str::lower($identifier) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak percobaan masuk. Demi keamanan sistem, silakan coba lagi dalam 1 menit.',
                ], 429);
            });
        });

        // Rate limiter for sensitive supervisor PIN verification (void & discount)
        RateLimiter::for('pin-auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())->response(function () {
                return response()->json([
                    'authorized' => false,
                    'success' => false,
                    'message' => 'Terlalu banyak percobaan PIN otorisasi. Silakan tunggu 1 menit.',
                ], 429);
            });
        });

        // Global API rate limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(300)->by($request->user()?->id ?: $request->ip());
        });
    }
}

