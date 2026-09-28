<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\SecurityEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityEventServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_event_service_records_event_with_request_context_and_description()
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $event = SecurityEventService::record(
            eventType: SecurityEventService::LOGIN_SUCCESS,
            severity: SecurityEventService::SEVERITY_LOW,
            userUuid: $user->uuid,
            description: 'User logged in successfully.',
            metadata: ['browser' => 'Chrome'],
            sessionUuid: (string) Str::uuid(),
            deviceUuid: (string) Str::uuid()
        );

        $this->assertInstanceOf(SecurityEvent::class, $event);
        $this->assertEquals(SecurityEventService::LOGIN_SUCCESS, $event->event_type);
        $this->assertEquals(SecurityEventService::SEVERITY_LOW, $event->severity);
        $this->assertEquals($user->uuid, $event->user_uuid);
        $this->assertEquals('User logged in successfully.', $event->description);
        $this->assertEquals('Chrome', $event->metadata['browser']);
        $this->assertNotNull($event->created_at);
        $this->assertNotNull($event->uuid);
    }

    public function test_security_event_service_automatically_redacts_sensitive_metadata()
    {
        $rawMetadata = [
            'login' => 'admin@example.com',
            'password' => 'SuperSecret123!',
            'pos_pin' => '1234',
            'token' => 'jwt.access.token.secret',
            'otp' => '999888',
            'nested' => [
                'refresh_token' => 'refresh-token-value',
                'safe_key' => 'visible-value',
            ],
        ];

        $sanitized = SecurityEventService::sanitizeMetadata($rawMetadata);

        $this->assertEquals('admin@example.com', $sanitized['login']);
        $this->assertEquals('[REDACTED]', $sanitized['password']);
        $this->assertEquals('[REDACTED]', $sanitized['pos_pin']);
        $this->assertEquals('[REDACTED]', $sanitized['token']);
        $this->assertEquals('[REDACTED]', $sanitized['otp']);
        $this->assertEquals('[REDACTED]', $sanitized['nested']['refresh_token']);
        $this->assertEquals('visible-value', $sanitized['nested']['safe_key']);
    }

    public function test_security_events_api_supports_filtering_by_new_fields()
    {
        $admin = User::factory()->create(['status' => 'active']);
        $role = \App\Models\Role::firstOrCreate(
            ['code' => 'admin'],
            ['name' => 'Admin', 'uuid' => (string) Str::uuid()]
        );
        $perm = \App\Models\Permission::firstOrCreate(
            ['code' => 'security_events.view'],
            ['name' => 'View Security Events', 'uuid' => (string) Str::uuid(), 'module' => 'security']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $admin->roles()->syncWithoutDetaching([$role->id]);

        $token = $this->createTestSession($admin);

        $targetSessionUuid = (string) Str::uuid();
        $targetDeviceUuid = (string) Str::uuid();

        SecurityEventService::record(
            eventType: SecurityEventService::SESSION_REVOKED,
            severity: SecurityEventService::SEVERITY_LOW,
            userUuid: $admin->uuid,
            sessionUuid: $targetSessionUuid,
            deviceUuid: $targetDeviceUuid
        );

        SecurityEventService::record(
            eventType: SecurityEventService::DEVICE_BLOCKED,
            severity: SecurityEventService::SEVERITY_HIGH,
            userUuid: $admin->uuid,
            sessionUuid: (string) Str::uuid(),
            deviceUuid: (string) Str::uuid()
        );

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/v1/security-events?session_uuid={$targetSessionUuid}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($targetSessionUuid, $data[0]['session_uuid']);
    }
}
