<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RbacDelegationAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_roles_and_permissions_have_module_hierarchy_and_resource_actions(): void
    {
        // 1. Test Super Admin & Module Admin Levels
        $superAdmin = Role::where('code', 'super_admin')->first();
        $this->assertEquals(100, $superAdmin->level);
        $this->assertEquals('system', $superAdmin->module);

        $posAdmin = Role::where('code', 'pos_admin')->first();
        $this->assertEquals(80, $posAdmin->level);
        $this->assertEquals('pos', $posAdmin->module);

        $cashier = Role::where('code', 'cashier')->first();
        $this->assertEquals(20, $cashier->level);
        $this->assertEquals('pos', $cashier->module);

        // 2. Test Permission module.resource.action format
        $checkoutPerm = Permission::where('code', 'pos.checkout')->first();
        $this->assertNotNull($checkoutPerm);
        $this->assertEquals('pos', $checkoutPerm->module);
        $this->assertEquals('sales', $checkoutPerm->resource);
        $this->assertEquals('checkout', $checkoutPerm->action);

        $stockTransferPerm = Permission::where('code', 'inventory.stock.transfer')->first();
        $this->assertNotNull($stockTransferPerm);
        $this->assertEquals('inventory', $stockTransferPerm->module);
        $this->assertEquals('stock', $stockTransferPerm->resource);
        $this->assertEquals('transfer', $stockTransferPerm->action);
    }

    public function test_delegation_boundaries_enforced_by_role_assignable_roles(): void
    {
        $posAdmin = Role::where('code', 'pos_admin')->first();
        $storeManager = Role::where('code', 'store_manager')->first();
        $cashier = Role::where('code', 'cashier')->first();
        $financeAdmin = Role::where('code', 'finance_admin')->first();
        $hrAdmin = Role::where('code', 'hr_admin')->first();
        $inventoryAdmin = Role::where('code', 'inventory_admin')->first();

        // POS Admin CAN assign POS roles
        $this->assertTrue($posAdmin->canAssign($storeManager));
        $this->assertTrue($posAdmin->canAssign('cashier'));

        // POS Admin CANNOT assign cross-module roles
        $this->assertFalse($posAdmin->canAssign($financeAdmin));
        $this->assertFalse($posAdmin->canAssign($hrAdmin));
        $this->assertFalse($posAdmin->canAssign($inventoryAdmin));

        // Inventory Admin delegation check
        $inventoryAdminRole = Role::where('code', 'inventory_admin')->first();
        $warehouseOperator = Role::where('code', 'warehouse_operator')->first();
        $this->assertTrue($inventoryAdminRole->canAssign($warehouseOperator));
        $this->assertFalse($inventoryAdminRole->canAssign('cashier'));
        $this->assertFalse($inventoryAdminRole->canAssign('payroll_admin'));

        // Super Admin can assign all
        $superAdmin = Role::where('code', 'super_admin')->first();
        $this->assertTrue($superAdmin->canAssign($financeAdmin));
        $this->assertTrue($superAdmin->canAssign($posAdmin));
    }

    public function test_user_role_assignment_multi_tenant_and_outlet_scoping(): void
    {
        $user = User::factory()->create();
        $posRole = Role::where('code', 'pos_admin')->first();
        $businessUuid = (string) Str::uuid();
        $outletUuid = (string) Str::uuid();
        $granterUuid = (string) Str::uuid();

        $assignment = UserRole::create([
            'user_id' => $user->id,
            'role_id' => $posRole->id,
            'business_uuid' => $businessUuid,
            'outlet_uuid' => $outletUuid,
            'assigned_by_uuid' => $granterUuid,
            'is_active' => true,
        ]);

        $this->assertNotNull($assignment->uuid);
        $this->assertEquals($businessUuid, $assignment->business_uuid);
        $this->assertEquals($outletUuid, $assignment->outlet_uuid);
        $this->assertTrue($assignment->isCurrentlyActive());

        // Test expired role
        $expiredAssignment = UserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::where('code', 'cashier')->first()->id,
            'business_uuid' => $businessUuid,
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $this->assertFalse($expiredAssignment->isCurrentlyActive());
    }

    public function test_security_events_audit_logging(): void
    {
        $userUuid = (string) Str::uuid();
        $businessUuid = (string) Str::uuid();

        $event = SecurityEvent::log(
            eventType: 'ROLE_ASSIGNED',
            userUuid: $userUuid,
            businessUuid: $businessUuid,
            metadata: [
                'role' => 'cashier',
                'outlet_uuid' => (string) Str::uuid(),
            ],
            severity: 'info',
            ipAddress: '127.0.0.1',
            userAgent: 'SmartPOS-Test/1.0'
        );

        $this->assertNotNull($event->id);
        $this->assertNotNull($event->uuid);
        $this->assertEquals('ROLE_ASSIGNED', $event->event_type);
        $this->assertEquals($userUuid, $event->user_uuid);
        $this->assertEquals($businessUuid, $event->business_uuid);
        $this->assertEquals('cashier', $event->metadata['role']);
        $this->assertEquals('127.0.0.1', $event->ip_address);
    }
}
