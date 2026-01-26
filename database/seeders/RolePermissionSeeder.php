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

        $warehouseRole = Role::findOrCreate('warehouse-staff', 'web');
        $warehouseRole->givePermissionTo([
            'create-rr',
            'view-rr',
            'update-rr',
            'view-po',
            'view-dashboard',
            'print-document',
        ]);


        // 6. Finance Role - Untuk accounting nanti
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
