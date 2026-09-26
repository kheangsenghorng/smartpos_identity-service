<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RbacCacheService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleControllerTest extends TestCase
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

    public function test_roles_index_returns_cached_roles_with_permissions(): void
    {
        $businessUuid = (string) Str::uuid();

        $role = Role::create([
            'business_uuid' => $businessUuid,
            'name' => 'Custom Cashier',
            'code' => 'custom_cashier_' . Str::random(5),
            'is_system' => false,
        ]);

        $perm = Permission::where('code', 'roles.view')->first();
        $role->permissions()->attach($perm->id);

        // First call populates cache
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/roles?business_uuid={$businessUuid}");

        $response->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Custom Cashier');

        $this->assertNotEmpty($response->json('data.0.permissions'));

        // Verify cache key was set
        $version = RbacCacheService::getRolesListVersion($businessUuid);
        $expectedCacheKey = "roles:list:business:{$businessUuid}:v{$version}:p1:l20";
        $this->assertTrue(Cache::has($expectedCacheKey));

        // Second call hits cache
        $cachedResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/roles?business_uuid={$businessUuid}");

        $cachedResponse->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Custom Cashier');
    }

    public function test_creating_a_role_invalidates_roles_cache(): void
    {
        $businessUuid = (string) Str::uuid();

        // Populate cache
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/roles?business_uuid={$businessUuid}");

        $versionBefore = RbacCacheService::getRolesListVersion($businessUuid);

        // Create a new role via API
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/roles', [
                'business_uuid' => $businessUuid,
                'name' => 'Store Assistant',
                'code' => 'store_assistant',
                'is_system' => false,
            ]);

        $createResponse->assertStatus(201);

        $versionAfter = RbacCacheService::getRolesListVersion($businessUuid);
        $this->assertGreaterThan($versionBefore, $versionAfter);

        // Verify newly created role appears in the list
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/roles?business_uuid={$businessUuid}");

        $response->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Store Assistant');
    }

    public function test_can_list_users_assigned_to_a_role(): void
    {
        $ownerRole = Role::where('code', 'owner')->first();
        $ownerUser = User::factory()->create(['name' => 'Owner Alpha', 'status' => 'active']);
        $ownerUser->roles()->attach($ownerRole->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/roles/{$ownerRole->uuid}/users?all=true");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.uuid', $ownerUser->uuid)
            ->assertJsonPath('data.0.name', 'Owner Alpha');
    }

    public function test_can_filter_users_by_role_in_user_index(): void
    {
        $ownerRole = Role::where('code', 'owner')->first();
        $ownerUser = User::factory()->create(['name' => 'Owner Beta', 'status' => 'active']);
        $ownerUser->roles()->attach($ownerRole->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/users?role=owner&all=true');

        $response->assertStatus(200);
        $uuids = collect($response->json('data'))->pluck('uuid')->all();
        $this->assertContains($ownerUser->uuid, $uuids);
    }

    public function test_roles_index_can_filter_by_is_system(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/roles?is_system=1&per_page=50');

        $response->assertStatus(200);
        $roles = $response->json('data');
        $this->assertNotEmpty($roles);

        foreach ($roles as $role) {
            $this->assertTrue((bool) $role['is_system']);
        }
    }
}
