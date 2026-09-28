<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Services\RbacCacheService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'code' => 'dashboard',
                'name' => 'Dashboard & Analytics',
                'description' => 'System metrics, operational overviews, and executive dashboards',
                'icon' => 'layout-dashboard',
                'sort_order' => 10,
                'modules' => ['dashboard'],
            ],
            [
                'code' => 'users',
                'name' => 'User Management',
                'description' => 'User accounts, credential administration, and profile access',
                'icon' => 'users',
                'sort_order' => 20,
                'modules' => ['users'],
            ],
            [
                'code' => 'roles',
                'name' => 'Roles & Permissions',
                'description' => 'Role-based access control, delegation, and permission assignments',
                'icon' => 'shield',
                'sort_order' => 30,
                'modules' => ['roles', 'permissions'],
            ],
            [
                'code' => 'pos_pin',
                'name' => 'POS PIN Authentication',
                'description' => 'Cashier terminal PIN management and quick verification',
                'icon' => 'key-round',
                'sort_order' => 40,
                'modules' => ['pos_pin'],
            ],
            [
                'code' => 'devices_sessions',
                'name' => 'Devices & Sessions',
                'description' => 'Hardware trust authorization, active sessions, and security revocation',
                'icon' => 'laptop',
                'sort_order' => 50,
                'modules' => ['devices', 'sessions'],
            ],
            [
                'code' => 'security',
                'name' => 'Security & Forensics',
                'description' => 'Security audit trails, intrusion monitoring, and login attempt logs',
                'icon' => 'shield-alert',
                'sort_order' => 60,
                'modules' => ['security'],
            ],
            [
                'code' => 'businesses',
                'name' => 'Businesses & Outlets',
                'description' => 'Multi-tenant organization structures, outlet locations, and settings',
                'icon' => 'store',
                'sort_order' => 70,
                'modules' => ['businesses', 'business_users', 'business_settings', 'outlets'],
            ],
            [
                'code' => 'registers_pos',
                'name' => 'POS Registers & Hardware',
                'description' => 'Cash registers, terminal pairings, and POS hardware peripherals',
                'icon' => 'cpu',
                'sort_order' => 80,
                'modules' => ['registers', 'pos_devices'],
            ],
            [
                'code' => 'products',
                'name' => 'Products Catalog',
                'description' => 'Item catalog, categories, brands, units, pricing, barcodes, and labels',
                'icon' => 'package',
                'sort_order' => 90,
                'modules' => ['products', 'categories', 'brands', 'units', 'product_codes', 'product_prices', 'product_images', 'labels'],
            ],
            [
                'code' => 'inventory',
                'name' => 'Inventory & Warehouses',
                'description' => 'Stock levels, warehouse management, purchase orders, transfers, and counts',
                'icon' => 'boxes',
                'sort_order' => 100,
                'modules' => ['inventory'],
            ],
            [
                'code' => 'pos_terminal',
                'name' => 'Point of Sale (POS)',
                'description' => 'Cashier checkout, register shift open/close (Z-reports), refunds, and discounts',
                'icon' => 'shopping-cart',
                'sort_order' => 110,
                'modules' => ['pos'],
            ],
            [
                'code' => 'finance',
                'name' => 'Finance & Accounting',
                'description' => 'Ledger accounts, journal entries, treasury, AR/AP, and financial reports',
                'icon' => 'receipt',
                'sort_order' => 120,
                'modules' => ['finance'],
            ],
            [
                'code' => 'hr',
                'name' => 'Human Resources & Staff',
                'description' => 'Employee records, payroll batches, benefits, leave, and clock-in',
                'icon' => 'user-check',
                'sort_order' => 130,
                'modules' => ['hr'],
            ],
            [
                'code' => 'audit_logs',
                'name' => 'System Audit Logs',
                'description' => 'System-wide audit event records and archival logs',
                'icon' => 'file-text',
                'sort_order' => 140,
                'modules' => ['audit_logs'],
            ],
        ];

        foreach ($groups as $groupData) {
            $modules = $groupData['modules'];
            unset($groupData['modules']);

            $existing = PermissionGroup::where('code', $groupData['code'])->first();
            $group = PermissionGroup::updateOrCreate(
                ['code' => $groupData['code']],
                array_merge($groupData, [
                    'uuid' => $existing?->uuid ?? (string) Str::uuid(),
                    'is_active' => true,
                ])
            );

            // Link all permissions that match the modules to this group
            Permission::whereIn('module', $modules)
                ->update(['permission_group_id' => $group->id]);
        }

        // Apply clean sort_order within each group based on action type
        $permissions = Permission::all();
        foreach ($permissions as $perm) {
            $sort = match ($perm->action) {
                'view', 'index' => 10,
                'create', 'store', 'order' => 20,
                'update', 'edit', 'adjust' => 30,
                'delete', 'destroy', 'revoke', 'block' => 40,
                'manage', 'admin' => 90,
                default => 50,
            };
            $perm->update(['sort_order' => $sort]);
        }

        $this->call(PermissionResourceSeeder::class);

        RbacCacheService::forgetPermissionGroupsTreeCache();
    }
}
