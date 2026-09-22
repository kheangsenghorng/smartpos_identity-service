<?php

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RbacCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RbacCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbac_permissions_and_roles_are_cached_in_redis()
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::create(['name' => 'Manager', 'code' => 'manager']);
        $permission = Permission::create(['name' => 'Manage Products', 'code' => 'products.manage', 'module' => 'products']);

        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        // First call populates cache
        $this->assertTrue($user->hasPermission('products.manage'));
        $this->assertTrue($user->hasRole('manager'));

        // Verify Redis cache keys exist
        $this->assertTrue(Cache::has("user:{$user->uuid}:permission_codes"));
        $this->assertTrue(Cache::has("user:{$user->uuid}:role_codes"));

        // Verify cached values
        $this->assertEquals(['products.manage'], Cache::get("user:{$user->uuid}:permission_codes"));
        $this->assertEquals(['manager'], Cache::get("user:{$user->uuid}:role_codes"));

        // Clear cache and verify key removal
        $user->clearRbacCache();
        $this->assertFalse(Cache::has("user:{$user->uuid}:permission_codes"));
        $this->assertFalse(Cache::has("user:{$user->uuid}:role_codes"));
    }

    public function test_roles_with_attached_permissions_are_cached_and_filtered_by_business_uuid()
    {
        $businessUuidA = '11111111-1111-1111-1111-111111111111';
        $businessUuidB = '22222222-2222-2222-2222-222222222222';

        $perm1 = Permission::create(['name' => 'View Products', 'code' => 'products.view', 'module' => 'products']);
        $perm2 = Permission::create(['name' => 'Create Orders', 'code' => 'orders.create', 'module' => 'orders']);

        $globalRole = Role::create(['name' => 'Global Role', 'code' => 'global_role', 'business_uuid' => null]);
        $globalRole->permissions()->attach($perm1->id);

        $roleA = Role::create(['name' => 'Business A Role', 'code' => 'biz_a_role', 'business_uuid' => $businessUuidA]);
        $roleA->permissions()->attach($perm2->id);

        $roleB = Role::create(['name' => 'Business B Role', 'code' => 'biz_b_role', 'business_uuid' => $businessUuidB]);
        $roleB->permissions()->attach([$perm1->id, $perm2->id]);

        // 1. Fetch filtered by Business A
        $resultA = RbacCacheService::getRolesWithPermissions($businessUuidA, 10, 1);
        $this->assertCount(1, $resultA->items());
        $this->assertEquals('biz_a_role', $resultA->items()[0]->code);
        $this->assertTrue($resultA->items()[0]->relationLoaded('permissions'));
        $this->assertEquals(['orders.create'], $resultA->items()[0]->permissions->pluck('code')->all());

        // Cache key for Business A should exist
        $versionA = RbacCacheService::getRolesListVersion($businessUuidA);
        $expectedKeyA = "roles:list:business:{$businessUuidA}:v{$versionA}:p1:l10";
        $this->assertTrue(Cache::has($expectedKeyA));

        // 2. Fetch all roles (business_uuid is null)
        $resultAll = RbacCacheService::getRolesWithPermissions(null, 10, 1);
        $this->assertCount(3, $resultAll->items());

        // Cache key for All roles should exist
        $versionAll = RbacCacheService::getRolesListVersion(null);
        $expectedKeyAll = "roles:list:all:v{$versionAll}:p1:l10";
        $this->assertTrue(Cache::has($expectedKeyAll));

        // 3. Second call returns identical cached data
        $cachedResultA = RbacCacheService::getRolesWithPermissions($businessUuidA, 10, 1);
        $this->assertCount(1, $cachedResultA->items());
        $this->assertEquals('biz_a_role', $cachedResultA->items()[0]->code);
    }

    public function test_roles_list_cache_invalidation_bumps_version()
    {
        $businessUuid = '33333333-3333-3333-3333-333333333333';
        $initialBizVersion = RbacCacheService::getRolesListVersion($businessUuid);
        $initialGlobalVersion = RbacCacheService::getRolesListVersion(null);

        RbacCacheService::forgetRolesListCache($businessUuid);

        $this->assertGreaterThan($initialBizVersion, RbacCacheService::getRolesListVersion($businessUuid));
        $this->assertGreaterThan($initialGlobalVersion, RbacCacheService::getRolesListVersion(null));
    }
}
