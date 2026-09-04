<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configureJwtKeys();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewApiDocs', function (?User $user = null): bool {
            return true;
        });

        Scramble::configure()
            ->expose(
                ui: '/docs/identity',
                document: '/docs/identity.json',
            );

        $this->configureRateLimiting();
    }

    /**
     * Load RS256 JWT keys from mounted Docker secret files.
     */
    protected function configureJwtKeys(): void
    {
        if (config('jwt.algo') !== 'RS256') {
            return;
        }

        $privatePath = config('jwt.keys.private');
        $publicPath = config('jwt.keys.public');

        // If keys are already loaded as PEM strings (e.g., from cached configuration)
        if (is_string($privatePath) && str_contains($privatePath, '-----BEGIN') &&
            is_string($publicPath) && str_contains($publicPath, '-----BEGIN')) {
            return;
        }

        $privateFile = str_starts_with($privatePath ?? '', 'file://') ? substr($privatePath, 7) : $privatePath;
        $publicFile = str_starts_with($publicPath ?? '', 'file://') ? substr($publicPath, 7) : $publicPath;

        if (!$privateFile || !is_readable($privateFile)) {
            throw new RuntimeException(
                'JWT private key is missing or unreadable.'
            );
        }

        if (!$publicFile || !is_readable($publicFile)) {
            throw new RuntimeException(
                'JWT public key is missing or unreadable.'
            );
        }

        $privateKey = file_get_contents($privateFile);
        $publicKey = file_get_contents($publicFile);

        if ($privateKey === false || trim($privateKey) === '') {
            throw new RuntimeException(
                'Unable to load JWT private key.'
            );
        }

        if ($publicKey === false || trim($publicKey) === '') {
            throw new RuntimeException(
                'Unable to load JWT public key.'
            );
        }

        if (!str_contains($privateKey, '-----BEGIN')) {
            throw new RuntimeException(
                'JWT private key is not valid PEM data.'
            );
        }

        if (!str_contains($publicKey, '-----BEGIN')) {
            throw new RuntimeException(
                'JWT public key is not valid PEM data.'
            );
        }

        config([
            'jwt.keys.private' => $privateKey,
            'jwt.keys.public' => $publicKey,
            'jwt.keys.passphrase' => config('jwt.keys.passphrase') ?: null,
        ]);
    }

    /**
     * Configure application rate limiters.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $account = Str::lower(trim((string) (
                $request->input('login')
                ?? $request->input('email')
                ?? $request->input('username')
                ?? ''
            )));

            return [
                Limit::perMinute(10)
                    ->by($account . '|' . $request->ip())
                    ->response(function () {
                        return response()->json([
                            'message' =>
                                'Too many login attempts for this account. Please try again in 1 minute.',
                        ], 429);
                    }),

                Limit::perMinute(60)
                    ->by($request->ip())
                    ->response(function () {
                        return response()->json([
                            'message' =>
                                'Too many login requests from this IP address.',
                        ], 429);
                    }),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' =>
                            'Too many registration requests. Please try again later.',
                    ], 429);
                });
        });

        RateLimiter::for('refresh', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' =>
                            'Too many token refresh requests.',
                    ], 429);
                });
        });

        RateLimiter::for('otp_send', function (Request $request) {
            $email = Str::lower(
                trim((string) $request->input('email', ''))
            );

            return [
                Limit::perMinute(5)
                    ->by($email . '|' . $request->ip())
                    ->response(function () {
                        return response()->json([
                            'message' =>
                                'Too many password reset requests for this email. Please try again later.',
                        ], 429);
                    }),

                Limit::perMinute(30)
                    ->by($request->ip())
                    ->response(function () {
                        return response()->json([
                            'message' =>
                                'Too many OTP requests from this IP address.',
                        ], 429);
                    }),
            ];
        });

        RateLimiter::for('otp_verify', function (Request $request) {
            $email = Str::lower(
                trim((string) $request->input('email', ''))
            );

            return Limit::perMinute(10)
                ->by($email . '|' . $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' =>
                            'Too many verification attempts. Please try again later.',
                    ], 429);
                });
        });

        RateLimiter::for('otp_reset', function (Request $request) {
            $email = Str::lower(
                trim((string) $request->input('email', ''))
            );

            return Limit::perMinute(5)
                ->by($email . '|' . $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' =>
                            'Too many password reset submissions. Please try again later.',
                    ], 429);
                });
        });
    }
}