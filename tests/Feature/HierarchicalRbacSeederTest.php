<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HierarchicalRbacSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_hierarchical_rbac_seeder_provisions_all_4_modules_and_super_admin(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        // 1. Verify Super Admin exists with ALL permissions
        $superAdmin = Role::where('code', 'super_admin')->first();
        $this->assertNotNull($superAdmin);
        $this->assertTrue($superAdmin->is_system);
        $totalPermissions = Permission::count();
        $this->assertEquals($totalPermissions, $superAdmin->permissions()->count());

        // 2. Verify Module 1: Inventory Admin and Sub-roles
        $inventoryAdmin = Role::where('code', 'inventory_admin')->first();
        $this->assertNotNull($inventoryAdmin);
        $this->assertTrue($inventoryAdmin->permissions()->where('code', 'inventory.admin')->exists());
        $this->assertTrue($inventoryAdmin->permissions()->where('code', 'inventory.warehouses.manage')->exists());
        $this->assertTrue($inventoryAdmin->permissions()->where('code', 'inventory.procurement.order')->exists());

        $invSubRoles = [
            'inventory_manager',
            'warehouse_operator',
            'planner_auditor',
            'purchasing_procurement',
            'order_fulfillment',
        ];
        foreach ($invSubRoles as $code) {
            $role = Role::where('code', $code)->first();
            $this->assertNotNull($role, "Missing Inventory sub-role: {$code}");
            $this->assertTrue($role->permissions()->where('code', 'like', 'inventory.%')->exists());
        }

        // 3. Verify Module 2: Finance Admin and Sub-roles
        $financeAdmin = Role::where('code', 'finance_admin')->first();
        $this->assertNotNull($financeAdmin);
        $this->assertTrue($financeAdmin->permissions()->where('code', 'finance.admin')->exists());
        $this->assertTrue($financeAdmin->permissions()->where('code', 'finance.gl.close_period')->exists());
        $this->assertTrue($financeAdmin->permissions()->where('code', 'finance.treasury.manage_cash')->exists());

        $finSubRoles = [
            'treasury_cash_management',
            'financial_analyst',
            'ar_manager',
            'finance_auditor',
            'ap_clerk',
            'general_ledger_accountant',
            'controller',
            'ap_approver',
            'ar_clerk',
        ];
        foreach ($finSubRoles as $code) {
            $role = Role::where('code', $code)->first();
            $this->assertNotNull($role, "Missing Finance sub-role: {$code}");
            $this->assertTrue($role->permissions()->where('code', 'like', 'finance.%')->exists());
        }

        // 4. Verify Module 3: POS Admin and Sub-roles
        $posAdmin = Role::where('code', 'pos_admin')->first();
        $this->assertNotNull($posAdmin);
        $this->assertTrue($posAdmin->permissions()->where('code', 'pos.admin')->exists());
        $this->assertTrue($posAdmin->permissions()->where('code', 'pos.access')->exists());

        $posSubRoles = [
            'cashier',
            'inventory_clerk',
            'reporting_accountant',
            'shift_supervisor',
            'store_manager',
        ];
        foreach ($posSubRoles as $code) {
            $role = Role::where('code', $code)->first();
            $this->assertNotNull($role, "Missing POS sub-role: {$code}");
        }

        // 5. Verify Module 4: HR Admin and Sub-roles
        $hrAdmin = Role::where('code', 'hr_admin')->first();
        $this->assertNotNull($hrAdmin);
        $this->assertTrue($hrAdmin->permissions()->where('code', 'hr.admin')->exists());
        $this->assertTrue($hrAdmin->permissions()->where('code', 'hr.payroll.approve')->exists());

        $hrSubRoles = [
            'benefits_administrator',
            'hr_manager',
            'payroll_admin',
            'hr_generalist',
            'people_manager',
            'recruiter',
            'compliance_officer',
            'employee_self_service',
        ];
        foreach ($hrSubRoles as $code) {
            $role = Role::where('code', $code)->first();
            $this->assertNotNull($role, "Missing HR sub-role: {$code}");
            $this->assertTrue($role->permissions()->where('code', 'like', 'hr.%')->exists());
        }
    }
}
