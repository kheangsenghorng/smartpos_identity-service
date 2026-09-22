<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use App\Services\RbacCacheService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class RbacInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private UserDevice $device;
    private UserSession $session;
    private string $refreshSecret;
    private Role $adminRole;
    private Permission $permissionView;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->adminRole = Role::where('code', 'admin')->first();
        $this->permissionView = Permission::where('code', 'products.view')->first();

        $this->user = User::factory()->create([
            'status' => 'active',
        ]);
        $this->user->roles()->attach($this->adminRole->id);

        $this->device = UserDevice::create([
            'user_id' => $this->user->id,
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'POS Terminal',
            'device_type' => 'pos',
            'platform' => 'android',
            'is_trusted' => true,
            'is_blocked' => false,
        ]);

        $this->refreshSecret = Str::random(80);

        $this->session = UserSession::create([
            'user_id' => $this->user->id,
            'user_device_id' => $this->device->id,
            'refresh_token_hash' => Hash::make($this->refreshSecret),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'SmartPOS Terminal',
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ]);
    }

    public function test_cached_permissions_are_invalidated_when_role_permissions_change(): void
    {
        $customRole = Role::create([
            'name' => 'Custom Role',
            'code' => 'custom_role',
            'is_system' => false,
        ]);
        $customRole->permissions()->attach($this->permissionView->id);

        $testUser = User::factory()->create(['status' => 'active']);
        $testUser->roles()->attach($customRole->id);

        // 1. Prime cache
        $cachedPerms = RbacCacheService::getUserPermissionCodes($testUser);
        $this->assertEquals(['products.view'], $cachedPerms);
        $this->assertTrue(Cache::has("user:{$testUser->uuid}:permission_codes"));

        // 2. Add existing permission to role via sync
        $otherPermission = Permission::where('code', 'products.create')->first();

        $adminToken = $this->createTestSession($this->user);

        $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/v1/roles/{$customRole->uuid}/permissions", [
                'permission_uuids' => [
                    $this->permissionView->uuid,
                    $otherPermission->uuid,
                ],
            ])
            ->assertStatus(200);

        // 3. Verify user's cache was invalidated
        $this->assertFalse(Cache::has("user:{$testUser->uuid}:permission_codes"));

        // 4. Fetch fresh permissions
        $updatedPerms = RbacCacheService::getUserPermissionCodes($testUser);
        $this->assertContains('products.view', $updatedPerms);
        $this->assertContains('products.create', $updatedPerms);
    }

    public function test_cached_roles_and_permissions_are_invalidated_on_role_assignment_and_detachment(): void
    {
        // Prime cache
        $roles = RbacCacheService::getUserRoleCodes($this->user);
        $this->assertEquals(['admin'], $roles);
        $this->assertTrue(Cache::has("user:{$this->user->uuid}:role_codes"));

        $cashierRole = Role::where('code', 'cashier')->first();

        $userToken = $this->createTestSession($this->user);

        // Assign cashier role
        $this->withHeader('Authorization', 'Bearer ' . $userToken)
            ->postJson("/api/v1/users/{$this->user->uuid}/roles", [
                'role_uuid' => $cashierRole->uuid,
            ])
            ->assertStatus(200);

        // Verify cache was cleared
        $this->assertFalse(Cache::has("user:{$this->user->uuid}:role_codes"));
        $newRoles = RbacCacheService::getUserRoleCodes($this->user);
        $this->assertContains('admin', $newRoles);
        $this->assertContains('cashier', $newRoles);

        // Detach Cashier role
        $this->withHeader('Authorization', 'Bearer ' . $userToken)
            ->deleteJson("/api/v1/users/{$this->user->uuid}/roles/{$cashierRole->uuid}")
            ->assertStatus(200);

        $this->assertFalse(Cache::has("user:{$this->user->uuid}:role_codes"));
        $finalRoles = RbacCacheService::getUserRoleCodes($this->user);
        $this->assertEquals(['admin'], $finalRoles);
    }

    public function test_detaching_critical_admin_role_revokes_active_sessions(): void
    {
        $this->assertNull($this->session->fresh()->revoked_at);
        $userToken = $this->createTestSession($this->user);

        $this->withHeader('Authorization', 'Bearer ' . $userToken)
            ->deleteJson("/api/v1/users/{$this->user->uuid}/roles/{$this->adminRole->uuid}")
            ->assertStatus(200);

        // The active session must be revoked immediately
        $this->assertNotNull($this->session->fresh()->revoked_at);
    }

    public function test_refresh_endpoint_rebuilds_updated_roles_and_permissions(): void
    {
        $cashierRole = Role::where('code', 'cashier')->first();
        $this->user->roles()->sync([$cashierRole->id]);
        $this->user->clearRbacCache();

        $refreshToken = "{$this->session->uuid}.{$this->refreshSecret}";

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'roles',
                'permissions',
            ]);

        $data = $response->json();
        $this->assertEquals(['cashier'], $data['roles']);
        $this->assertContains('pos.access', $data['permissions']);
        $this->assertContains('products.view', $data['permissions']);
    }

    public function test_refresh_endpoint_rejects_when_device_is_blocked(): void
    {
        $this->device->update(['is_blocked' => true]);

        $refreshToken = "{$this->session->uuid}.{$this->refreshSecret}";

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Device is blocked.',
            ]);

        // Session must be marked revoked
        $this->assertNotNull($this->session->fresh()->revoked_at);
    }

    public function test_refresh_endpoint_rejects_when_user_is_blocked_or_inactive(): void
    {
        $this->user->update(['status' => 'blocked']);

        $refreshToken = "{$this->session->uuid}.{$this->refreshSecret}";

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Account is not active.',
            ]);

        // Session must be marked revoked
        $this->assertNotNull($this->session->fresh()->revoked_at);
    }
}
