<?php

use App\Models\Currency;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
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
        'name' => 'PO Print Confirm User',
        'username' => 'po-print-confirm-user',
        'email' => 'po-print-confirm-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $this->user->assignRole('purchasing-staff');

    $currency = Currency::query()->create([
        'name' => 'Indonesian Rupiah',
        'code' => 'IDR',
        'symbol' => 'Rp',
        'created_by' => $this->user->id,
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Print Confirm Supplier',
        'code' => 'SUP-PO-PRINT-CFM',
        'created_by' => $this->user->id,
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Office Supplies',
        'code' => 'OFF',
    ]);

    $item = Item::query()->create([
        'name' => 'Confirm Item',
        'code' => 'ITM-PO-CFM',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-OLD-001',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $this->purchaseOrder->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'line_subtotal' => 1000,
        'discount_rate' => 0,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
        'total' => 1000,
        'meta' => ['prs_number' => 'PRS-CFM-001'],
    ]);
});

it('shows a print confirmation modal with editable po number on the show page', function () {
    $response = $this->actingAs($this->user)
        ->get(route('purchase-orders.show', $this->purchaseOrder));

    $response->assertSuccessful();
    $response->assertSee('data-bs-target="#poPrintConfirm-'.$this->purchaseOrder->id.'"', false);
    $response->assertSee('id="poPrintConfirm-'.$this->purchaseOrder->id.'"', false);
    $response->assertSee('Confirm PO Number');
    $response->assertSee('Edit if the paper form number is different');
    $response->assertSee(
        'action="'.route('purchase-orders.print', $this->purchaseOrder).'"',
        false
    );
    $response->assertSee('value="PO-OLD-001"', false);
    $response->assertDontSee('formaction="'.route('purchase-orders.print', $this->purchaseOrder).'"', false);
});

it('saves an edited po number when printing from the confirmation modal', function () {
    $response = $this->actingAs($this->user)
        ->post(route('purchase-orders.print', $this->purchaseOrder), [
            'po_number' => 'PO-PAPER-777',
            'po_number_suggested' => 'PO-SUGGESTED',
        ]);

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');

    expect($this->purchaseOrder->fresh()->po_number)->toBe('PO-PAPER-777');
});
