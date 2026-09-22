<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\RbacCacheService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OwnerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure baseline permissions exist in database
        if (Permission::count() === 0) {
            $this->call(RoleAndPermissionSeeder::class);
            return;
        }

        // 2. Synchronize all system permissions to owner
        $permissionIds = Permission::query()->pluck('id');

        $role = Role::firstOrCreate(
            ['code' => 'owner'],
            [
                'name' => 'Owner',
                'module' => 'system',
                'level' => 90,
                'is_system' => true,
                'uuid' => (string) Str::uuid(),
            ]
        );

        $role->update([
            'name' => 'Owner',
            'module' => 'system',
            'level' => 90,
            'is_system' => true,
        ]);

        $role->permissions()->sync($permissionIds);

        // Invalidate Redis cache for any users with this role
        RbacCacheService::forgetRolesListCache();
        RbacCacheService::forgetRoleUsersCache($role);
    }
}
