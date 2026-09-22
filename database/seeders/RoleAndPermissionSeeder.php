<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignableRole;
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
        // 1. Define baseline permission matrix across all 4 modules and core system
        $permissions = [
            // Core Identity & Security
            ['code' => 'dashboard.view', 'name' => 'View Dashboard', 'module' => 'dashboard', 'resource' => 'dashboard', 'action' => 'view', 'description' => 'Can view identity service overview metrics'],
            ['code' => 'users.view', 'name' => 'View Users', 'module' => 'users', 'resource' => 'users', 'action' => 'view', 'description' => 'Can view users list and details'],
            ['code' => 'users.create', 'name' => 'Create Users', 'module' => 'users', 'resource' => 'users', 'action' => 'create', 'description' => 'Can create new users'],
            ['code' => 'users.update', 'name' => 'Update Users', 'module' => 'users', 'resource' => 'users', 'action' => 'update', 'description' => 'Can update user profiles'],
            ['code' => 'users.delete', 'name' => 'Delete Users', 'module' => 'users', 'resource' => 'users', 'action' => 'delete', 'description' => 'Can delete users'],
            ['code' => 'users.manage', 'name' => 'Full User Management', 'module' => 'users', 'resource' => 'users', 'action' => 'manage', 'description' => 'Can perform all user administrative actions'],

            ['code' => 'roles.view', 'name' => 'View Roles', 'module' => 'roles', 'resource' => 'roles', 'action' => 'view', 'description' => 'Can view roles and permissions'],
            ['code' => 'roles.create', 'name' => 'Create Roles', 'module' => 'roles', 'resource' => 'roles', 'action' => 'create', 'description' => 'Can create new roles'],
            ['code' => 'roles.update', 'name' => 'Update Roles', 'module' => 'roles', 'resource' => 'roles', 'action' => 'update', 'description' => 'Can update roles'],
            ['code' => 'roles.delete', 'name' => 'Delete Roles', 'module' => 'roles', 'resource' => 'roles', 'action' => 'delete', 'description' => 'Can delete roles'],
            ['code' => 'roles.manage', 'name' => 'Full Role Management', 'module' => 'roles', 'resource' => 'roles', 'action' => 'manage', 'description' => 'Can perform all role administrative actions'],
            ['code' => 'user_roles.assign', 'name' => 'Assign User Roles', 'module' => 'roles', 'resource' => 'user_roles', 'action' => 'assign', 'description' => 'Can assign roles to users'],
            ['code' => 'user_roles.remove', 'name' => 'Remove User Roles', 'module' => 'roles', 'resource' => 'user_roles', 'action' => 'remove', 'description' => 'Can remove roles from users'],

            ['code' => 'permissions.view', 'name' => 'View Permissions', 'module' => 'permissions', 'resource' => 'permissions', 'action' => 'view', 'description' => 'Can view permission lists'],
            ['code' => 'permissions.create', 'name' => 'Create Permissions', 'module' => 'permissions', 'resource' => 'permissions', 'action' => 'create', 'description' => 'Can create permissions'],
            ['code' => 'permissions.update', 'name' => 'Update Permissions', 'module' => 'permissions', 'resource' => 'permissions', 'action' => 'update', 'description' => 'Can update permissions'],
            ['code' => 'permissions.delete', 'name' => 'Delete Permissions', 'module' => 'permissions', 'resource' => 'permissions', 'action' => 'delete', 'description' => 'Can delete permissions'],

            ['code' => 'pos_pin.view', 'name' => 'View POS PIN Status', 'module' => 'pos_pin', 'resource' => 'pin', 'action' => 'view', 'description' => 'Can view POS PIN status'],
            ['code' => 'pos_pin.update', 'name' => 'Update POS PIN', 'module' => 'pos_pin', 'resource' => 'pin', 'action' => 'update', 'description' => 'Can update cashier POS PIN'],
            ['code' => 'pos_pin.verify', 'name' => 'Verify POS PIN', 'module' => 'pos_pin', 'resource' => 'pin', 'action' => 'verify', 'description' => 'Can quick-verify POS PIN at terminal'],
            ['code' => 'pos_pin.manage', 'name' => 'Full POS PIN Management', 'module' => 'pos_pin', 'resource' => 'pin', 'action' => 'manage', 'description' => 'Can manage cashier PINs'],

            ['code' => 'devices.view', 'name' => 'View Devices', 'module' => 'devices', 'resource' => 'devices', 'action' => 'view', 'description' => 'Can view trusted devices'],
            ['code' => 'devices.trust', 'name' => 'Trust Devices', 'module' => 'devices', 'resource' => 'devices', 'action' => 'trust', 'description' => 'Can mark user devices as trusted'],
            ['code' => 'devices.block', 'name' => 'Block Devices', 'module' => 'devices', 'resource' => 'devices', 'action' => 'block', 'description' => 'Can block/unblock devices'],
            ['code' => 'devices.manage', 'name' => 'Full Device Management', 'module' => 'devices', 'resource' => 'devices', 'action' => 'manage', 'description' => 'Can manage device trust status'],
            ['code' => 'sessions.view', 'name' => 'View Active Sessions', 'module' => 'sessions', 'resource' => 'sessions', 'action' => 'view', 'description' => 'Can view active sessions'],
            ['code' => 'sessions.revoke', 'name' => 'Revoke Sessions', 'module' => 'sessions', 'resource' => 'sessions', 'action' => 'revoke', 'description' => 'Can terminate user sessions'],
            ['code' => 'login_attempts.view', 'name' => 'View Login Attempts', 'module' => 'security', 'resource' => 'login_attempts', 'action' => 'view', 'description' => 'Can view login security logs'],
            ['code' => 'security_events.view', 'name' => 'View Security Audit Events', 'module' => 'security', 'resource' => 'security_events', 'action' => 'view', 'description' => 'Can view forensic security audit trail logs'],

            // Business & Outlets
            ['code' => 'businesses.view', 'name' => 'View Businesses', 'module' => 'businesses', 'resource' => 'businesses', 'action' => 'view', 'description' => 'Can view business profiles and details'],
            ['code' => 'businesses.create', 'name' => 'Create Businesses', 'module' => 'businesses', 'resource' => 'businesses', 'action' => 'create', 'description' => 'Can create new businesses'],
            ['code' => 'businesses.update', 'name' => 'Update Businesses', 'module' => 'businesses', 'resource' => 'businesses', 'action' => 'update', 'description' => 'Can update business details and settings'],
            ['code' => 'businesses.delete', 'name' => 'Delete Businesses', 'module' => 'businesses', 'resource' => 'businesses', 'action' => 'delete', 'description' => 'Can delete businesses'],
            ['code' => 'business_users.view', 'name' => 'View Business Users', 'module' => 'business_users', 'resource' => 'business_users', 'action' => 'view', 'description' => 'Can view users assigned to a business'],
            ['code' => 'business_users.manage', 'name' => 'Manage Business Users', 'module' => 'business_users', 'resource' => 'business_users', 'action' => 'manage', 'description' => 'Can add, update, suspend, or remove users in a business'],

            ['code' => 'outlets.view', 'name' => 'View Outlets', 'module' => 'outlets', 'resource' => 'outlets', 'action' => 'view', 'description' => 'Can view outlet locations'],
            ['code' => 'outlets.create', 'name' => 'Create Outlets', 'module' => 'outlets', 'resource' => 'outlets', 'action' => 'create', 'description' => 'Can create new outlets for a business'],
            ['code' => 'outlets.update', 'name' => 'Update Outlets', 'module' => 'outlets', 'resource' => 'outlets', 'action' => 'update', 'description' => 'Can update outlet settings and details'],
            ['code' => 'outlets.delete', 'name' => 'Delete Outlets', 'module' => 'outlets', 'resource' => 'outlets', 'action' => 'delete', 'description' => 'Can delete outlets'],

            ['code' => 'registers.view', 'name' => 'View Registers', 'module' => 'registers', 'resource' => 'registers', 'action' => 'view', 'description' => 'Can view cash registers and points of sale'],
            ['code' => 'registers.create', 'name' => 'Create Registers', 'module' => 'registers', 'resource' => 'registers', 'action' => 'create', 'description' => 'Can create new cash registers for an outlet'],
            ['code' => 'registers.update', 'name' => 'Update Registers', 'module' => 'registers', 'resource' => 'registers', 'action' => 'update', 'description' => 'Can update cash register configurations'],
            ['code' => 'registers.manage', 'name' => 'Manage Registers', 'module' => 'registers', 'resource' => 'registers', 'action' => 'manage', 'description' => 'Can delete and manage cash registers'],

            ['code' => 'pos_devices.view', 'name' => 'View POS Devices', 'module' => 'pos_devices', 'resource' => 'pos_devices', 'action' => 'view', 'description' => 'Can view POS hardware devices and status'],
            ['code' => 'pos_devices.create', 'name' => 'Create POS Devices', 'module' => 'pos_devices', 'resource' => 'pos_devices', 'action' => 'create', 'description' => 'Can register new POS devices for an outlet'],
            ['code' => 'pos_devices.update', 'name' => 'Update POS Devices', 'module' => 'pos_devices', 'resource' => 'pos_devices', 'action' => 'update', 'description' => 'Can update POS device configurations'],
            ['code' => 'pos_devices.manage', 'name' => 'Manage POS Devices', 'module' => 'pos_devices', 'resource' => 'pos_devices', 'action' => 'manage', 'description' => 'Can activate, revoke, lock, and rotate secrets for POS devices'],

            // Products Catalog
            ['code' => 'products.view', 'name' => 'View Products', 'module' => 'products', 'resource' => 'products', 'action' => 'view', 'description' => 'Can view products, variants, and product catalog details'],
            ['code' => 'products.create', 'name' => 'Create Products', 'module' => 'products', 'resource' => 'products', 'action' => 'create', 'description' => 'Can create new products and variants'],
            ['code' => 'products.update', 'name' => 'Update Products', 'module' => 'products', 'resource' => 'products', 'action' => 'update', 'description' => 'Can update products and variants'],
            ['code' => 'products.delete', 'name' => 'Delete Products', 'module' => 'products', 'resource' => 'products', 'action' => 'delete', 'description' => 'Can delete products and variants'],
            ['code' => 'categories.view', 'name' => 'View Categories', 'module' => 'categories', 'resource' => 'categories', 'action' => 'view', 'description' => 'Can view product categories'],
            ['code' => 'categories.create', 'name' => 'Create Categories', 'module' => 'categories', 'resource' => 'categories', 'action' => 'create', 'description' => 'Can create product categories'],
            ['code' => 'categories.update', 'name' => 'Update Categories', 'module' => 'categories', 'resource' => 'categories', 'action' => 'update', 'description' => 'Can update product categories'],
            ['code' => 'categories.delete', 'name' => 'Delete Categories', 'module' => 'categories', 'resource' => 'categories', 'action' => 'delete', 'description' => 'Can delete product categories'],
            ['code' => 'brands.view', 'name' => 'View Brands', 'module' => 'brands', 'resource' => 'brands', 'action' => 'view', 'description' => 'Can view product brands'],
            ['code' => 'brands.create', 'name' => 'Create Brands', 'module' => 'brands', 'resource' => 'brands', 'action' => 'create', 'description' => 'Can create product brands'],
            ['code' => 'brands.update', 'name' => 'Update Brands', 'module' => 'brands', 'resource' => 'brands', 'action' => 'update', 'description' => 'Can update product brands'],
            ['code' => 'brands.delete', 'name' => 'Delete Brands', 'module' => 'brands', 'resource' => 'brands', 'action' => 'delete', 'description' => 'Can delete product brands'],
            ['code' => 'units.view', 'name' => 'View Units', 'module' => 'units', 'resource' => 'units', 'action' => 'view', 'description' => 'Can view units of measurement'],
            ['code' => 'units.create', 'name' => 'Create Units', 'module' => 'units', 'resource' => 'units', 'action' => 'create', 'description' => 'Can create units of measurement'],
            ['code' => 'units.update', 'name' => 'Update Units', 'module' => 'units', 'resource' => 'units', 'action' => 'update', 'description' => 'Can update units of measurement'],
            ['code' => 'units.delete', 'name' => 'Delete Units', 'module' => 'units', 'resource' => 'units', 'action' => 'delete', 'description' => 'Can delete units of measurement'],
            ['code' => 'product_codes.view', 'name' => 'View Product Codes', 'module' => 'product_codes', 'resource' => 'product_codes', 'action' => 'view', 'description' => 'Can view barcodes and SKUs'],
            ['code' => 'product_codes.create', 'name' => 'Create Product Codes', 'module' => 'product_codes', 'resource' => 'product_codes', 'action' => 'create', 'description' => 'Can create and generate barcodes and SKUs'],
            ['code' => 'product_codes.delete', 'name' => 'Delete Product Codes', 'module' => 'product_codes', 'resource' => 'product_codes', 'action' => 'delete', 'description' => 'Can delete barcodes and SKUs'],
            ['code' => 'product_prices.view', 'name' => 'View Product Prices', 'module' => 'product_prices', 'resource' => 'product_prices', 'action' => 'view', 'description' => 'Can view pricing tiers and history'],
            ['code' => 'product_prices.create', 'name' => 'Create Product Prices', 'module' => 'product_prices', 'resource' => 'product_prices', 'action' => 'create', 'description' => 'Can create product prices'],
            ['code' => 'product_prices.update', 'name' => 'Update Product Prices', 'module' => 'product_prices', 'resource' => 'product_prices', 'action' => 'update', 'description' => 'Can update product prices'],
            ['code' => 'product_prices.delete', 'name' => 'Delete Product Prices', 'module' => 'product_prices', 'resource' => 'product_prices', 'action' => 'delete', 'description' => 'Can delete product prices'],
            ['code' => 'product_images.view', 'name' => 'View Product Images', 'module' => 'product_images', 'resource' => 'product_images', 'action' => 'view', 'description' => 'Can view product gallery images'],
            ['code' => 'product_images.create', 'name' => 'Upload Product Images', 'module' => 'product_images', 'resource' => 'product_images', 'action' => 'create', 'description' => 'Can upload product images'],
            ['code' => 'product_images.delete', 'name' => 'Delete Product Images', 'module' => 'product_images', 'resource' => 'product_images', 'action' => 'delete', 'description' => 'Can delete product images'],
            ['code' => 'labels.view', 'name' => 'View Labels', 'module' => 'labels', 'resource' => 'labels', 'action' => 'view', 'description' => 'Can view label templates and print previews'],
            ['code' => 'labels.print', 'name' => 'Print Barcode Labels', 'module' => 'labels', 'resource' => 'labels', 'action' => 'print', 'description' => 'Can print product barcode labels'],
            ['code' => 'labels.manage', 'name' => 'Manage Label Templates', 'module' => 'labels', 'resource' => 'labels', 'action' => 'manage', 'description' => 'Can create, update, and delete label templates'],

            // ==========================================
            // MODULE 1: INVENTORY (inventory_service)
            // ==========================================
            ['code' => 'inventory.view', 'name' => 'View Inventory', 'module' => 'inventory', 'resource' => 'stock', 'action' => 'view', 'description' => 'Can view store stock levels'],
            ['code' => 'inventory.update', 'name' => 'Update Inventory', 'module' => 'inventory', 'resource' => 'stock', 'action' => 'update', 'description' => 'Can adjust stock levels'],
            ['code' => 'inventory.admin', 'name' => 'Inventory Module Administration', 'module' => 'inventory', 'resource' => 'inventory', 'action' => 'admin', 'description' => 'Full administrative control over inventory module'],
            ['code' => 'inventory.warehouses.view', 'name' => 'View Warehouses', 'module' => 'inventory', 'resource' => 'warehouses', 'action' => 'view', 'description' => 'Can view warehouse locations and zones'],
            ['code' => 'inventory.warehouses.manage', 'name' => 'Manage Warehouses', 'module' => 'inventory', 'resource' => 'warehouses', 'action' => 'manage', 'description' => 'Can create, edit, and configure warehouses'],
            ['code' => 'inventory.stock.transfer', 'name' => 'Transfer Stock', 'module' => 'inventory', 'resource' => 'stock', 'action' => 'transfer', 'description' => 'Can initiate and accept inter-warehouse/outlet stock transfers'],
            ['code' => 'inventory.stock.count', 'name' => 'Perform Stock Count', 'module' => 'inventory', 'resource' => 'stock', 'action' => 'count', 'description' => 'Can submit physical inventory stock counts'],
            ['code' => 'inventory.procurement.view', 'name' => 'View Purchase Orders', 'module' => 'inventory', 'resource' => 'procurement', 'action' => 'view', 'description' => 'Can view supplier purchase orders and requisitions'],
            ['code' => 'inventory.procurement.order', 'name' => 'Create Purchase Orders', 'module' => 'inventory', 'resource' => 'procurement', 'action' => 'order', 'description' => 'Can create and approve purchase orders for suppliers'],
            ['code' => 'inventory.procurement.receive', 'name' => 'Receive Goods', 'module' => 'inventory', 'resource' => 'procurement', 'action' => 'receive', 'description' => 'Can receive inbound shipments from suppliers'],
            ['code' => 'inventory.audits.view', 'name' => 'View Inventory Audits', 'module' => 'inventory', 'resource' => 'audits', 'action' => 'view', 'description' => 'Can review stock variance audit reports'],
            ['code' => 'inventory.audits.reconcile', 'name' => 'Reconcile Inventory', 'module' => 'inventory', 'resource' => 'audits', 'action' => 'reconcile', 'description' => 'Can authorize write-offs and stock reconciliations'],
            ['code' => 'inventory.fulfillment.view', 'name' => 'View Order Fulfillment', 'module' => 'inventory', 'resource' => 'fulfillment', 'action' => 'view', 'description' => 'Can view orders waiting for pick and pack'],
            ['code' => 'inventory.fulfillment.pack', 'name' => 'Pick & Pack Orders', 'module' => 'inventory', 'resource' => 'fulfillment', 'action' => 'pack', 'description' => 'Can mark order packages as picked and packed'],
            ['code' => 'inventory.fulfillment.ship', 'name' => 'Dispatch Shipments', 'module' => 'inventory', 'resource' => 'fulfillment', 'action' => 'ship', 'description' => 'Can create waybills and ship packages'],

            // ==========================================
            // MODULE 2: FINANCE (finance_service)
            // ==========================================
            ['code' => 'finance.admin', 'name' => 'Finance Module Administration', 'module' => 'finance', 'resource' => 'finance', 'action' => 'admin', 'description' => 'Full administrative control over finance module'],
            ['code' => 'finance.dashboard.view', 'name' => 'View Financial Dashboard', 'module' => 'finance', 'resource' => 'dashboard', 'action' => 'view', 'description' => 'Can view executive financial overview and metrics'],
            ['code' => 'finance.accounts.view', 'name' => 'View Chart of Accounts', 'module' => 'finance', 'resource' => 'accounts', 'action' => 'view', 'description' => 'Can view chart of accounts and balances'],
            ['code' => 'finance.accounts.manage', 'name' => 'Manage Chart of Accounts', 'module' => 'finance', 'resource' => 'accounts', 'action' => 'manage', 'description' => 'Can configure accounting ledger structures'],
            ['code' => 'finance.gl.view', 'name' => 'View General Ledger', 'module' => 'finance', 'resource' => 'gl', 'action' => 'view', 'description' => 'Can view journal entries and ledger logs'],
            ['code' => 'finance.gl.post', 'name' => 'Post Journal Entries', 'module' => 'finance', 'resource' => 'gl', 'action' => 'post', 'description' => 'Can create manual journal entries and adjustments'],
            ['code' => 'finance.gl.close_period', 'name' => 'Close Financial Periods', 'module' => 'finance', 'resource' => 'gl', 'action' => 'close_period', 'description' => 'Can lock and close monthly/annual fiscal periods'],
            ['code' => 'finance.treasury.view', 'name' => 'View Treasury & Cash', 'module' => 'finance', 'resource' => 'treasury', 'action' => 'view', 'description' => 'Can view bank accounts and cash holdings'],
            ['code' => 'finance.treasury.manage_cash', 'name' => 'Manage Cash Flows', 'module' => 'finance', 'resource' => 'treasury', 'action' => 'manage_cash', 'description' => 'Can allocate funds and manage liquidity'],
            ['code' => 'finance.treasury.transfer', 'name' => 'Execute Bank Transfers', 'module' => 'finance', 'resource' => 'treasury', 'action' => 'transfer', 'description' => 'Can authorize inter-bank transfers and deposits'],
            ['code' => 'finance.reports.view', 'name' => 'View Financial Reports', 'module' => 'finance', 'resource' => 'reports', 'action' => 'view', 'description' => 'Can view P&L, balance sheets, and cash flow reports'],
            ['code' => 'finance.reports.export', 'name' => 'Export Financial Reports', 'module' => 'finance', 'resource' => 'reports', 'action' => 'export', 'description' => 'Can export financial audits and tax filings'],
            ['code' => 'finance.ar.view', 'name' => 'View Accounts Receivable', 'module' => 'finance', 'resource' => 'ar', 'action' => 'view', 'description' => 'Can view invoices and customer credit balances'],
            ['code' => 'finance.ar.invoice', 'name' => 'Issue AR Invoices', 'module' => 'finance', 'resource' => 'ar', 'action' => 'invoice', 'description' => 'Can generate customer invoices and debit memos'],
            ['code' => 'finance.ar.collect', 'name' => 'Record AR Collections', 'module' => 'finance', 'resource' => 'ar', 'action' => 'collect', 'description' => 'Can apply received customer payments against invoices'],
            ['code' => 'finance.ap.view', 'name' => 'View Accounts Payable', 'module' => 'finance', 'resource' => 'ap', 'action' => 'view', 'description' => 'Can view vendor bills and pending obligations'],
            ['code' => 'finance.ap.bills', 'name' => 'Enter Vendor Bills', 'module' => 'finance', 'resource' => 'ap', 'action' => 'bills', 'description' => 'Can input supplier bills and credit notes'],
            ['code' => 'finance.ap.approve', 'name' => 'Approve Vendor Bills', 'module' => 'finance', 'resource' => 'ap', 'action' => 'approve', 'description' => 'Can approve supplier bills for disbursement'],
            ['code' => 'finance.ap.pay', 'name' => 'Disburse AP Payments', 'module' => 'finance', 'resource' => 'ap', 'action' => 'pay', 'description' => 'Can authorize and execute payments to suppliers'],
            ['code' => 'finance.audits.view', 'name' => 'View Financial Audits', 'module' => 'finance', 'resource' => 'audits', 'action' => 'view', 'description' => 'Can review internal and external financial audit trails'],

            // ==========================================
            // MODULE 3: POS (pos_service)
            // ==========================================
            ['code' => 'pos.admin', 'name' => 'POS Module Administration', 'module' => 'pos', 'resource' => 'pos', 'action' => 'admin', 'description' => 'Full administrative control over POS registers and terminals'],
            ['code' => 'pos.access', 'name' => 'Access POS Terminal', 'module' => 'pos', 'resource' => 'terminal', 'action' => 'access', 'description' => 'Can access cashier POS interface'],
            ['code' => 'pos.checkout', 'name' => 'Process Checkout', 'module' => 'pos', 'resource' => 'sales', 'action' => 'checkout', 'description' => 'Can process sales transactions'],
            ['code' => 'pos.refund', 'name' => 'Process Refund', 'module' => 'pos', 'resource' => 'sales', 'action' => 'refund', 'description' => 'Can issue sales refunds'],
            ['code' => 'pos.void', 'name' => 'Void Orders & Line Items', 'module' => 'pos', 'resource' => 'sales', 'action' => 'void', 'description' => 'Can void active items or complete orders'],
            ['code' => 'pos.discount', 'name' => 'Apply Manual Discount', 'module' => 'pos', 'resource' => 'sales', 'action' => 'discount', 'description' => 'Can apply manual discount overrides at checkout'],
            ['code' => 'pos.shifts.open', 'name' => 'Open Register Shift', 'module' => 'pos', 'resource' => 'shifts', 'action' => 'open', 'description' => 'Can open cashier shift and record opening float'],
            ['code' => 'pos.shifts.close', 'name' => 'Close Register Shift', 'module' => 'pos', 'resource' => 'shifts', 'action' => 'close', 'description' => 'Can reconcile and close register shifts (Z-report)'],
            ['code' => 'pos.shifts.view', 'name' => 'View Shift Reports', 'module' => 'pos', 'resource' => 'shifts', 'action' => 'view', 'description' => 'Can view active and closed register shift reports'],
            ['code' => 'pos.reports.view', 'name' => 'View POS Sales Reports', 'module' => 'pos', 'resource' => 'reports', 'action' => 'view', 'description' => 'Can view daily register turnover and sales summaries'],
            ['code' => 'pos.reports.export', 'name' => 'Export POS Reports', 'module' => 'pos', 'resource' => 'reports', 'action' => 'export', 'description' => 'Can export sales transactions and tender breakdowns'],

            // ==========================================
            // MODULE 4: HR (hr_service)
            // ==========================================
            ['code' => 'hr.admin', 'name' => 'HR Module Administration', 'module' => 'hr', 'resource' => 'hr', 'action' => 'admin', 'description' => 'Full administrative control over HR, payroll, and staff'],
            ['code' => 'hr.dashboard.view', 'name' => 'View HR Dashboard', 'module' => 'hr', 'resource' => 'dashboard', 'action' => 'view', 'description' => 'Can view headcount, attendance, and payroll overview'],
            ['code' => 'hr.employees.view', 'name' => 'View Employee Profiles', 'module' => 'hr', 'resource' => 'employees', 'action' => 'view', 'description' => 'Can view employee directory and personal files'],
            ['code' => 'hr.employees.create', 'name' => 'Create Employee Records', 'module' => 'hr', 'resource' => 'employees', 'action' => 'create', 'description' => 'Can onboard and create new employee records'],
            ['code' => 'hr.employees.update', 'name' => 'Update Employee Records', 'module' => 'hr', 'resource' => 'employees', 'action' => 'update', 'description' => 'Can edit job titles, departments, and employee info'],
            ['code' => 'hr.employees.delete', 'name' => 'Terminate/Delete Employees', 'module' => 'hr', 'resource' => 'employees', 'action' => 'delete', 'description' => 'Can offboard and deactivate employee accounts'],
            ['code' => 'hr.payroll.view', 'name' => 'View Payroll Runs', 'module' => 'hr', 'resource' => 'payroll', 'action' => 'view', 'description' => 'Can view salary structures and wage calculation drafts'],
            ['code' => 'hr.payroll.manage', 'name' => 'Manage Payroll Configurations', 'module' => 'hr', 'resource' => 'payroll', 'action' => 'manage', 'description' => 'Can configure tax rates, allowances, and deductions'],
            ['code' => 'hr.payroll.process', 'name' => 'Process Payroll Calculation', 'module' => 'hr', 'resource' => 'payroll', 'action' => 'process', 'description' => 'Can run monthly payroll batch calculations'],
            ['code' => 'hr.payroll.approve', 'name' => 'Authorize Payroll Payouts', 'module' => 'hr', 'resource' => 'payroll', 'action' => 'approve', 'description' => 'Can provide final authorization for payroll transfers'],
            ['code' => 'hr.benefits.view', 'name' => 'View Benefits & Insurance', 'module' => 'hr', 'resource' => 'benefits', 'action' => 'view', 'description' => 'Can view company benefits and insurance plans'],
            ['code' => 'hr.benefits.manage', 'name' => 'Manage Employee Benefits', 'module' => 'hr', 'resource' => 'benefits', 'action' => 'manage', 'description' => 'Can enroll employees and configure benefit policies'],
            ['code' => 'hr.recruitment.view', 'name' => 'View Job Requisitions', 'module' => 'hr', 'resource' => 'recruitment', 'action' => 'view', 'description' => 'Can review job postings and applicant pipelines'],
            ['code' => 'hr.recruitment.post', 'name' => 'Create Job Openings', 'module' => 'hr', 'resource' => 'recruitment', 'action' => 'post', 'description' => 'Can publish job openings and review candidates'],
            ['code' => 'hr.recruitment.hire', 'name' => 'Extend Job Offers', 'module' => 'hr', 'resource' => 'recruitment', 'action' => 'hire', 'description' => 'Can extend offers and complete hiring workflows'],
            ['code' => 'hr.compliance.view', 'name' => 'View Compliance Records', 'module' => 'hr', 'resource' => 'compliance', 'action' => 'view', 'description' => 'Can view labor law compliance and certification status'],
            ['code' => 'hr.compliance.manage', 'name' => 'Manage HR Compliance Policies', 'module' => 'hr', 'resource' => 'compliance', 'action' => 'manage', 'description' => 'Can enforce workplace standards and legal compliance'],
            ['code' => 'hr.self_service.view_payslip', 'name' => 'View Personal Payslips', 'module' => 'hr', 'resource' => 'self_service', 'action' => 'view_payslip', 'description' => 'Can view individual monthly payslips'],
            ['code' => 'hr.self_service.request_leave', 'name' => 'Submit Leave Requests', 'module' => 'hr', 'resource' => 'self_service', 'action' => 'request_leave', 'description' => 'Can request annual, sick, or emergency leave'],
            ['code' => 'hr.self_service.clock_in', 'name' => 'Staff Attendance Clock In/Out', 'module' => 'hr', 'resource' => 'self_service', 'action' => 'clock_in', 'description' => 'Can clock in and out for work shifts'],
            ['code' => 'hr.teams.view', 'name' => 'View Team Reports', 'module' => 'hr', 'resource' => 'teams', 'action' => 'view', 'description' => 'Can view direct reports attendance and leave'],
            ['code' => 'hr.teams.manage', 'name' => 'Manage Team Schedules', 'module' => 'hr', 'resource' => 'teams', 'action' => 'manage', 'description' => 'Can approve team leave and manage work shifts'],
        ];

        foreach ($permissions as $permissionData) {
            $existing = Permission::where('code', $permissionData['code'])->first();
            Permission::updateOrCreate(
                ['code' => $permissionData['code']],
                [
                    ...$permissionData,
                    'uuid' => $existing?->uuid ?? (string) Str::uuid(),
                    'is_active' => true,
                ]
            );
        }

        // 2. Define System & Standard Role Hierarchy Matrix with module and level
        $rolesMatrix = [
            // Global Super Admin & Root Roles
            'Super Admin' => [
                'code' => 'super_admin',
                'module' => 'system',
                'level' => 100,
                'is_system' => true,
                'permissions' => Permission::pluck('code')->all(),
            ],
            'Admin' => [
                'code' => 'admin',
                'module' => 'system',
                'level' => 95,
                'is_system' => true,
                'permissions' => Permission::pluck('code')->all(),
            ],
            'Owner' => [
                'code' => 'owner',
                'module' => 'system',
                'level' => 90,
                'is_system' => true,
                'permissions' => Permission::pluck('code')->all(),
            ],

            // ==========================================
            // MODULE 1: INVENTORY (Admin + 5 Sub-roles)
            // ==========================================
            'Inventory Admin' => [
                'code' => 'inventory_admin',
                'module' => 'inventory',
                'level' => 80,
                'is_system' => true,
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
                'module' => 'inventory',
                'level' => 60,
                'is_system' => false,
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
                'module' => 'inventory',
                'level' => 30,
                'is_system' => false,
                'permissions' => [
                    'inventory.view', 'inventory.warehouses.view', 'inventory.stock.transfer', 'inventory.stock.count',
                    'inventory.procurement.receive', 'inventory.fulfillment.view', 'inventory.fulfillment.pack', 'inventory.fulfillment.ship',
                    'products.view', 'product_codes.view', 'labels.print',
                ],
            ],
            'Planner / Auditor' => [
                'code' => 'planner_auditor',
                'module' => 'inventory',
                'level' => 50,
                'is_system' => false,
                'permissions' => [
                    'inventory.view', 'inventory.stock.count', 'inventory.audits.view', 'inventory.audits.reconcile',
                    'inventory.procurement.view', 'products.view', 'product_codes.view', 'product_prices.view',
                ],
            ],
            'Purchasing / Procurement' => [
                'code' => 'purchasing_procurement',
                'module' => 'inventory',
                'level' => 45,
                'is_system' => false,
                'permissions' => [
                    'inventory.view', 'inventory.procurement.view', 'inventory.procurement.order', 'inventory.procurement.receive',
                    'products.view', 'product_codes.view', 'product_prices.view', 'brands.view',
                ],
            ],
            'Sales / Order Fulfillment' => [
                'code' => 'order_fulfillment',
                'module' => 'inventory',
                'level' => 35,
                'is_system' => false,
                'permissions' => [
                    'inventory.view', 'inventory.fulfillment.view', 'inventory.fulfillment.pack', 'inventory.fulfillment.ship',
                    'products.view', 'labels.print',
                ],
            ],

            // ==========================================
            // MODULE 2: FINANCE (Admin + 9 Sub-roles)
            // ==========================================
            'Finance Admin' => [
                'code' => 'finance_admin',
                'module' => 'finance',
                'level' => 80,
                'is_system' => true,
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
                'module' => 'finance',
                'level' => 55,
                'is_system' => false,
                'permissions' => [
                    'finance.dashboard.view', 'finance.treasury.view', 'finance.treasury.manage_cash',
                    'finance.treasury.transfer', 'finance.reports.view',
                ],
            ],
            'Financial Reporting / Analyst' => [
                'code' => 'financial_analyst',
                'module' => 'finance',
                'level' => 50,
                'is_system' => false,
                'permissions' => [
                    'finance.dashboard.view', 'finance.accounts.view', 'finance.gl.view',
                    'finance.reports.view', 'finance.reports.export', 'finance.ar.view', 'finance.ap.view',
                ],
            ],
            'AR Manager' => [
                'code' => 'ar_manager',
                'module' => 'finance',
                'level' => 60,
                'is_system' => false,
                'permissions' => [
                    'finance.accounts.view', 'finance.ar.view', 'finance.ar.invoice',
                    'finance.ar.collect', 'finance.reports.view',
                ],
            ],
            'Auditor' => [
                'code' => 'finance_auditor',
                'module' => 'finance',
                'level' => 65,
                'is_system' => false,
                'permissions' => [
                    'finance.accounts.view', 'finance.gl.view', 'finance.reports.view',
                    'finance.audits.view', 'finance.ar.view', 'finance.ap.view',
                ],
            ],
            'AP Clerk' => [
                'code' => 'ap_clerk',
                'module' => 'finance',
                'level' => 25,
                'is_system' => false,
                'permissions' => [
                    'finance.ap.view', 'finance.ap.bills',
                ],
            ],
            'General Ledger Accountant' => [
                'code' => 'general_ledger_accountant',
                'module' => 'finance',
                'level' => 50,
                'is_system' => false,
                'permissions' => [
                    'finance.accounts.view', 'finance.gl.view', 'finance.gl.post',
                    'finance.reports.view',
                ],
            ],
            'Controller' => [
                'code' => 'controller',
                'module' => 'finance',
                'level' => 75,
                'is_system' => false,
                'permissions' => [
                    'finance.dashboard.view', 'finance.accounts.view', 'finance.gl.view', 'finance.gl.post',
                    'finance.gl.close_period', 'finance.treasury.view', 'finance.reports.view', 'finance.reports.export',
                    'finance.ar.view', 'finance.ap.view', 'finance.ap.approve',
                ],
            ],
            'AP Approver / Manager' => [
                'code' => 'ap_approver',
                'module' => 'finance',
                'level' => 60,
                'is_system' => false,
                'permissions' => [
                    'finance.ap.view', 'finance.ap.bills', 'finance.ap.approve', 'finance.ap.pay',
                    'finance.reports.view',
                ],
            ],
            'AR Clerk' => [
                'code' => 'ar_clerk',
                'module' => 'finance',
                'level' => 25,
                'is_system' => false,
                'permissions' => [
                    'finance.ar.view', 'finance.ar.invoice', 'finance.ar.collect',
                ],
            ],

            // ==========================================
            // MODULE 3: POS (Admin + 5 Sub-roles)
            // ==========================================
            'POS Admin' => [
                'code' => 'pos_admin',
                'module' => 'pos',
                'level' => 80,
                'is_system' => true,
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
                'module' => 'pos',
                'level' => 60,
                'is_system' => false,
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
                'module' => 'pos',
                'level' => 20,
                'is_system' => false,
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
                'module' => 'pos',
                'level' => 25,
                'is_system' => false,
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
                'module' => 'pos',
                'level' => 40,
                'is_system' => false,
                'permissions' => [
                    'pos.shifts.view', 'pos.reports.view', 'pos.reports.export',
                    'registers.view', 'products.view',
                ],
            ],
            'Shift Supervisor' => [
                'code' => 'shift_supervisor',
                'module' => 'pos',
                'level' => 45,
                'is_system' => false,
                'permissions' => [
                    'pos.access', 'pos.checkout', 'pos.refund', 'pos.void', 'pos.discount',
                    'pos.shifts.open', 'pos.shifts.close', 'pos.shifts.view',
                    'pos.reports.view', 'pos_pin.verify', 'registers.view', 'pos_devices.view',
                    'products.view', 'categories.view', 'product_prices.view',
                ],
            ],

            // ==========================================
            // MODULE 4: HR (Admin + 8 Sub-roles)
            // ==========================================
            'HR Admin' => [
                'code' => 'hr_admin',
                'module' => 'hr',
                'level' => 80,
                'is_system' => true,
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
                'module' => 'hr',
                'level' => 45,
                'is_system' => false,
                'permissions' => [
                    'hr.employees.view', 'hr.benefits.view', 'hr.benefits.manage',
                ],
            ],
            'HR Manager / Director' => [
                'code' => 'hr_manager',
                'module' => 'hr',
                'level' => 60,
                'is_system' => false,
                'permissions' => [
                    'hr.dashboard.view', 'hr.employees.view', 'hr.employees.create', 'hr.employees.update',
                    'hr.payroll.view', 'hr.payroll.manage', 'hr.benefits.view', 'hr.benefits.manage',
                    'hr.recruitment.view', 'hr.recruitment.post', 'hr.recruitment.hire',
                    'hr.compliance.view', 'hr.teams.view', 'hr.teams.manage',
                ],
            ],
            'Payroll Administrator' => [
                'code' => 'payroll_admin',
                'module' => 'hr',
                'level' => 50,
                'is_system' => false,
                'permissions' => [
                    'hr.employees.view', 'hr.payroll.view', 'hr.payroll.manage', 'hr.payroll.process',
                ],
            ],
            'HR Generalist / Coordinator' => [
                'code' => 'hr_generalist',
                'module' => 'hr',
                'level' => 40,
                'is_system' => false,
                'permissions' => [
                    'hr.employees.view', 'hr.employees.create', 'hr.employees.update',
                    'hr.benefits.view', 'hr.recruitment.view', 'hr.teams.view',
                ],
            ],
            'People Manager (scoped to reports)' => [
                'code' => 'people_manager',
                'module' => 'hr',
                'level' => 50,
                'is_system' => false,
                'permissions' => [
                    'hr.employees.view', 'hr.teams.view', 'hr.teams.manage',
                    'hr.self_service.view_payslip', 'hr.self_service.request_leave', 'hr.self_service.clock_in',
                ],
            ],
            'Recruiter / Talent Acquisition' => [
                'code' => 'recruiter',
                'module' => 'hr',
                'level' => 40,
                'is_system' => false,
                'permissions' => [
                    'hr.employees.view', 'hr.recruitment.view', 'hr.recruitment.post', 'hr.recruitment.hire',
                ],
            ],
            'Compliance / Legal Officer' => [
                'code' => 'compliance_officer',
                'module' => 'hr',
                'level' => 55,
                'is_system' => false,
                'permissions' => [
                    'hr.employees.view', 'hr.compliance.view', 'hr.compliance.manage',
                ],
            ],
            'Employee Self-Service' => [
                'code' => 'employee_self_service',
                'module' => 'hr',
                'level' => 10,
                'is_system' => false,
                'permissions' => [
                    'hr.self_service.view_payslip', 'hr.self_service.request_leave', 'hr.self_service.clock_in',
                ],
            ],
        ];

        $createdRoles = [];

        foreach ($rolesMatrix as $roleName => $config) {
            $role = Role::firstOrCreate(
                ['code' => $config['code']],
                [
                    'name' => $roleName,
                    'module' => $config['module'],
                    'level' => $config['level'],
                    'is_system' => $config['is_system'],
                    'is_active' => true,
                    'uuid' => (string) Str::uuid(),
                ]
            );

            $role->update([
                'name' => $roleName,
                'module' => $config['module'],
                'level' => $config['level'],
                'is_system' => $config['is_system'],
                'is_active' => true,
            ]);

            $permissionIds = Permission::whereIn('code', $config['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);

            $createdRoles[$config['code']] = $role;

            // Invalidate Redis cache for users with this role
            RbacCacheService::forgetRoleUsersCache($role);
        }

        // 3. Seed Delegation Matrix (role_assignable_roles)
        $delegationMap = [
            'super_admin' => array_keys($createdRoles), // Super Admin can delegate all
            'admin' => array_keys($createdRoles),
            'owner' => array_keys($createdRoles),

            'inventory_admin' => [
                'inventory_manager',
                'warehouse_operator',
                'planner_auditor',
                'purchasing_procurement',
                'order_fulfillment',
            ],
            'finance_admin' => [
                'treasury_cash_management',
                'financial_analyst',
                'ar_manager',
                'finance_auditor',
                'ap_clerk',
                'general_ledger_accountant',
                'controller',
                'ap_approver',
                'ar_clerk',
            ],
            'pos_admin' => [
                'store_manager',
                'shift_supervisor',
                'cashier',
                'inventory_clerk',
                'reporting_accountant',
            ],
            'hr_admin' => [
                'benefits_administrator',
                'hr_manager',
                'payroll_admin',
                'hr_generalist',
                'people_manager',
                'recruiter',
                'compliance_officer',
                'employee_self_service',
            ],
        ];

        foreach ($delegationMap as $grantorCode => $assignableCodes) {
            $grantorRole = $createdRoles[$grantorCode] ?? null;
            if (! $grantorRole) continue;

            $assignableRoleIds = collect($assignableCodes)
                ->map(fn ($c) => $createdRoles[$c]->id ?? null)
                ->filter()
                ->values();

            $grantorRole->assignableRoles()->sync($assignableRoleIds);
        }
    }
}
