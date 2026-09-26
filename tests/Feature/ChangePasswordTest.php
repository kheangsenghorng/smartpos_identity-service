<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_change_password_successfully()
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
            'status' => 'active',
        ]);

        $token = $this->createTestSession($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'OldPassword123!',
                'password' => 'NewSecretPassword456!',
                'password_confirmation' => 'NewSecretPassword456!',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'password_changed_at',
                'password_changed_at_formatted',
            ])
            ->assertJson([
                'message' => 'Password changed successfully.',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecretPassword456!', $user->password));
        $this->assertFalse(Hash::check('OldPassword123!', $user->password));
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_change_password_fails_if_current_password_is_incorrect()
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
            'status' => 'active',
        ]);

        $token = $this->createTestSession($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'WrongPassword123!',
                'password' => 'NewSecretPassword456!',
                'password_confirmation' => 'NewSecretPassword456!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_change_password_fails_if_new_password_is_same_as_current()
    {
        $user = User::factory()->create([
            'password' => Hash::make('SamePassword123!'),
            'status' => 'active',
        ]);

        $token = $this->createTestSession($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'SamePassword123!',
                'password' => 'SamePassword123!',
                'password_confirmation' => 'SamePassword123!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_change_password_validates_min_length_and_confirmation()
    {
        $user = User::factory()->create([
            'password' => Hash::make('CurrentPassword123!'),
            'status' => 'active',
        ]);

        $token = $this->createTestSession($user);

        // Mismatched confirmation
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'CurrentPassword123!',
                'password' => 'Short1!',
                'password_confirmation' => 'Different1!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_change_password_revokes_other_sessions_when_requested()
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'Secondary Device',
            'device_type' => 'mobile',
            'platform' => 'iOS',
            'is_trusted' => true,
            'is_blocked' => false,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        // Current session
        $token = $this->createTestSession($user);

        // Other session
        $otherSession = UserSession::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'uuid' => (string) Str::uuid(),
            'refresh_token_hash' => Hash::make('dummy-refresh-token'),
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mobile Safari',
            'expires_at' => now()->addDays(30),
            'last_activity_at' => now(),
            'revoked_at' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'Password123!',
                'password' => 'BrandNewPassword456!',
                'password_confirmation' => 'BrandNewPassword456!',
                'logout_other_devices' => true,
            ]);

        $response->assertStatus(200);

        // Other session should be revoked
        $otherSession->refresh();
        $this->assertNotNull($otherSession->revoked_at);
    }

    public function test_unauthenticated_change_password_is_rejected()
    {
        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Password123!',
            'password' => 'BrandNewPassword456!',
            'password_confirmation' => 'BrandNewPassword456!',
        ]);

        $response->assertStatus(401);
    }
}
