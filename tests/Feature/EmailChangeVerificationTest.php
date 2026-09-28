<?php

namespace Tests\Feature;

use App\Mail\EmailChangeVerificationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailChangeVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_request_email_change_with_current_password()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'original@example.com',
            'password' => Hash::make('Secret1234!'),
            'pending_email' => null,
            'status' => 'active',
        ]);

        $token = $this->createTestSession($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/email/change-request', [
                'email' => 'newemail@example.com',
                'password' => 'Secret1234!',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'pending_email',
                'expires_in',
                'expires_at',
            ])
            ->assertJson([
                'pending_email' => 'newemail@example.com',
                'expires_in' => 900,
            ]);

        $user->refresh();
        $this->assertEquals('newemail@example.com', $user->pending_email);
        $this->assertNotNull($user->pending_email_expires_at);
        $this->assertNotNull($user->pending_email_token);
        $this->assertEquals('original@example.com', $user->email);

        Mail::assertSent(EmailChangeVerificationMail::class, function ($mail) {
            return $mail->hasTo('newemail@example.com')
                && $mail->pendingEmail === 'newemail@example.com'
                && $mail->expiresInMinutes === 15;
        });
    }

    public function test_request_email_change_fails_if_password_is_incorrect_or_missing()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'original@example.com',
            'password' => Hash::make('Secret1234!'),
            'status' => 'active',
        ]);

        $token = $this->createTestSession($user);

        // Missing password
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/email/change-request', [
                'email' => 'newemail@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Incorrect password
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/email/change-request', [
                'email' => 'newemail@example.com',
                'password' => 'WrongPassword!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        Mail::assertNothingSent();
    }

    public function test_request_email_change_fails_if_same_as_current_email()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'current@example.com',
            'password' => Hash::make('Secret1234!'),
            'status' => 'active',
        ]);

        $token = $this->createTestSession($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/email/change-request', [
                'email' => 'current@example.com',
                'password' => 'Secret1234!',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The new email address cannot be the same as your current email.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_request_email_change_fails_if_email_already_registered_by_another_user()
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'existing@example.com',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'email' => 'me@example.com',
            'password' => Hash::make('Secret1234!'),
            'status' => 'active',
        ]);

        $token = $this->createTestSession($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/email/change-request', [
                'email' => 'existing@example.com',
                'password' => 'Secret1234!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        Mail::assertNothingSent();
    }

    public function test_verify_email_change_successfully_updates_email_and_clears_pending_fields()
    {
        $rawToken = Str::random(64);
        $expiresAt = now()->addMinutes(15);

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'brandnew@example.com',
            'pending_email_expires_at' => $expiresAt,
            'pending_email_token' => hash('sha256', $rawToken),
            'email_verified_at' => null,
            'status' => 'active',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'email.change.verify',
            $expiresAt,
            [
                'token' => $rawToken,
                'user' => $user->uuid,
            ]
        );

        $response = $this->getJson($verificationUrl);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'New email verified successfully.',
                'user' => [
                    'id' => $user->id,
                    'email' => 'brandnew@example.com',
                ],
            ]);

        $user->refresh();
        $this->assertEquals('brandnew@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_expires_at);
        $this->assertNull($user->pending_email_token);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_verify_email_change_fails_when_signature_is_expired_or_invalid()
    {
        $rawToken = Str::random(64);

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'brandnew@example.com',
            'pending_email_expires_at' => now()->subMinutes(1),
            'pending_email_token' => hash('sha256', $rawToken),
            'status' => 'active',
        ]);

        // Expired signature
        $expiredUrl = URL::temporarySignedRoute(
            'email.change.verify',
            now()->subMinutes(1),
            [
                'token' => $rawToken,
                'user' => $user->uuid,
            ]
        );

        $response = $this->getJson($expiredUrl);

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'LINK_EXPIRED_OR_INVALID',
            ]);

        $user->refresh();
        $this->assertEquals('old@example.com', $user->email);
        $this->assertEquals('brandnew@example.com', $user->pending_email);
    }

    public function test_verify_email_change_fails_when_token_does_not_match()
    {
        $rawToken = Str::random(64);
        $differentToken = Str::random(64);
        $expiresAt = now()->addMinutes(15);

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'newtarget@example.com',
            'pending_email_expires_at' => $expiresAt,
            'pending_email_token' => hash('sha256', $rawToken),
            'status' => 'active',
        ]);

        $tamperedTokenUrl = URL::temporarySignedRoute(
            'email.change.verify',
            $expiresAt,
            [
                'token' => $differentToken,
                'user' => $user->uuid,
            ]
        );

        $response = $this->getJson($tamperedTokenUrl);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Email verification request does not match.',
            ]);

        $user->refresh();
        $this->assertEquals('old@example.com', $user->email);
        $this->assertEquals('newtarget@example.com', $user->pending_email);
    }

    public function test_verify_email_change_fails_if_database_expiration_passed()
    {
        $rawToken = Str::random(64);
        // Signature might be mathematically valid if manipulated or generated for future,
        // but DB timestamp indicates it has expired:
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'brandnew@example.com',
            'pending_email_expires_at' => now()->subMinutes(5),
            'pending_email_token' => hash('sha256', $rawToken),
            'status' => 'active',
        ]);

        $validSignatureUrl = URL::temporarySignedRoute(
            'email.change.verify',
            now()->addMinutes(15),
            [
                'token' => $rawToken,
                'user' => $user->uuid,
            ]
        );

        $response = $this->getJson($validSignatureUrl);

        $response->assertStatus(410)
            ->assertJson([
                'message' => 'Verification link has expired.',
            ]);
    }

    public function test_verify_email_change_fails_if_no_pending_email_is_present()
    {
        $rawToken = Str::random(64);
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => null,
            'status' => 'active',
        ]);

        $url = URL::temporarySignedRoute(
            'email.change.verify',
            now()->addMinutes(15),
            [
                'token' => $rawToken,
                'user' => $user->uuid,
            ]
        );

        $response = $this->getJson($url);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'No pending email change found.',
            ]);
    }

    public function test_verify_email_change_fails_if_new_email_was_claimed_in_the_interim()
    {
        $rawToken = Str::random(64);
        $expiresAt = now()->addMinutes(15);

        User::factory()->create([
            'email' => 'conflicting@example.com',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'conflicting@example.com',
            'pending_email_expires_at' => $expiresAt,
            'pending_email_token' => hash('sha256', $rawToken),
            'status' => 'active',
        ]);

        $url = URL::temporarySignedRoute(
            'email.change.verify',
            $expiresAt,
            [
                'token' => $rawToken,
                'user' => $user->uuid,
            ]
        );

        $response = $this->getJson($url);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'This email address is already in use by another account.',
            ]);

        $user->refresh();
        $this->assertEquals('old@example.com', $user->email);
    }

    public function test_authenticated_user_can_resend_email_change_verification()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'current@example.com',
            'pending_email' => 'waiting@example.com',
            'pending_email_expires_at' => now()->addMinutes(5),
            'pending_email_token' => hash('sha256', 'oldtoken'),
            'status' => 'active',
        ]);

        $token = $this->createTestSession($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/email/change/resend');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Verification link resent to your new email.',
                'pending_email' => 'waiting@example.com',
            ]);

        Mail::assertSent(EmailChangeVerificationMail::class);
    }

    public function test_authenticated_user_can_cancel_pending_email_change()
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'pending_email' => 'waiting@example.com',
            'pending_email_expires_at' => now()->addMinutes(15),
            'pending_email_token' => hash('sha256', 'tok123'),
            'status' => 'active',
        ]);

        $token = $this->createTestSession($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/email/change/cancel');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Pending email change cancelled successfully.',
            ]);

        $user->refresh();
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_expires_at);
        $this->assertNull($user->pending_email_token);
    }
}
