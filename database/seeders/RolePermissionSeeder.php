<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions_users = [
            'create-users',
            'view-users',
            'edit-users',
            'delete-users'
        ];
        $permissions_prs = [
            'create-prs',
            'view-prs',
            'edit-prs',
            'delete-prs',
            'approve-prs',
        ];

        $permissions_canvassing = [
            'assign-canvaser',
            'view-canvassing',
            'update-canvassing',
        ];

        $permissions_po = [
            'create-po',            // Buat Purchase Order
            'view-po',              // Lihat PO
            'submit-po',            // Submit PO
            'approve-po',           // Approve PO
            'cancel-po',            // Cancel PO
            'view-po-progress',     // Lihat progress PO
        ];

        $permissions_rr = [
            'create-rr',            // Buat Receiving Report
            'view-rr',              // Lihat RR
            'update-rr',            // Update RR
        ];

        $permissions_general = [
            'view-dashboard',       // Akses dashboard
            'export-report',        // Export laporan
            'print-document',       // Cetak dokumen
        ];

        $all_permissions = array_merge(
            $permissions_users,
            $permissions_prs,
            $permissions_canvassing,
            $permissions_po,
            $permissions_rr,
            $permissions_general
        );

        foreach ($all_permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $administratorRole = Role::create(['name' => 'administrator']);
        $administratorRole->givePermissionTo(Permission::all());

        $generalManagerRole = Role::create(['name' => 'general-manager']);
        $generalManagerRole->givePermissionTo($permissions_general);

        $itManagerRole = Role::create(['name' => 'it-manager']);
        $itManagerRole->givePermissionTo(Permission::all());

        // Manager Roles
        $purchasingManagerRole = Role::create(['name' => 'purchasing-manager']);
        $purchasingManagerRole->givePermissionTo([
            'approve-prs',
            'view-prs',
            'assign-canvaser',
            'view-canvassing',
            'approve-po',
            'view-po',
            'view-po-progress',
            'view-rr',
            'view-dashboard',
            'export-report',
            'print-document',
        ]);

        $inventoryManagerRole = Role::create(['name' => 'inventory-manager']);
        $inventoryManagerRole->givePermissionTo([
            'create-rr',
            'view-rr',
            'update-rr',
            'view-po',
            'view-dashboard',
            'print-document',
        ]);

        $salesManagerRole = Role::create(['name' => 'sales-manager']);
        $salesManagerRole->givePermissionTo([
            'view-prs',
            'view-po',
            'view-dashboard',
            'export-report',
            'print-document',
        ]);

        $financeManagerRole = Role::create(['name' => 'finance-manager']);
        $financeManagerRole->givePermissionTo([
            'view-prs',
            'view-po',
            'view-rr',
            'view-dashboard',
            'export-report',
            'print-document',
        ]);

        $itSupervisorRole = Role::create(['name' => 'it-supervisor']);
        $itSupervisorRole->givePermissionTo(Permission::all());

        // Supervisor Roles
        $purchasingSupervisorRole = Role::create(['name' => 'purchasing-supervisor']);
        $purchasingSupervisorRole->givePermissionTo([
            'assign-canvaser',
            'view-canvassing',
            'create-po',
            'view-po',
            'submit-po',
            'view-po-progress',
            'view-prs',
            'create-prs',
            'view-dashboard',
            'print-document',
        ]);

        $salesSupervisorRole = Role::create(['name' => 'sales-supervisor']);
        $salesSupervisorRole->givePermissionTo([
            'view-prs',
            'view-po',
            'view-dashboard',
            'print-document',
        ]);

        $financeSupervisorRole = Role::create(['name' => 'finance-supervisor']);
        $financeSupervisorRole->givePermissionTo([
            'view-prs',
            'view-po',
            'view-rr',
            'view-dashboard',
            'export-report',
            'print-document',
        ]);

        $itStaffRole = Role::create(['name' => 'it-staff']);
        $itStaffRole->givePermissionTo([
            'view-dashboard',
            'print-document',
        ]);

        // Staff Roles
        $canvaserRole = Role::create(['name' => 'canvaser']);
        $canvaserRole->givePermissionTo([
            'view-canvassing',
            'update-canvassing',
            'create-po',
            'view-po',
            'submit-po',
            'cancel-po',
            'view-po-progress',
            'create-prs',
            'view-prs',
            'edit-prs',
            'delete-prs',
            'view-dashboard',
            'print-document'
        ]);

        $salesStaffRole = Role::create(['name' => 'sales-staff']);
        $salesStaffRole->givePermissionTo([
            'view-prs',
            'view-po',
            'view-dashboard',
            'print-document',
        ]);

        $financeStaffRole = Role::create(['name' => 'finance-staff']);
        $financeStaffRole->givePermissionTo([
            'view-prs',
            'view-po',
            'view-rr',
            'view-dashboard',
            'print-document',
        ]);

        $inventoryStaffRole = Role::findOrCreate('inventory-staff', 'web');
        $inventoryStaffRole->givePermissionTo([
            'create-rr',
            'view-rr',
            'update-rr',
            'view-po',
            'view-dashboard',
            'print-document',
        ]);

        // Finance Role - Untuk accounting nanti
        $financeRole = Role::findOrCreate('finance', 'web');
        $financeRole->givePermissionTo([
            'view-prs',
            'view-po',
            'view-rr',
            'view-dashboard',
            'export-report',
            'print-document',
        ]);
    }
}
