<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityEventControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active']);
        $adminRole = Role::where('code', 'admin')->first();
        $this->admin->roles()->attach($adminRole->id);

        $this->adminToken = $this->createTestSession($this->admin);
    }

    public function test_can_list_and_filter_security_events(): void
    {
        $userUuid = (string) Str::uuid();
        $businessUuid = (string) Str::uuid();

        SecurityEvent::log(
            eventType: 'ROLE_ASSIGNED',
            userUuid: $userUuid,
            businessUuid: $businessUuid,
            metadata: ['role' => 'store_manager'],
            severity: 'info'
        );

        SecurityEvent::log(
            eventType: 'LOGIN_FAILED',
            userUuid: null,
            businessUuid: null,
            metadata: ['ip' => '1.2.3.4'],
            severity: 'warning'
        );

        // 1. List all events
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/security-events');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, $response->json('total'));

        // 2. Filter by event_type
        $filterTypeResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/security-events?event_type=LOGIN_FAILED');

        $filterTypeResponse->assertStatus(200);
        $this->assertEquals(1, $filterTypeResponse->json('total'));
        $this->assertEquals('LOGIN_FAILED', $filterTypeResponse->json('data.0.event_type'));

        // 3. Filter by severity
        $filterSeverityResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/security-events?severity=info');

        $filterSeverityResponse->assertStatus(200);
        $this->assertEquals('ROLE_ASSIGNED', $filterSeverityResponse->json('data.0.event_type'));
    }

    public function test_can_view_single_security_event(): void
    {
        $event = SecurityEvent::log(
            eventType: 'PERMISSION_DENIED',
            userUuid: $this->admin->uuid,
            metadata: ['endpoint' => '/api/v1/secret'],
            severity: 'warning'
        );

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/security-events/{$event->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'uuid' => $event->uuid,
                    'event_type' => 'PERMISSION_DENIED',
                    'severity' => 'warning',
                ],
            ]);
    }

    public function test_security_events_requires_permission(): void
    {
        $cashier = User::factory()->create(['status' => 'active']);
        $cashierRole = Role::where('code', 'cashier')->first();
        $cashier->roles()->attach($cashierRole->id);

        $cashierToken = $this->createTestSession($cashier);

        $response = $this->withHeader('Authorization', 'Bearer ' . $cashierToken)
            ->getJson('/api/v1/security-events');

        $response->assertStatus(403);
    }

    public function test_assigning_role_triggers_security_event(): void
    {
        $targetUser = User::factory()->create();
        $cashierRole = Role::where('code', 'cashier')->first();
        $businessUuid = (string) Str::uuid();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/users/{$targetUser->uuid}/roles", [
                'role_uuid' => $cashierRole->uuid,
                'business_uuid' => $businessUuid,
            ]);

        $response->assertStatus(200);

        // Verify security event was recorded
        $event = SecurityEvent::where('event_type', 'ROLE_ASSIGNED')
            ->where('user_uuid', $targetUser->uuid)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($businessUuid, $event->business_uuid);
        $this->assertEquals('cashier', $event->metadata['role_code']);
    }
}
