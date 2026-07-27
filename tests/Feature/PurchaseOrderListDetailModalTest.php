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

it('shows po detail in a modal and opens print confirm before printing', function () {
    $response = $this->actingAs($this->user)
        ->get(route('purchase-orders.index'));

    $response->assertSuccessful();
    $response->assertSee(
        'href="'.route('purchase-orders.show', $this->purchaseOrder).'"',
        false
    );
    $response->assertSee('Cancel PO');
    $response->assertSee('confirmCancelPo(', false);
    $response->assertSee(
        'action="'.route('purchase-orders.cancel', $this->purchaseOrder).'"',
        false
    );
    $response->assertSee('data-bs-target="#poDetail-'.$this->purchaseOrder->id.'"', false);
    $response->assertSee('id="poDetail-'.$this->purchaseOrder->id.'"', false);
    $response->assertSee('Purchase Order Detail');
    $response->assertSee('po-detail-body', false);
    $response->assertSee('po-detail-modal', false);
    $response->assertSee('data-bs-target="#poPrintConfirm-'.$this->purchaseOrder->id.'"', false);
    $response->assertSee('id="poPrintConfirm-'.$this->purchaseOrder->id.'"', false);
    $response->assertSee('Confirm PO Number');
    $response->assertSee(
        'action="'.route('purchase-orders.print', $this->purchaseOrder).'"',
        false
    );
    $response->assertSee('name="po_number"', false);
    $response->assertSee('value="PO-MODAL-001"', false);
    $response->assertDontSee(
        'href="'.route('purchase-orders.print', $this->purchaseOrder).'" target="_blank"',
        false
    );
});
