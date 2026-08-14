<?php

use App\Models\Department;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7210',
        'alias' => 'PUR',
    ]);

    $this->canvasser = User::query()->create([
        'name' => 'View All Canvasser',
        'username' => 'po-view-all-canvasser',
        'email' => 'po-view-all-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->otherStaff = User::query()->create([
        'name' => 'Other View Staff',
        'username' => 'po-view-all-other',
        'email' => 'po-view-all-other@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
    $this->otherStaff->assignRole('purchasing-staff');

    $ownSupplier = Supplier::query()->create([
        'name' => 'Own View Supplier',
        'code' => 'SUP-VIEW-OWN',
        'created_by' => $this->canvasser->id,
    ]);

    $otherSupplier = Supplier::query()->create([
        'name' => 'Other View Supplier',
        'code' => 'SUP-VIEW-OTHER',
        'created_by' => $this->otherStaff->id,
    ]);

    $this->ownPo = PurchaseOrder::query()->create([
        'supplier_id' => $ownSupplier->id,
        'created_by' => $this->canvasser->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-VIEW-OWN-001',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $this->otherPo = PurchaseOrder::query()->create([
        'supplier_id' => $otherSupplier->id,
        'created_by' => $this->otherStaff->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-VIEW-OTHER-001',
        'subtotal' => 2000,
        'total' => 2000,
    ]);

    $this->otherPendingPo = PurchaseOrder::query()->create([
        'supplier_id' => $otherSupplier->id,
        'created_by' => $this->otherStaff->id,
        'status' => 'PENDING_APPROVAL',
        'po_number' => 'PO-VIEW-OTHER-PENDING',
        'subtotal' => 1500,
        'total' => 1500,
        'submitted_at' => now(),
    ]);
});

it('allows purchasing staff to see purchase orders created by other staff', function () {
    $response = $this->actingAs($this->canvasser)
        ->get(route('purchase-orders.index'));

    $response->assertSuccessful();
    $response->assertSee('PO-VIEW-OWN-001');
    $response->assertSee('PO-VIEW-OTHER-001');
    $response->assertSee('PO-VIEW-OTHER-PENDING');
});

it('forbids purchasing staff from cancelling another staff purchase order', function () {
    $this->actingAs($this->canvasser)
        ->post(route('purchase-orders.cancel', $this->otherPo))
        ->assertForbidden();

    expect($this->otherPo->fresh()->status)->toBe('APPROVED');
});

it('allows im-staff with view-po to see purchase orders created by other users', function () {
    $imStaff = User::query()->create([
        'name' => 'IM Staff Viewer',
        'username' => 'po-view-im-staff',
        'email' => 'po-view-im-staff@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->canvasser->department_id,
        'role' => 'Staff',
    ]);
    $imStaff->assignRole('im-staff');

    $this->actingAs($imStaff)
        ->get(route('purchase-orders.index'))
        ->assertSuccessful()
        ->assertSee('PO-VIEW-OWN-001')
        ->assertSee('PO-VIEW-OTHER-001')
        ->assertSee('PO-VIEW-OTHER-PENDING');
});

it('forbids purchasing staff from withdrawing another staff purchase order', function () {
    $this->actingAs($this->canvasser)
        ->post(route('purchase-orders.withdraw', $this->otherPendingPo))
        ->assertForbidden();

    expect($this->otherPendingPo->fresh()->status)->toBe('PENDING_APPROVAL');
});
