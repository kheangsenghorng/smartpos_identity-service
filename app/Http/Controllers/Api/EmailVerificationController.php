<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailLinkMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    /**
     * Send email verification link with a 15-minute expiration.
     */
    public function sendVerificationEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
            ],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        // Check if already verified
        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => 'Email address is already verified.',
                'email_verified_at' => $user->email_verified_at->toIso8601String(),
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Generate 15-Minute Temporary Signed Verification URL
        |--------------------------------------------------------------------------
        */
        $expiresAt = now()->addMinutes(15);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            $expiresAt,
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Send Verification Mail
        |--------------------------------------------------------------------------
        */
        try {
            Mail::to($user->email)->send(
                new VerifyEmailLinkMail($user, $verificationUrl, 15)
            );
        } catch (\Throwable $e) {
            Log::warning('Verification email failed to send: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }

        $response = [
            'message' => 'Verification link sent successfully. Please check your email.',
            'sent_to' => $user->email,
            'expires_in_minutes' => 15,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        if (app()->environment('local', 'testing') || config('app.debug')) {
            $response['verification_url'] = $verificationUrl;
        }

        return response()->json($response);
    }

    /**
     * Verify email address using the 15-minute signed link.
     */
    public function verify(Request $request, int|string $id, string $hash): JsonResponse|RedirectResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Validate Temporary Signature & 15-minute Expiration
        |--------------------------------------------------------------------------
        */
        if (! $request->hasValidSignature()) {
            if (! $request->expectsJson() && $request->isMethod('GET')) {
                $frontendUrl = config('app.frontend_url', 'http://localhost:3000') . '/auth/login?verification_error=expired';
                return redirect($frontendUrl);
            }

            return response()->json([
                'message' => 'Invalid or expired verification link. Verification links expire after 15 minutes.',
                'code' => 'LINK_EXPIRED_OR_INVALID',
            ], 400);
        }

        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Hash Matches User's Current Email
        |--------------------------------------------------------------------------
        */
        if (! hash_equals((string) $hash, sha1($user->email))) {
            return response()->json([
                'message' => 'Invalid verification hash.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Set and Update email_verified_at
        |--------------------------------------------------------------------------
        */
        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
            $user->save();

            if (method_exists($user, 'clearRbacCache')) {
                $user->clearRbacCache();
            }
        }

        // If clicked from a browser directly, redirect to frontend login with success flag
        if (! $request->expectsJson() && $request->isMethod('GET')) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000') . '/auth/login?verified=true';
            return redirect($frontendUrl);
        }

        return response()->json([
            'message' => 'Email address verified successfully.',
            'email_verified_at' => $user->email_verified_at->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Check verification status of authenticated user.
     */
    public function checkStatus(): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'is_verified' => $user->email_verified_at !== null,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ]);
    }

    /**
     * Resend verification email to authenticated user.
     */
    public function resendVerificationNotification(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check if already verified
        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => 'Email address is already verified.',
                'email_verified_at' => $user->email_verified_at->toIso8601String(),
            ], 400);
        }

        $expiresAt = now()->addMinutes(15);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            $expiresAt,
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        try {
            Mail::to($user->email)->send(
                new VerifyEmailLinkMail($user, $verificationUrl, 15)
            );
        } catch (\Throwable $e) {
            Log::warning('Verification email failed to send: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }

        $response = [
            'message' => 'Verification link sent successfully. Please check your email.',
            'sent_to' => $user->email,
            'expires_in_minutes' => 15,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        if (app()->environment('local', 'testing') || config('app.debug')) {
            $response['verification_url'] = $verificationUrl;
        }

        return response()->json($response);
    }
}

