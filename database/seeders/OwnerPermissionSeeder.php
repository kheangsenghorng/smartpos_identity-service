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
        $ownerPermissions = [
            'dashboard.view',
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.manage',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.manage',
            'user_roles.assign', 'user_roles.remove',
            'permissions.view',
            'pos_pin.view', 'pos_pin.update', 'pos_pin.verify', 'pos_pin.manage',
            'devices.view', 'devices.trust', 'devices.block', 'devices.manage',
            'sessions.view', 'sessions.revoke',
            'login_attempts.view',
            'pos.access', 'pos.checkout', 'pos.refund',
            'inventory.view', 'inventory.update',
            'businesses.view', 'businesses.create', 'businesses.update', 'businesses.delete',
            'business_users.view', 'business_users.manage',
            'outlets.view', 'outlets.create', 'outlets.update', 'outlets.delete',
            'registers.view', 'registers.create', 'registers.update', 'registers.manage',
            'pos_devices.view', 'pos_devices.create', 'pos_devices.update', 'pos_devices.manage',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'brands.view', 'brands.create', 'brands.update', 'brands.delete',
            'units.view', 'units.create', 'units.update', 'units.delete',
            'product_codes.view', 'product_codes.create', 'product_codes.delete',
            'product_prices.view', 'product_prices.create', 'product_prices.update', 'product_prices.delete',
            'product_images.view', 'product_images.create', 'product_images.delete',
            'labels.view', 'labels.print', 'labels.manage',
        ];

        $permissionIds = Permission::whereIn('code', $ownerPermissions)->pluck('id');

        $role = Role::firstOrCreate(
            ['code' => 'owner'],
            [
                'name' => 'Owner',
                'is_system' => true,
                'uuid' => (string) Str::uuid(),
            ]
        );

        $role->update([
            'name' => 'Owner',
            'is_system' => true,
        ]);

        $role->permissions()->sync($permissionIds);

        // Invalidate Redis cache for any users with this role
        RbacCacheService::forgetRoleUsersCache($role);
    }
}
