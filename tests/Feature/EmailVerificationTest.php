<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailLinkMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_verification_email_successfully_with_15_minute_expiry()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'email_verified_at' => null,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/email/send-verification', [
            'email' => 'unverified@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'sent_to',
                'expires_in_minutes',
                'expires_at',
            ])
            ->assertJson([
                'sent_to' => 'unverified@example.com',
                'expires_in_minutes' => 15,
            ]);

        Mail::assertSent(VerifyEmailLinkMail::class, function ($mail) use ($user) {
            return $mail->hasTo('unverified@example.com') && $mail->expiresInMinutes === 15;
        });
    }

    public function test_send_verification_email_returns_400_if_already_verified()
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'verified@example.com',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/email/send-verification', [
            'email' => 'verified@example.com',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Email address is already verified.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_send_verification_email_returns_404_if_user_not_found()
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/email/send-verification', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'User not found.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_send_verification_email_returns_422_on_validation_failure()
    {
        Mail::fake();

        // Missing email
        $response = $this->postJson('/api/v1/auth/email/send-verification', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Invalid email format
        $response = $this->postJson('/api/v1/auth/email/send-verification', [
            'email' => 'not-a-valid-email',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        Mail::assertNothingSent();
    }

    public function test_verify_email_successfully_via_valid_signed_url()
    {
        $user = User::factory()->create([
            'email' => 'pending@example.com',
            'email_verified_at' => null,
            'status' => 'active',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(15),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $response = $this->getJson($verificationUrl);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Email address verified successfully.',
            ]);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_verify_email_fails_with_400_when_signature_is_expired_or_invalid()
    {
        $user = User::factory()->create([
            'email' => 'pending@example.com',
            'email_verified_at' => null,
            'status' => 'active',
        ]);

        // Expired signed URL
        $expiredUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinutes(1),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $response = $this->getJson($expiredUrl);

        $response->assertStatus(400)
            ->assertJson([
                'code' => 'LINK_EXPIRED_OR_INVALID',
            ]);
    }

    public function test_authenticated_user_can_resend_verification_notification()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'authuser@example.com',
            'email_verified_at' => null,
            'status' => 'active',
        ]);

        $token = $this->createTestSession($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/email/verification-notification');

        $response->assertStatus(200)
            ->assertJson([
                'sent_to' => 'authuser@example.com',
                'expires_in_minutes' => 15,
            ]);

        Mail::assertSent(VerifyEmailLinkMail::class);
    }

    public function test_authenticated_user_can_check_verification_status()
    {
        $user = User::factory()->create([
            'email' => 'statususer@example.com',
            'email_verified_at' => null,
            'status' => 'active',
        ]);

        $token = $this->createTestSession($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/email/verification-status');

        $response->assertStatus(200)
            ->assertJson([
                'is_verified' => false,
                'email_verified_at' => null,
            ]);

        // Mark as verified and recheck
        $user->update(['email_verified_at' => now()]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/email/verification-status');

        $response->assertStatus(200)
            ->assertJson([
                'is_verified' => true,
            ]);
    }
}
