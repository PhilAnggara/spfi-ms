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
            'view users',
            'create users',
            'edit users',
            'delete users',
        ];
        $permissions_prs = [
            'view prs',
            'create prs',
            'edit prs',
            'delete prs',
            'approve prs',
        ];

        $all_permissions = array_merge(
            $permissions_users,
            $permissions_prs
        );

        foreach ($all_permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $administratorRole = Role::create(['name' => 'Administrator']);
        $administratorRole->givePermissionTo(Permission::all());

        $generalManagerRole = Role::create(['name' => 'General Manager']);
        $generalManagerRole->givePermissionTo($permissions_prs);

        $purchasingManagerRole = Role::create(['name' => 'Purchasing Manager']);
        $purchasingManagerRole->givePermissionTo($permissions_prs);

        $purchasingStaffRole = Role::create(['name' => 'Purchasing Staff']);
        $purchasingStaffRole->givePermissionTo([
            'view prs',
            'create prs',
            'edit prs',
            'delete prs',
        ]);

        $staffRole = Role::create(['name' => 'Staff']);
        $staffRole->givePermissionTo([
            'view prs',
            'create prs',
            'edit prs',
            'delete prs',
        ]);
    }
}
