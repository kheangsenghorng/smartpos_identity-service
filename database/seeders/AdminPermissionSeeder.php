<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\RbacCacheService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure baseline permissions exist in database
        if (Permission::count() === 0) {
            $this->call(RoleAndPermissionSeeder::class);
            return;
        }

        // 2. Ensure admin role exists
        $adminRole = Role::query()->firstOrCreate(
            ['code' => 'admin'],
            [
                'name' => 'System Administrator',
                'module' => 'system',
                'level' => 95,
                'uuid' => (string) Str::uuid(),
                'is_system' => true,
            ]
        );

        // 3. Synchronize all permissions to admin
        $permissionIds = Permission::query()->pluck('id');
        $adminRole->permissions()->sync($permissionIds);

        // 4. Invalidate RBAC caches
        RbacCacheService::forgetRolesListCache();
        RbacCacheService::forgetRoleUsersCache($adminRole);
    }
}