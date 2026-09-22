<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoleProvisionService
{
    /**
     * Standard hierarchical role templates categorized by module.
     */
    public static function getModuleRoleTemplates(): array
    {
        return [
            'inventory' => [
                'Inventory Admin' => [
                    'code' => 'inventory_admin',
                    'permissions' => [
                        'dashboard.view', 'inventory.view', 'inventory.update', 'inventory.admin',
                        'inventory.warehouses.view', 'inventory.warehouses.manage',
                        'inventory.stock.transfer', 'inventory.stock.count',
                        'inventory.procurement.view', 'inventory.procurement.order', 'inventory.procurement.receive',
                        'inventory.audits.view', 'inventory.audits.reconcile',
                        'inventory.fulfillment.view', 'inventory.fulfillment.pack', 'inventory.fulfillment.ship',
                        'products.view', 'products.create', 'products.update', 'products.delete',
                        'categories.view', 'categories.create', 'categories.update', 'categories.delete',
                        'brands.view', 'brands.create', 'brands.update', 'brands.delete',
                        'units.view', 'units.create', 'units.update', 'units.delete',
                        'product_codes.view', 'product_codes.create', 'product_codes.delete',
                        'product_prices.view', 'product_prices.create', 'product_prices.update',
                        'product_images.view', 'product_images.create', 'product_images.delete',
                        'labels.view', 'labels.print', 'labels.manage',
                    ],
                ],
                'Inventory Manager' => [
                    'code' => 'inventory_manager',
                    'permissions' => [
                        'inventory.view', 'inventory.update', 'inventory.warehouses.view', 'inventory.stock.transfer',
                        'inventory.stock.count', 'inventory.procurement.view', 'inventory.procurement.order', 'inventory.procurement.receive',
                        'inventory.audits.view', 'inventory.fulfillment.view', 'inventory.fulfillment.pack', 'inventory.fulfillment.ship',
                        'products.view', 'products.create', 'products.update', 'categories.view', 'brands.view', 'units.view',
                        'product_codes.view', 'product_prices.view', 'labels.view', 'labels.print',
                    ],
                ],
                'Warehouse Staff / Operator' => [
                    'code' => 'warehouse_operator',
                    'permissions' => [
                        'inventory.view', 'inventory.warehouses.view', 'inventory.stock.transfer', 'inventory.stock.count',
                        'inventory.procurement.receive', 'inventory.fulfillment.view', 'inventory.fulfillment.pack', 'inventory.fulfillment.ship',
                        'products.view', 'product_codes.view', 'labels.print',
                    ],
                ],
                'Planner / Auditor' => [
                    'code' => 'planner_auditor',
                    'permissions' => [
                        'inventory.view', 'inventory.stock.count', 'inventory.audits.view', 'inventory.audits.reconcile',
                        'inventory.procurement.view', 'products.view', 'product_codes.view', 'product_prices.view',
                    ],
                ],
                'Purchasing / Procurement' => [
                    'code' => 'purchasing_procurement',
                    'permissions' => [
                        'inventory.view', 'inventory.procurement.view', 'inventory.procurement.order', 'inventory.procurement.receive',
                        'products.view', 'product_codes.view', 'product_prices.view', 'brands.view',
                    ],
                ],
                'Sales / Order Fulfillment' => [
                    'code' => 'order_fulfillment',
                    'permissions' => [
                        'inventory.view', 'inventory.fulfillment.view', 'inventory.fulfillment.pack', 'inventory.fulfillment.ship',
                        'products.view', 'labels.print',
                    ],
                ],
            ],

            'finance' => [
                'Finance Admin' => [
                    'code' => 'finance_admin',
                    'permissions' => [
                        'dashboard.view', 'finance.admin', 'finance.dashboard.view', 'finance.accounts.view', 'finance.accounts.manage',
                        'finance.gl.view', 'finance.gl.post', 'finance.gl.close_period', 'finance.treasury.view',
                        'finance.treasury.manage_cash', 'finance.treasury.transfer', 'finance.reports.view', 'finance.reports.export',
                        'finance.ar.view', 'finance.ar.invoice', 'finance.ar.collect', 'finance.ap.view', 'finance.ap.bills',
                        'finance.ap.approve', 'finance.ap.pay', 'finance.audits.view',
                    ],
                ],
                'Treasury / Cash Management' => [
                    'code' => 'treasury_cash_management',
                    'permissions' => [
                        'finance.dashboard.view', 'finance.treasury.view', 'finance.treasury.manage_cash',
                        'finance.treasury.transfer', 'finance.reports.view',
                    ],
                ],
                'Financial Reporting / Analyst' => [
                    'code' => 'financial_analyst',
                    'permissions' => [
                        'finance.dashboard.view', 'finance.accounts.view', 'finance.gl.view',
                        'finance.reports.view', 'finance.reports.export', 'finance.ar.view', 'finance.ap.view',
                    ],
                ],
                'AR Manager' => [
                    'code' => 'ar_manager',
                    'permissions' => [
                        'finance.accounts.view', 'finance.ar.view', 'finance.ar.invoice',
                        'finance.ar.collect', 'finance.reports.view',
                    ],
                ],
                'Auditor' => [
                    'code' => 'finance_auditor',
                    'permissions' => [
                        'finance.accounts.view', 'finance.gl.view', 'finance.reports.view',
                        'finance.audits.view', 'finance.ar.view', 'finance.ap.view',
                    ],
                ],
                'AP Clerk' => [
                    'code' => 'ap_clerk',
                    'permissions' => [
                        'finance.ap.view', 'finance.ap.bills',
                    ],
                ],
                'General Ledger Accountant' => [
                    'code' => 'general_ledger_accountant',
                    'permissions' => [
                        'finance.accounts.view', 'finance.gl.view', 'finance.gl.post',
                        'finance.reports.view',
                    ],
                ],
                'Controller' => [
                    'code' => 'controller',
                    'permissions' => [
                        'finance.dashboard.view', 'finance.accounts.view', 'finance.gl.view', 'finance.gl.post',
                        'finance.gl.close_period', 'finance.treasury.view', 'finance.reports.view', 'finance.reports.export',
                        'finance.ar.view', 'finance.ap.view', 'finance.ap.approve',
                    ],
                ],
                'AP Approver / Manager' => [
                    'code' => 'ap_approver',
                    'permissions' => [
                        'finance.ap.view', 'finance.ap.bills', 'finance.ap.approve', 'finance.ap.pay',
                        'finance.reports.view',
                    ],
                ],
                'AR Clerk' => [
                    'code' => 'ar_clerk',
                    'permissions' => [
                        'finance.ar.view', 'finance.ar.invoice', 'finance.ar.collect',
                    ],
                ],
            ],

            'pos' => [
                'POS Admin' => [
                    'code' => 'pos_admin',
                    'permissions' => [
                        'dashboard.view', 'pos.admin', 'pos.access', 'pos.checkout', 'pos.refund', 'pos.void',
                        'pos.discount', 'pos.shifts.open', 'pos.shifts.close', 'pos.shifts.view',
                        'pos.reports.view', 'pos.reports.export', 'registers.view', 'registers.create',
                        'registers.update', 'registers.manage', 'pos_devices.view', 'pos_devices.create',
                        'pos_devices.update', 'pos_devices.manage', 'pos_pin.view', 'pos_pin.update',
                        'pos_pin.verify', 'pos_pin.manage', 'products.view', 'categories.view', 'brands.view',
                    ],
                ],
                'Store Manager' => [
                    'code' => 'store_manager',
                    'permissions' => [
                        'dashboard.view', 'users.view', 'users.create', 'users.update', 'users.manage',
                        'roles.view', 'pos_pin.view', 'pos_pin.update', 'pos_pin.verify', 'pos_pin.manage',
                        'devices.view', 'devices.trust', 'devices.block', 'devices.manage',
                        'sessions.view', 'sessions.revoke', 'login_attempts.view',
                        'pos.access', 'pos.checkout', 'pos.refund', 'pos.void', 'pos.discount',
                        'pos.shifts.open', 'pos.shifts.close', 'pos.shifts.view', 'pos.reports.view', 'pos.reports.export',
                        'inventory.view', 'inventory.update', 'businesses.view', 'business_users.view',
                        'outlets.view', 'outlets.update', 'registers.view', 'registers.create',
                        'registers.update', 'registers.manage', 'pos_devices.view', 'pos_devices.create',
                        'pos_devices.update', 'pos_devices.manage', 'products.view', 'products.create',
                        'products.update', 'products.delete', 'categories.view', 'categories.create',
                        'brands.view', 'brands.create', 'units.view', 'units.create',
                        'product_codes.view', 'product_codes.create', 'product_prices.view', 'product_prices.update',
                        'product_images.view', 'labels.view', 'labels.print', 'labels.manage',
                    ],
                ],
                'Cashier' => [
                    'code' => 'cashier',
                    'permissions' => [
                        'pos.access', 'pos.checkout', 'pos.shifts.open', 'pos.shifts.close',
                        'pos_pin.verify', 'registers.view', 'pos_devices.view',
                        'products.view', 'categories.view', 'brands.view', 'units.view',
                        'product_codes.view', 'product_prices.view', 'product_images.view',
                        'labels.view', 'labels.print',
                    ],
                ],
                'Inventory / Stock Clerk' => [
                    'code' => 'inventory_clerk',
                    'permissions' => [
                        'dashboard.view', 'inventory.view', 'inventory.update', 'outlets.view',
                        'products.view', 'products.create', 'products.update',
                        'categories.view', 'categories.create', 'brands.view', 'brands.create',
                        'units.view', 'units.create', 'product_codes.view', 'product_codes.create',
                        'product_prices.view', 'product_images.view', 'labels.view', 'labels.print',
                    ],
                ],
                'Accountant / Read-only Reporting' => [
                    'code' => 'reporting_accountant',
                    'permissions' => [
                        'pos.shifts.view', 'pos.reports.view', 'pos.reports.export',
                        'registers.view', 'products.view',
                    ],
                ],
                'Shift Supervisor' => [
                    'code' => 'shift_supervisor',
                    'permissions' => [
                        'pos.access', 'pos.checkout', 'pos.refund', 'pos.void', 'pos.discount',
                        'pos.shifts.open', 'pos.shifts.close', 'pos.shifts.view',
                        'pos.reports.view', 'pos_pin.verify', 'registers.view', 'pos_devices.view',
                        'products.view', 'categories.view', 'product_prices.view',
                    ],
                ],
            ],

            'hr' => [
                'HR Admin' => [
                    'code' => 'hr_admin',
                    'permissions' => [
                        'dashboard.view', 'hr.admin', 'hr.dashboard.view', 'hr.employees.view', 'hr.employees.create',
                        'hr.employees.update', 'hr.employees.delete', 'hr.payroll.view', 'hr.payroll.manage',
                        'hr.payroll.process', 'hr.payroll.approve', 'hr.benefits.view', 'hr.benefits.manage',
                        'hr.recruitment.view', 'hr.recruitment.post', 'hr.recruitment.hire',
                        'hr.compliance.view', 'hr.compliance.manage', 'hr.teams.view', 'hr.teams.manage',
                        'hr.self_service.view_payslip', 'hr.self_service.request_leave', 'hr.self_service.clock_in',
                        'users.view', 'users.create', 'users.update', 'business_users.view', 'business_users.manage',
                    ],
                ],
                'Benefits Administrator' => [
                    'code' => 'benefits_administrator',
                    'permissions' => [
                        'hr.employees.view', 'hr.benefits.view', 'hr.benefits.manage',
                    ],
                ],
                'HR Manager / Director' => [
                    'code' => 'hr_manager',
                    'permissions' => [
                        'hr.dashboard.view', 'hr.employees.view', 'hr.employees.create', 'hr.employees.update',
                        'hr.payroll.view', 'hr.payroll.manage', 'hr.benefits.view', 'hr.benefits.manage',
                        'hr.recruitment.view', 'hr.recruitment.post', 'hr.recruitment.hire',
                        'hr.compliance.view', 'hr.teams.view', 'hr.teams.manage',
                    ],
                ],
                'Payroll Administrator' => [
                    'code' => 'payroll_admin',
                    'permissions' => [
                        'hr.employees.view', 'hr.payroll.view', 'hr.payroll.manage', 'hr.payroll.process',
                    ],
                ],
                'HR Generalist / Coordinator' => [
                    'code' => 'hr_generalist',
                    'permissions' => [
                        'hr.employees.view', 'hr.employees.create', 'hr.employees.update',
                        'hr.benefits.view', 'hr.recruitment.view', 'hr.teams.view',
                    ],
                ],
                'People Manager (scoped to reports)' => [
                    'code' => 'people_manager',
                    'permissions' => [
                        'hr.employees.view', 'hr.teams.view', 'hr.teams.manage',
                        'hr.self_service.view_payslip', 'hr.self_service.request_leave', 'hr.self_service.clock_in',
                    ],
                ],
                'Recruiter / Talent Acquisition' => [
                    'code' => 'recruiter',
                    'permissions' => [
                        'hr.employees.view', 'hr.recruitment.view', 'hr.recruitment.post', 'hr.recruitment.hire',
                    ],
                ],
                'Compliance / Legal Officer' => [
                    'code' => 'compliance_officer',
                    'permissions' => [
                        'hr.employees.view', 'hr.compliance.view', 'hr.compliance.manage',
                    ],
                ],
                'Employee Self-Service' => [
                    'code' => 'employee_self_service',
                    'permissions' => [
                        'hr.self_service.view_payslip', 'hr.self_service.request_leave', 'hr.self_service.clock_in',
                    ],
                ],
            ],
        ];
    }

    /**
     * Standard role templates and their default permission mappings (flat array for backward compatibility).
     */
    public static function getStandardRoleTemplates(?string $module = null): array
    {
        $modules = self::getModuleRoleTemplates();

        if ($module && isset($modules[strtolower($module)])) {
            return $modules[strtolower($module)];
        }

        // Merge all module templates
        $all = [
            'Owner' => [
                'code' => 'owner',
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
        ];

        foreach ($modules as $modTemplates) {
            foreach ($modTemplates as $name => $config) {
                $all[$name] = $config;
            }
        }

        return $all;
    }

    /**
     * Auto-provision standard roles and permissions for a business.
     */
    public function provisionForBusiness(string $businessUuid, ?string $module = null): Collection
    {
        $templates = self::getStandardRoleTemplates($module);
        $provisionedRoles = collect();

        foreach ($templates as $roleName => $config) {
            $role = Role::firstOrCreate(
                [
                    'business_uuid' => $businessUuid,
                    'code' => $config['code'],
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $roleName,
                    'is_system' => false,
                ]
            );

            $role->update([
                'name' => $roleName,
                'is_system' => false,
            ]);

            // Sync predefined permissions
            $permissionIds = Permission::whereIn('code', $config['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);

            // Invalidate Redis cache
            RbacCacheService::forgetRoleUsersCache($role);

            $provisionedRoles->push($role->fresh('permissions'));
        }

        RbacCacheService::forgetRolesListCache($businessUuid);

        return $provisionedRoles;
    }
}
