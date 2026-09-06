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
     * Load RS256 JWT keys from mounted Docker secret files or storage.
     */
    protected function configureJwtKeys(): void
    {
        if (config('jwt.algo') !== 'RS256') {
            return;
        }

        $privateConfig = config('jwt.keys.private');
        $publicConfig = config('jwt.keys.public');

        // If keys are already loaded as PEM strings (e.g., from environment or cached configuration)
        if (is_string($privateConfig) && str_contains($privateConfig, '-----BEGIN') &&
            is_string($publicConfig) && str_contains($publicConfig, '-----BEGIN')) {
            return;
        }

        $privateCandidatePaths = array_values(array_filter([
            $privateConfig,
            '/run/secrets/identity-jwt/jwt-rsa-2048-private.pem',
            '/run/secrets/identity-jwt/private.pem',
            storage_path('certs/jwt-rsa-2048-private.pem'),
            storage_path('certs/private.pem'),
            '/opt/smartpos/secrets/identity-jwt/jwt-rsa-2048-private.pem',
            '/opt/smartpos/secrets/identity-jwt/private.pem',
        ]));

        $publicCandidatePaths = array_values(array_filter([
            $publicConfig,
            '/run/secrets/identity-jwt/jwt-rsa-2048-public.pem',
            '/run/secrets/identity-jwt/public.pem',
            storage_path('certs/jwt-rsa-2048-public.pem'),
            storage_path('certs/public.pem'),
            storage_path('certs/jwt-public.pem'),
            '/opt/smartpos/secrets/identity-jwt/jwt-rsa-2048-public.pem',
            '/opt/smartpos/secrets/identity-jwt/public.pem',
        ]));

        $privateFile = null;
        foreach ($privateCandidatePaths as $path) {
            $cleanPath = str_starts_with($path, 'file://') ? substr($path, 7) : $path;
            if (is_file($cleanPath) && is_readable($cleanPath)) {
                $privateFile = $cleanPath;
                break;
            }
        }

        $publicFile = null;
        foreach ($publicCandidatePaths as $path) {
            $cleanPath = str_starts_with($path, 'file://') ? substr($path, 7) : $path;
            if (is_file($cleanPath) && is_readable($cleanPath)) {
                $publicFile = $cleanPath;
                break;
            }
        }

        if (!$privateFile || !$publicFile) {
            if ($this->autoGenerateJwtKeys()) {
                return;
            }

            if ($this->app->runningInConsole()) {
                return;
            }

            if (!$privateFile) {
                throw new RuntimeException('JWT private key is missing or unreadable.');
            }

            throw new RuntimeException('JWT public key is missing or unreadable.');
        }

        $privateKey = file_get_contents($privateFile);
        $publicKey = file_get_contents($publicFile);

        if ($privateKey === false || trim($privateKey) === '') {
            throw new RuntimeException('Unable to load JWT private key.');
        }

        if ($publicKey === false || trim($publicKey) === '') {
            throw new RuntimeException('Unable to load JWT public key.');
        }

        if (!str_contains($privateKey, '-----BEGIN')) {
            throw new RuntimeException('JWT private key is not valid PEM data.');
        }

        if (!str_contains($publicKey, '-----BEGIN')) {
            throw new RuntimeException('JWT public key is not valid PEM data.');
        }

        config([
            'jwt.keys.private' => $privateKey,
            'jwt.keys.public' => $publicKey,
            'jwt.keys.passphrase' => config('jwt.keys.passphrase') ?: null,
        ]);
    }

    /**
     * Auto-generate RSA 2048 key pair if keys are missing.
     */
    protected function autoGenerateJwtKeys(): bool
    {
        try {
            $certsDir = storage_path('certs');
            if (!is_dir($certsDir)) {
                @mkdir($certsDir, 0755, true);
            }

            $privateTarget = $certsDir . '/jwt-rsa-2048-private.pem';
            $publicTarget = $certsDir . '/jwt-rsa-2048-public.pem';

            $keyPair = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            if (!$keyPair) {
                return false;
            }

            openssl_pkey_export($keyPair, $privateKey);
            $keyDetails = openssl_pkey_get_details($keyPair);
            $publicKey = $keyDetails['key'] ?? null;

            if (!$privateKey || !$publicKey) {
                return false;
            }

            if (is_dir($certsDir) && is_writable($certsDir)) {
                @file_put_contents($privateTarget, $privateKey);
                @file_put_contents($publicTarget, $publicKey);
                @file_put_contents($certsDir . '/private.pem', $privateKey);
                @file_put_contents($certsDir . '/public.pem', $publicKey);
                @chmod($privateTarget, 0600);
                @chmod($certsDir . '/private.pem', 0600);
            }

            config([
                'jwt.keys.private' => $privateKey,
                'jwt.keys.public' => $publicKey,
                'jwt.keys.passphrase' => config('jwt.keys.passphrase') ?: null,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
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