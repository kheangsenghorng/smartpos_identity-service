<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\RbacCacheService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Define baseline permission matrix
        $permissions = [
            // User Management
            ['code' => 'users.view', 'name' => 'View Users', 'module' => 'users', 'description' => 'Can view users list and details'],
            ['code' => 'users.create', 'name' => 'Create Users', 'module' => 'users', 'description' => 'Can create new users'],
            ['code' => 'users.update', 'name' => 'Update Users', 'module' => 'users', 'description' => 'Can update user profiles'],
            ['code' => 'users.delete', 'name' => 'Delete Users', 'module' => 'users', 'description' => 'Can delete users'],
            ['code' => 'users.manage', 'name' => 'Full User Management', 'module' => 'users', 'description' => 'Can perform all user administrative actions'],

            // Role Management
            ['code' => 'roles.view', 'name' => 'View Roles', 'module' => 'roles', 'description' => 'Can view roles and permissions'],
            ['code' => 'roles.create', 'name' => 'Create Roles', 'module' => 'roles', 'description' => 'Can create new roles'],
            ['code' => 'roles.update', 'name' => 'Update Roles', 'module' => 'roles', 'description' => 'Can update roles'],
            ['code' => 'roles.delete', 'name' => 'Delete Roles', 'module' => 'roles', 'description' => 'Can delete roles'],
            ['code' => 'roles.manage', 'name' => 'Full Role Management', 'module' => 'roles', 'description' => 'Can perform all role administrative actions'],
            ['code' => 'user_roles.assign', 'name' => 'Assign User Roles', 'module' => 'roles', 'description' => 'Can assign roles to users'],
            ['code' => 'user_roles.remove', 'name' => 'Remove User Roles', 'module' => 'roles', 'description' => 'Can remove roles from users'],

            // Permission Management
            ['code' => 'permissions.view', 'name' => 'View Permissions', 'module' => 'permissions', 'description' => 'Can view permission lists'],
            ['code' => 'permissions.create', 'name' => 'Create Permissions', 'module' => 'permissions', 'description' => 'Can create permissions'],
            ['code' => 'permissions.update', 'name' => 'Update Permissions', 'module' => 'permissions', 'description' => 'Can update permissions'],
            ['code' => 'permissions.delete', 'name' => 'Delete Permissions', 'module' => 'permissions', 'description' => 'Can delete permissions'],

            // POS Terminal Quick PIN Management
            ['code' => 'pos_pin.view', 'name' => 'View POS PIN Status', 'module' => 'pos_pin', 'description' => 'Can view POS PIN status'],
            ['code' => 'pos_pin.update', 'name' => 'Update POS PIN', 'module' => 'pos_pin', 'description' => 'Can update cashier POS PIN'],
            ['code' => 'pos_pin.verify', 'name' => 'Verify POS PIN', 'module' => 'pos_pin', 'description' => 'Can quick-verify POS PIN at terminal'],
            ['code' => 'pos_pin.manage', 'name' => 'Full POS PIN Management', 'module' => 'pos_pin', 'description' => 'Can manage cashier PINs'],

            // Device & Session Management
            ['code' => 'devices.view', 'name' => 'View Devices', 'module' => 'devices', 'description' => 'Can view trusted devices'],
            ['code' => 'devices.trust', 'name' => 'Trust Devices', 'module' => 'devices', 'description' => 'Can mark user devices as trusted'],
            ['code' => 'devices.block', 'name' => 'Block Devices', 'module' => 'devices', 'description' => 'Can block/unblock devices'],
            ['code' => 'devices.manage', 'name' => 'Full Device Management', 'module' => 'devices', 'description' => 'Can manage device trust status'],
            ['code' => 'sessions.view', 'name' => 'View Active Sessions', 'module' => 'sessions', 'description' => 'Can view active sessions'],
            ['code' => 'sessions.revoke', 'name' => 'Revoke Sessions', 'module' => 'sessions', 'description' => 'Can terminate user sessions'],

            // Security Audit & Dashboard
            ['code' => 'dashboard.view', 'name' => 'View Dashboard', 'module' => 'dashboard', 'description' => 'Can view identity service metrics'],
            ['code' => 'login_attempts.view', 'name' => 'View Login Attempts', 'module' => 'security', 'description' => 'Can view login security logs'],

            // POS Operation Permissions
            ['code' => 'pos.access', 'name' => 'Access POS Terminal', 'module' => 'pos', 'description' => 'Can access cashier POS interface'],
            ['code' => 'pos.checkout', 'name' => 'Process Checkout', 'module' => 'pos', 'description' => 'Can process sales transactions'],
            ['code' => 'pos.refund', 'name' => 'Process Refund', 'module' => 'pos', 'description' => 'Can issue sales refunds'],

            // Inventory Permissions
            ['code' => 'inventory.view', 'name' => 'View Inventory', 'module' => 'inventory', 'description' => 'Can view store stock levels'],
            ['code' => 'inventory.update', 'name' => 'Update Inventory', 'module' => 'inventory', 'description' => 'Can adjust stock levels'],

            // Business Service - Business Management
            ['code' => 'businesses.view', 'name' => 'View Businesses', 'module' => 'businesses', 'description' => 'Can view business profiles and details'],
            ['code' => 'businesses.create', 'name' => 'Create Businesses', 'module' => 'businesses', 'description' => 'Can create new businesses'],
            ['code' => 'businesses.update', 'name' => 'Update Businesses', 'module' => 'businesses', 'description' => 'Can update business details and settings'],
            ['code' => 'businesses.delete', 'name' => 'Delete Businesses', 'module' => 'businesses', 'description' => 'Can delete businesses'],

            // Business Service - Business User Membership
            ['code' => 'business_users.view', 'name' => 'View Business Users', 'module' => 'business_users', 'description' => 'Can view users assigned to a business'],
            ['code' => 'business_users.manage', 'name' => 'Manage Business Users', 'module' => 'business_users', 'description' => 'Can add, update, suspend, or remove users in a business'],

            // Business Service - Outlets & Locations
            ['code' => 'outlets.view', 'name' => 'View Outlets', 'module' => 'outlets', 'description' => 'Can view outlet locations'],
            ['code' => 'outlets.create', 'name' => 'Create Outlets', 'module' => 'outlets', 'description' => 'Can create new outlets for a business'],
            ['code' => 'outlets.update', 'name' => 'Update Outlets', 'module' => 'outlets', 'description' => 'Can update outlet settings and details'],
            ['code' => 'outlets.delete', 'name' => 'Delete Outlets', 'module' => 'outlets', 'description' => 'Can delete outlets'],

            // Business Service - Cash Registers & Drawers
            ['code' => 'registers.view', 'name' => 'View Registers', 'module' => 'registers', 'description' => 'Can view cash registers and points of sale'],
            ['code' => 'registers.create', 'name' => 'Create Registers', 'module' => 'registers', 'description' => 'Can create new cash registers for an outlet'],
            ['code' => 'registers.update', 'name' => 'Update Registers', 'module' => 'registers', 'description' => 'Can update cash register configurations'],
            ['code' => 'registers.manage', 'name' => 'Manage Registers', 'module' => 'registers', 'description' => 'Can delete and manage cash registers'],

            // Business Service - POS Hardware Devices
            ['code' => 'pos_devices.view', 'name' => 'View POS Devices', 'module' => 'pos_devices', 'description' => 'Can view POS hardware devices and status'],
            ['code' => 'pos_devices.create', 'name' => 'Create POS Devices', 'module' => 'pos_devices', 'description' => 'Can register new POS devices for an outlet'],
            ['code' => 'pos_devices.update', 'name' => 'Update POS Devices', 'module' => 'pos_devices', 'description' => 'Can update POS device configurations'],
            ['code' => 'pos_devices.manage', 'name' => 'Manage POS Devices', 'module' => 'pos_devices', 'description' => 'Can activate, revoke, lock, and rotate secrets for POS devices'],

            // Product Service - Products & Variants
            ['code' => 'products.view', 'name' => 'View Products', 'module' => 'products', 'description' => 'Can view products, variants, and product catalog details'],
            ['code' => 'products.create', 'name' => 'Create Products', 'module' => 'products', 'description' => 'Can create new products and variants'],
            ['code' => 'products.update', 'name' => 'Update Products', 'module' => 'products', 'description' => 'Can update products and variants'],
            ['code' => 'products.delete', 'name' => 'Delete Products', 'module' => 'products', 'description' => 'Can delete products and variants'],

            // Product Service - Categories
            ['code' => 'categories.view', 'name' => 'View Categories', 'module' => 'categories', 'description' => 'Can view product categories'],
            ['code' => 'categories.create', 'name' => 'Create Categories', 'module' => 'categories', 'description' => 'Can create product categories'],
            ['code' => 'categories.update', 'name' => 'Update Categories', 'module' => 'categories', 'description' => 'Can update product categories'],
            ['code' => 'categories.delete', 'name' => 'Delete Categories', 'module' => 'categories', 'description' => 'Can delete product categories'],

            // Product Service - Brands
            ['code' => 'brands.view', 'name' => 'View Brands', 'module' => 'brands', 'description' => 'Can view product brands'],
            ['code' => 'brands.create', 'name' => 'Create Brands', 'module' => 'brands', 'description' => 'Can create product brands'],
            ['code' => 'brands.update', 'name' => 'Update Brands', 'module' => 'brands', 'description' => 'Can update product brands'],
            ['code' => 'brands.delete', 'name' => 'Delete Brands', 'module' => 'brands', 'description' => 'Can delete product brands'],

            // Product Service - Units
            ['code' => 'units.view', 'name' => 'View Units', 'module' => 'units', 'description' => 'Can view units of measurement'],
            ['code' => 'units.create', 'name' => 'Create Units', 'module' => 'units', 'description' => 'Can create units of measurement'],
            ['code' => 'units.update', 'name' => 'Update Units', 'module' => 'units', 'description' => 'Can update units of measurement'],
            ['code' => 'units.delete', 'name' => 'Delete Units', 'module' => 'units', 'description' => 'Can delete units of measurement'],

            // Product Service - Product Barcodes & Codes
            ['code' => 'product_codes.view', 'name' => 'View Product Codes', 'module' => 'product_codes', 'description' => 'Can view barcodes and SKUs'],
            ['code' => 'product_codes.create', 'name' => 'Create Product Codes', 'module' => 'product_codes', 'description' => 'Can create and generate barcodes and SKUs'],
            ['code' => 'product_codes.delete', 'name' => 'Delete Product Codes', 'module' => 'product_codes', 'description' => 'Can delete barcodes and SKUs'],

            // Product Service - Product Prices
            ['code' => 'product_prices.view', 'name' => 'View Product Prices', 'module' => 'product_prices', 'description' => 'Can view pricing tiers and history'],
            ['code' => 'product_prices.create', 'name' => 'Create Product Prices', 'module' => 'product_prices', 'description' => 'Can create product prices'],
            ['code' => 'product_prices.update', 'name' => 'Update Product Prices', 'module' => 'product_prices', 'description' => 'Can update product prices'],
            ['code' => 'product_prices.delete', 'name' => 'Delete Product Prices', 'module' => 'product_prices', 'description' => 'Can delete product prices'],

            // Product Service - Product Images
            ['code' => 'product_images.view', 'name' => 'View Product Images', 'module' => 'product_images', 'description' => 'Can view product gallery images'],
            ['code' => 'product_images.create', 'name' => 'Upload Product Images', 'module' => 'product_images', 'description' => 'Can upload product images'],
            ['code' => 'product_images.delete', 'name' => 'Delete Product Images', 'module' => 'product_images', 'description' => 'Can delete product images'],

            // Product Service - Label Templates & Barcode Printing
            ['code' => 'labels.view', 'name' => 'View Labels', 'module' => 'labels', 'description' => 'Can view label templates and print previews'],
            ['code' => 'labels.print', 'name' => 'Print Barcode Labels', 'module' => 'labels', 'description' => 'Can print product barcode labels'],
            ['code' => 'labels.manage', 'name' => 'Manage Label Templates', 'module' => 'labels', 'description' => 'Can create, update, and delete label templates'],
        ];

        foreach ($permissions as $permissionData) {
            $existing = Permission::where('code', $permissionData['code'])->first();
            Permission::updateOrCreate(
                ['code' => $permissionData['code']],
                [
                    ...$permissionData,
                    'uuid' => $existing?->uuid ?? (string) Str::uuid(),
                ]
            );
        }

        // 2. Define Standard POS Roles and Permission Matrix
        $rolesMatrix = [
            'Admin' => [
                'code' => 'admin',
                'is_system' => true,
                'permissions' => Permission::pluck('code')->all(),
            ],
            'Owner' => [
                'code' => 'owner',
                'is_system' => true,
                'permissions' => [
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
                ],
            ],
            'Store_Manager' => [
                'code' => 'store_manager',
                'is_system' => false,
                'permissions' => [
                    'dashboard.view',
                    'users.view', 'users.create', 'users.update', 'users.manage',
                    'roles.view',
                    'pos_pin.view', 'pos_pin.update', 'pos_pin.verify', 'pos_pin.manage',
                    'devices.view', 'devices.trust', 'devices.block', 'devices.manage',
                    'sessions.view', 'sessions.revoke',
                    'login_attempts.view',
                    'pos.access', 'pos.checkout', 'pos.refund',
                    'inventory.view', 'inventory.update',
                    'businesses.view',
                    'business_users.view',
                    'outlets.view', 'outlets.update',
                    'registers.view', 'registers.create', 'registers.update', 'registers.manage',
                    'pos_devices.view', 'pos_devices.create', 'pos_devices.update', 'pos_devices.manage',
                    'products.view', 'products.create', 'products.update', 'products.delete',
                    'categories.view', 'categories.create', 'categories.update', 'categories.delete',
                    'brands.view', 'brands.create', 'brands.update', 'brands.delete',
                    'units.view', 'units.create', 'units.update',
                    'product_codes.view', 'product_codes.create', 'product_codes.delete',
                    'product_prices.view', 'product_prices.create', 'product_prices.update',
                    'product_images.view', 'product_images.create', 'product_images.delete',
                    'labels.view', 'labels.print', 'labels.manage',
                ],
            ],
            'Cashier' => [
                'code' => 'cashier',
                'is_system' => false,
                'permissions' => [
                    'pos_pin.verify',
                    'pos.access',
                    'pos.checkout',
                    'registers.view',
                    'pos_devices.view',
                    'products.view',
                    'categories.view',
                    'brands.view',
                    'units.view',
                    'product_codes.view',
                    'product_prices.view',
                    'product_images.view',
                    'labels.view',
                    'labels.print',
                ],
            ],
            'Inventory_Clerk' => [
                'code' => 'inventory_clerk',
                'is_system' => false,
                'permissions' => [
                    'dashboard.view',
                    'inventory.view',
                    'inventory.update',
                    'outlets.view',
                    'products.view', 'products.create', 'products.update',
                    'categories.view', 'categories.create',
                    'brands.view', 'brands.create',
                    'units.view', 'units.create',
                    'product_codes.view', 'product_codes.create', 'product_codes.delete',
                    'product_prices.view',
                    'product_images.view', 'product_images.create',
                    'labels.view', 'labels.print',
                ],
            ],
        ];

        foreach ($rolesMatrix as $roleName => $config) {
            $role = Role::firstOrCreate(
                ['code' => $config['code']],
                [
                    'name' => $roleName,
                    'is_system' => $config['is_system'],
                    'uuid' => (string) Str::uuid(),
                ]
            );

            $role->update([
                'name' => $roleName,
                'is_system' => $config['is_system'],
            ]);

            $permissionIds = Permission::whereIn('code', $config['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);

            // Invalidate Redis cache for users with this role
            RbacCacheService::forgetRoleUsersCache($role);
        }
    }
}
