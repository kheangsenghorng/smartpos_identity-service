<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\PermissionResource;
use App\Models\Role;
use App\Models\User;
use App\Services\RbacCacheService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionGroupSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_permission_groups_are_seeded_and_linked_to_permissions(): void
    {
        $this->assertGreaterThan(0, PermissionGroup::count());
        $totalPermissions = Permission::count();
        $this->assertGreaterThan(0, $totalPermissions);

        // Verify all permissions have an assigned group
        $unlinkedCount = Permission::whereNull('permission_group_id')->count();
        $this->assertEquals(0, $unlinkedCount, "Found unlinked permissions without a permission_group_id");

        // Verify group hasMany relationship
        $productGroup = PermissionGroup::where('code', 'products')->first();
        $this->assertNotNull($productGroup);
        $this->assertGreaterThan(0, $productGroup->permissions()->count());
        $this->assertTrue($productGroup->permissions()->where('code', 'products.view')->exists());

        // Verify permission belongsTo relationship
        $viewPerm = Permission::where('code', 'products.view')->first();
        $this->assertNotNull($viewPerm);
        $this->assertEquals($productGroup->id, $viewPerm->permissionGroup->id);
    }

    public function test_permission_resources_are_seeded_and_linked_to_groups_and_permissions(): void
    {
        $this->assertGreaterThan(0, PermissionResource::count());

        // Verify product_details resource belongs to products group
        $resource = PermissionResource::where('code', 'product_details')->first();
        $this->assertNotNull($resource);
        $this->assertEquals('products', $resource->permissionGroup->code);

        // Verify resource hasMany permissions
        $this->assertGreaterThan(0, $resource->permissions()->count());
        $this->assertTrue($resource->permissions()->where('code', 'products.view')->exists());

        // Verify permission belongs to resource
        $perm = Permission::where('code', 'products.view')->first();
        $this->assertNotNull($perm->permissionResource);
        $this->assertEquals('product_details', $perm->permissionResource->code);
    }

    public function test_rbac_cache_service_caches_permission_groups_tree_in_redis(): void
    {
        // Flush cache before test
        RbacCacheService::forgetPermissionGroupsTreeCache();

        $tree = RbacCacheService::getPermissionGroupsTree();
        $this->assertIsArray($tree);
        $this->assertNotEmpty($tree);

        // Verify structure has permissions nested inside groups
        $firstGroup = $tree[0];
        $this->assertArrayHasKey('code', $firstGroup);
        $this->assertArrayHasKey('name', $firstGroup);
        $this->assertArrayHasKey('permissions', $firstGroup);

        // Verify versioning and invalidation
        $v1 = RbacCacheService::getPermissionGroupsTreeVersion();
        RbacCacheService::forgetPermissionGroupsTreeCache();
        $v2 = RbacCacheService::getPermissionGroupsTreeVersion();
        $this->assertGreaterThan($v1, $v2);
    }

    public function test_get_permission_groups_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/permissions/groups');
        $response->assertStatus(401);
    }

    public function test_get_permission_groups_requires_permissions_view_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = $this->createTestSession($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/permissions/groups');
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_view_permission_groups(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $adminRole = Role::where('code', 'admin')->first();
        $admin->roles()->sync([$adminRole->id]);
        $token = $this->createTestSession($admin);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/permissions/groups');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'code',
                        'name',
                        'icon',
                        'sort_order',
                        'permissions' => [
                            '*' => [
                                'id',
                                'uuid',
                                'code',
                                'name',
                                'sort_order',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_permission_groups_endpoint_blocks_sql_injection(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $adminRole = Role::where('code', 'admin')->first();
        $admin->roles()->sync([$adminRole->id]);
        $token = $this->createTestSession($admin);

        // Attempt SQL injection via query parameters or path
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/v1/permissions/' OR '1'='1");

        // Must reject or return 404, not expose database error or crash
        $this->assertContains($response->status(), [404, 422, 400]);
    }
}
