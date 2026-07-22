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
        'code' => '7200',
        'alias' => 'PUR',
    ]);

    $this->user = User::query()->create([
        'name' => 'PO List User',
        'username' => 'po-list-user',
        'email' => 'po-list-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $this->user->assignRole('purchasing-staff');

    $supplier = Supplier::query()->create([
        'name' => 'Modal Supplier',
        'code' => 'SUP-PO-MODAL',
        'created_by' => $this->user->id,
    ]);

    $this->purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-MODAL-001',
        'subtotal' => 1000,
        'total' => 1000,
    ]);
});

it('shows po detail in a modal and opens print in a new tab', function () {
    $response = $this->actingAs($this->user)
        ->get(route('purchase-orders.index'));

    $response->assertSuccessful();
    $response->assertDontSee(
        'href="'.route('purchase-orders.show', $this->purchaseOrder).'"',
        false
    );
    $response->assertSee('data-bs-target="#poDetail-'.$this->purchaseOrder->id.'"', false);
    $response->assertSee('id="poDetail-'.$this->purchaseOrder->id.'"', false);
    $response->assertSee('Purchase Order Detail');
    $response->assertSee(
        'href="'.route('purchase-orders.print', $this->purchaseOrder).'" target="_blank"',
        false
    );
});
