<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EmailChangeVerificationMail;
use App\Models\User;
use App\Services\SecurityEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmailChangeController extends Controller
{
    /**
     * Request an email change:
     * - Validates new email format & uniqueness
     * - Requires user current password for security verification
     * - Stores pending_email, pending_email_expires_at, and hashed pending_email_token
     * - Sends 15-minute temporary signed verification link to the new email address
     */
    public function requestChange(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $data = $request->validate([
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => [
                'required',
                'current_password',
            ],
        ]);

        $newEmail = strtolower(trim($data['email']));

        if (strtolower(trim($user->email ?? '')) === $newEmail) {
            return response()->json([
                'message' => 'The new email address cannot be the same as your current email.',
            ], 422);
        }

        $token = Str::random(64);
        $expiresAt = now()->addMinutes(15);

        $user->update([
            'pending_email' => $newEmail,
            'pending_email_expires_at' => $expiresAt,
            'pending_email_token' => hash('sha256', $token),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Generate 15-Minute Temporary Signed Verification URL
        |--------------------------------------------------------------------------
        */
        $verificationUrl = URL::temporarySignedRoute(
            'email.change.verify',
            $expiresAt,
            [
                'token' => $token,
                'user' => $user->uuid,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Send Verification Link to the NEW Email Address
        |--------------------------------------------------------------------------
        */
        try {
            Mail::to($user->pending_email)->send(
                new EmailChangeVerificationMail($user, $user->pending_email, $verificationUrl, 15)
            );
        } catch (\Throwable $e) {
            Log::warning('Email change verification failed to send: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'pending_email' => $user->pending_email,
            ]);
        }

        SecurityEventService::record(
            eventType: SecurityEventService::EMAIL_CHANGE_REQUESTED,
            severity: SecurityEventService::SEVERITY_MEDIUM,
            userUuid: $user->uuid,
            description: "User requested email change to {$user->pending_email}.",
            metadata: ['pending_email' => $user->pending_email]
        );

        $response = [
            'message' => 'Verification link sent to your new email.',
            'pending_email' => $user->pending_email,
            'expires_in' => 900,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        if (app()->environment('local', 'testing') || config('app.debug')) {
            $response['verification_url'] = $verificationUrl;
        }

        return response()->json($response);
    }

    /**
     * Verify email change using the temporary signed URL with random token:
     * - Checks signature validity
     * - Checks link expiration (signature & database timestamp)
     * - Checks user match
     * - Checks token match
     * - Updates users.email, sets email_verified_at, clears pending fields
     */
    public function verify(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        // Check 1: Valid signed URL
        if (! $request->hasValidSignature()) {
            if (! $request->expectsJson() && $request->isMethod('GET')) {
                return redirect($frontendUrl . '/settings/security?email_error=expired');
            }

            return response()->json([
                'message' => 'Verification link is invalid or expired.',
                'code' => 'LINK_EXPIRED_OR_INVALID',
            ], 403);
        }

        // Check 2: Match user by user query param (uuid) or token hash
        $userUuid = $request->query('user');
        $user = $userUuid ? User::where('uuid', $userUuid)->first() : null;

        if (! $user) {
            $user = User::where('pending_email_token', hash('sha256', $token))->first();
        }

        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        // Check 3: Pending email exists
        if (! $user->pending_email) {
            if (! $request->expectsJson() && $request->isMethod('GET')) {
                return redirect($frontendUrl . '/settings/security?email_error=no_pending');
            }

            return response()->json([
                'message' => 'No pending email change found.',
            ], 422);
        }

        // Check 4: Check if link/database timestamp has expired
        if (! $user->pending_email_expires_at || now()->greaterThan($user->pending_email_expires_at)) {
            if (! $request->expectsJson() && $request->isMethod('GET')) {
                return redirect($frontendUrl . '/settings/security?email_error=expired');
            }

            return response()->json([
                'message' => 'Verification link has expired.',
            ], 410);
        }

        // Check 5: Token hash comparison
        if (! hash_equals((string) $user->pending_email_token, hash('sha256', $token))) {
            if (! $request->expectsJson() && $request->isMethod('GET')) {
                return redirect($frontendUrl . '/settings/security?email_error=invalid_token');
            }

            return response()->json([
                'message' => 'Email verification request does not match.',
            ], 403);
        }

        // Collision check: Has another account registered with this email in the interim?
        $emailConflict = User::where('email', $user->pending_email)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($emailConflict) {
            return response()->json([
                'message' => 'This email address is already in use by another account.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | Update users.email, set email_verified_at, clear pending fields
        |--------------------------------------------------------------------------
        */
        DB::transaction(function () use ($user) {
            $user->update([
                'email' => $user->pending_email,
                'email_verified_at' => now(),
                'pending_email' => null,
                'pending_email_expires_at' => null,
                'pending_email_token' => null,
            ]);
        });

        if (method_exists($user, 'clearRbacCache')) {
            $user->clearRbacCache();
        }

        SecurityEventService::record(
            eventType: SecurityEventService::EMAIL_CHANGED,
            severity: SecurityEventService::SEVERITY_LOW,
            userUuid: $user->uuid,
            description: "User primary email address successfully updated to {$user->email}.",
            metadata: ['email' => $user->email]
        );

        if (! $request->expectsJson() && $request->isMethod('GET')) {
            return redirect($frontendUrl . '/settings/security?email_changed=true');
        }

        return response()->json([
            'message' => 'New email verified successfully.',
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Resend verification link with a fresh 15-minute token.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->pending_email) {
            return response()->json([
                'message' => 'No pending email change found.',
            ], 422);
        }

        $token = Str::random(64);
        $expiresAt = now()->addMinutes(15);

        $user->update([
            'pending_email_expires_at' => $expiresAt,
            'pending_email_token' => hash('sha256', $token),
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'email.change.verify',
            $expiresAt,
            [
                'token' => $token,
                'user' => $user->uuid,
            ]
        );

        try {
            Mail::to($user->pending_email)->send(
                new EmailChangeVerificationMail($user, $user->pending_email, $verificationUrl, 15)
            );
        } catch (\Throwable $e) {
            Log::warning('Email change resend failed to send: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'pending_email' => $user->pending_email,
            ]);
        }

        $response = [
            'message' => 'Verification link resent to your new email.',
            'pending_email' => $user->pending_email,
            'expires_in' => 900,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        if (app()->environment('local', 'testing') || config('app.debug')) {
            $response['verification_url'] = $verificationUrl;
        }

        return response()->json($response);
    }

    /**
     * Cancel an active pending email change request.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user->update([
            'pending_email' => null,
            'pending_email_expires_at' => null,
            'pending_email_token' => null,
        ]);

        return response()->json([
            'message' => 'Pending email change cancelled successfully.',
        ]);
    }
}
