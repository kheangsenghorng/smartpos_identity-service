<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\PermissionResource;
use App\Services\RbacCacheService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionResourceSeeder extends Seeder
{
    public function run(): void
    {
        $resourceDefinitions = [
            // Products Group
            'products' => [
                ['code' => 'product_details', 'name' => 'Product Details', 'sort_order' => 10, 'permission_codes' => ['products.view', 'products.create', 'products.update', 'products.delete', 'products.manage']],
                ['code' => 'product_prices', 'name' => 'Product Prices', 'sort_order' => 20, 'permission_codes' => ['product_prices.view', 'product_prices.create', 'product_prices.update', 'product_prices.delete']],
                ['code' => 'product_images', 'name' => 'Product Images', 'sort_order' => 30, 'permission_codes' => ['product_images.view', 'product_images.create', 'product_images.delete']],
                ['code' => 'product_labels', 'name' => 'Product Labels', 'sort_order' => 40, 'permission_codes' => ['labels.view', 'labels.print', 'labels.manage']],
                ['code' => 'product_categories', 'name' => 'Product Categories', 'sort_order' => 50, 'permission_codes' => ['categories.view', 'categories.create', 'categories.update', 'categories.delete']],
                ['code' => 'product_brands', 'name' => 'Product Brands', 'sort_order' => 60, 'permission_codes' => ['brands.view', 'brands.create', 'brands.update', 'brands.delete']],
                ['code' => 'product_units', 'name' => 'Units of Measure', 'sort_order' => 70, 'permission_codes' => ['units.view', 'units.create', 'units.update', 'units.delete']],
                ['code' => 'product_codes', 'name' => 'Product Codes & Barcodes', 'sort_order' => 80, 'permission_codes' => ['product_codes.view', 'product_codes.create', 'product_codes.delete']],
            ],

            // Inventory & Procurement Group
            'inventory' => [
                ['code' => 'po_details', 'name' => 'Purchase Order Details', 'sort_order' => 10, 'permission_codes' => ['inventory.procurement.view']],
                ['code' => 'po_authorise', 'name' => 'Authorise Purchase Order', 'sort_order' => 20, 'permission_codes' => ['inventory.procurement.order']],
                ['code' => 'po_delivery', 'name' => 'Purchase Order Delivery Summary', 'sort_order' => 30, 'permission_codes' => ['inventory.procurement.receive']],
                ['code' => 'inv_stock', 'name' => 'Stock Levels & Counts', 'sort_order' => 40, 'permission_codes' => ['inventory.view', 'inventory.update', 'inventory.admin', 'inventory.stock.count']],
                ['code' => 'inv_transfers', 'name' => 'Stock Transfers', 'sort_order' => 50, 'permission_codes' => ['inventory.stock.transfer']],
                ['code' => 'inv_warehouses', 'name' => 'Warehouses & Zones', 'sort_order' => 60, 'permission_codes' => ['inventory.warehouses.view', 'inventory.warehouses.manage']],
                ['code' => 'inv_audits', 'name' => 'Inventory Audits & Reconciliation', 'sort_order' => 70, 'permission_codes' => ['inventory.audits.view', 'inventory.audits.reconcile']],
                ['code' => 'inv_fulfillment', 'name' => 'Order Fulfillment & Dispatch', 'sort_order' => 80, 'permission_codes' => ['inventory.fulfillment.view', 'inventory.fulfillment.pack', 'inventory.fulfillment.ship']],
            ],

            // Point of Sale (POS) Group
            'pos_terminal' => [
                ['code' => 'pos_access', 'name' => 'Terminal Access & Interface', 'sort_order' => 10, 'permission_codes' => ['pos.access', 'pos.admin']],
                ['code' => 'pos_checkout', 'name' => 'POS Checkout & Sales', 'sort_order' => 20, 'permission_codes' => ['pos.checkout']],
                ['code' => 'pos_refunds', 'name' => 'Refunds & Returns', 'sort_order' => 30, 'permission_codes' => ['pos.refund']],
                ['code' => 'pos_voids', 'name' => 'Void Orders & Items', 'sort_order' => 40, 'permission_codes' => ['pos.void']],
                ['code' => 'pos_discounts', 'name' => 'Manual Discounts', 'sort_order' => 50, 'permission_codes' => ['pos.discount']],
                ['code' => 'pos_shifts', 'name' => 'Register Shifts (Z-Reports)', 'sort_order' => 60, 'permission_codes' => ['pos.shifts.open', 'pos.shifts.close', 'pos.shifts.view']],
                ['code' => 'pos_reports', 'name' => 'Daily Register Turnover Reports', 'sort_order' => 70, 'permission_codes' => ['pos.reports.view', 'pos.reports.export']],
            ],

            // Finance Group
            'finance' => [
                ['code' => 'finance_dashboard', 'name' => 'Financial Executive Overview', 'sort_order' => 10, 'permission_codes' => ['finance.admin', 'finance.dashboard.view']],
                ['code' => 'finance_accounts', 'name' => 'Chart of Accounts', 'sort_order' => 20, 'permission_codes' => ['finance.accounts.view', 'finance.accounts.manage']],
                ['code' => 'finance_gl', 'name' => 'General Ledger & Journals', 'sort_order' => 30, 'permission_codes' => ['finance.gl.view', 'finance.gl.post', 'finance.gl.close_period']],
                ['code' => 'finance_treasury', 'name' => 'Treasury & Cash Flows', 'sort_order' => 40, 'permission_codes' => ['finance.treasury.view', 'finance.treasury.manage_cash', 'finance.treasury.transfer']],
                ['code' => 'finance_reports', 'name' => 'Financial Reports & P&L', 'sort_order' => 50, 'permission_codes' => ['finance.reports.view', 'finance.reports.export']],
                ['code' => 'finance_ar', 'name' => 'Accounts Receivable & Invoices', 'sort_order' => 60, 'permission_codes' => ['finance.ar.view', 'finance.ar.invoice', 'finance.ar.collect']],
                ['code' => 'finance_ap', 'name' => 'Accounts Payable & Vendor Bills', 'sort_order' => 70, 'permission_codes' => ['finance.ap.view', 'finance.ap.bills', 'finance.ap.approve', 'finance.ap.pay']],
                ['code' => 'finance_audits', 'name' => 'Financial Audits', 'sort_order' => 80, 'permission_codes' => ['finance.audits.view']],
            ],

            // Human Resources Group
            'hr' => [
                ['code' => 'hr_dashboard', 'name' => 'HR Overview & Headcount', 'sort_order' => 10, 'permission_codes' => ['hr.admin', 'hr.dashboard.view']],
                ['code' => 'hr_employees', 'name' => 'Employee Directory & Profiles', 'sort_order' => 20, 'permission_codes' => ['hr.employees.view', 'hr.employees.create', 'hr.employees.update', 'hr.employees.delete']],
                ['code' => 'hr_payroll', 'name' => 'Payroll Batches & Salaries', 'sort_order' => 30, 'permission_codes' => ['hr.payroll.view', 'hr.payroll.manage', 'hr.payroll.process', 'hr.payroll.approve']],
                ['code' => 'hr_benefits', 'name' => 'Employee Benefits & Insurance', 'sort_order' => 40, 'permission_codes' => ['hr.benefits.view', 'hr.benefits.manage']],
                ['code' => 'hr_recruitment', 'name' => 'Job Openings & Hiring', 'sort_order' => 50, 'permission_codes' => ['hr.recruitment.view', 'hr.recruitment.post', 'hr.recruitment.hire']],
                ['code' => 'hr_compliance', 'name' => 'Compliance Records & Policies', 'sort_order' => 60, 'permission_codes' => ['hr.compliance.view', 'hr.compliance.manage']],
                ['code' => 'hr_self_service', 'name' => 'Staff Self Service & Leave', 'sort_order' => 70, 'permission_codes' => ['hr.self_service.view_payslip', 'hr.self_service.request_leave', 'hr.self_service.clock_in']],
                ['code' => 'hr_teams', 'name' => 'Team Schedules & Shifts', 'sort_order' => 80, 'permission_codes' => ['hr.teams.view', 'hr.teams.manage']],
            ],

            // Users Group
            'users' => [
                ['code' => 'user_accounts', 'name' => 'User Accounts & Credentials', 'sort_order' => 10, 'permission_codes' => ['users.view', 'users.create', 'users.update', 'users.delete', 'users.manage']],
            ],

            // Roles Group
            'roles' => [
                ['code' => 'roles_management', 'name' => 'Roles & Hierarchy', 'sort_order' => 10, 'permission_codes' => ['roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.manage']],
                ['code' => 'user_roles', 'name' => 'User Role Assignments', 'sort_order' => 20, 'permission_codes' => ['user_roles.assign', 'user_roles.remove']],
                ['code' => 'permissions_catalog', 'name' => 'Permissions Catalog', 'sort_order' => 30, 'permission_codes' => ['permissions.view', 'permissions.create', 'permissions.update', 'permissions.delete']],
            ],

            // POS PIN Group
            'pos_pin' => [
                ['code' => 'pos_pin_auth', 'name' => 'Cashier POS PIN', 'sort_order' => 10, 'permission_codes' => ['pos_pin.view', 'pos_pin.update', 'pos_pin.verify', 'pos_pin.manage']],
            ],

            // Devices & Sessions Group
            'devices_sessions' => [
                ['code' => 'devices_management', 'name' => 'Trusted & Blocked Devices', 'sort_order' => 10, 'permission_codes' => ['devices.view', 'devices.trust', 'devices.block', 'devices.manage']],
                ['code' => 'sessions_management', 'name' => 'Active Sessions & Revocation', 'sort_order' => 20, 'permission_codes' => ['sessions.view', 'sessions.revoke']],
            ],

            // Security Group
            'security' => [
                ['code' => 'security_events', 'name' => 'Forensic Security Audit Events', 'sort_order' => 10, 'permission_codes' => ['security_events.view', 'security_events.export', 'security_events.manage']],
                ['code' => 'login_attempts', 'name' => 'Login Attempt Logs', 'sort_order' => 20, 'permission_codes' => ['login_attempts.view']],
            ],

            // Businesses Group
            'businesses' => [
                ['code' => 'business_profiles', 'name' => 'Business Profiles & Details', 'sort_order' => 10, 'permission_codes' => ['businesses.view', 'businesses.create', 'businesses.update', 'businesses.delete']],
                ['code' => 'business_users', 'name' => 'Business Users', 'sort_order' => 20, 'permission_codes' => ['business_users.view', 'business_users.manage']],
                ['code' => 'business_settings', 'name' => 'Business Settings', 'sort_order' => 30, 'permission_codes' => ['business_settings.view', 'business_settings.update']],
                ['code' => 'outlets', 'name' => 'Outlets & Branches', 'sort_order' => 40, 'permission_codes' => ['outlets.view', 'outlets.create', 'outlets.update', 'outlets.delete']],
            ],

            // Registers & POS Hardware Group
            'registers_pos' => [
                ['code' => 'registers', 'name' => 'Cash Registers & Tills', 'sort_order' => 10, 'permission_codes' => ['registers.view', 'registers.create', 'registers.update', 'registers.manage']],
                ['code' => 'pos_devices', 'name' => 'POS Terminals & Hardware Pairing', 'sort_order' => 20, 'permission_codes' => ['pos_devices.view', 'pos_devices.create', 'pos_devices.update', 'pos_devices.manage']],
            ],

            // Dashboard Group
            'dashboard' => [
                ['code' => 'dashboard_metrics', 'name' => 'Dashboard Overview', 'sort_order' => 10, 'permission_codes' => ['dashboard.view']],
            ],

            // System Audit Logs Group
            'audit_logs' => [
                ['code' => 'audit_logs_system', 'name' => 'System Activity Audit Trails', 'sort_order' => 10, 'permission_codes' => ['audit_logs.view', 'audit_logs.export']],
            ],
        ];

        foreach ($resourceDefinitions as $groupCode => $resources) {
            $group = PermissionGroup::where('code', $groupCode)->first();
            if (! $group) {
                continue;
            }

            foreach ($resources as $resData) {
                $permissionCodes = $resData['permission_codes'];
                unset($resData['permission_codes']);

                $existing = PermissionResource::where('code', $resData['code'])->first();
                $resource = PermissionResource::updateOrCreate(
                    ['code' => $resData['code']],
                    array_merge($resData, [
                        'uuid' => $existing?->uuid ?? (string) Str::uuid(),
                        'permission_group_id' => $group->id,
                        'is_active' => true,
                    ])
                );

                // Associate permissions with this resource
                Permission::whereIn('code', $permissionCodes)
                    ->update([
                        'permission_resource_id' => $resource->id,
                        'permission_group_id' => $group->id,
                    ]);
            }
        }

        RbacCacheService::forgetPermissionGroupsTreeCache();
    }
}
