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
    $response->assertSee('Decimal places');
    $response->assertSee('name="decimal_places"', false);
    $response->assertSee('value="2"', false);
    $response->assertSee('value="10"', false);
    $response->assertSee('2 (default)', false);
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
            'decimal_places' => 2,
        ]);

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');

    expect($this->purchaseOrder->fresh()->po_number)->toBe('PO-PAPER-777');
});

it('prints money amounts using the selected decimal places', function () {
    $this->purchaseOrder->update([
        'subtotal' => 1000.5,
        'total' => 1000.5,
    ]);
    $this->purchaseOrder->items()->first()->update([
        'unit_price' => 1000.5,
        'total' => 1000.5,
    ]);

    $html = view('pdf.purchase-order', [
        'purchaseOrder' => $this->purchaseOrder->fresh()->load([
            'supplier',
            'currency',
            'items.item.unit',
            'items.prsItem.prs.department',
            'certifiedBy',
            'approvedBy',
        ]),
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
        'decimalPlaces' => 4,
    ])->render();

    expect($html)
        ->toContain('1.000,5000')
        ->not->toContain('1.000,50</td>');
});

it('defaults printed money amounts to two decimal places', function () {
    $html = view('pdf.purchase-order', [
        'purchaseOrder' => $this->purchaseOrder->load([
            'supplier',
            'currency',
            'items.item.unit',
            'items.prsItem.prs.department',
            'certifiedBy',
            'approvedBy',
        ]),
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
    ])->render();

    expect($html)->toContain('1.000,00');
});

it('rejects invalid decimal places when printing', function () {
    $response = $this->actingAs($this->user)
        ->from(route('purchase-orders.show', $this->purchaseOrder))
        ->post(route('purchase-orders.print', $this->purchaseOrder), [
            'po_number' => 'PO-PAPER-777',
            'decimal_places' => 11,
        ]);

    $response->assertRedirect(route('purchase-orders.show', $this->purchaseOrder));
    $response->assertSessionHasErrors('decimal_places');
    expect($this->purchaseOrder->fresh()->po_number)->toBe('PO-OLD-001');
});

it('rejects a duplicate po number when saving the number and shows the supplier', function () {
    $otherSupplier = Supplier::query()->create([
        'name' => 'Conflicting Supplier Co',
        'code' => 'SUP-PO-DUP-001',
        'created_by' => $this->user->id,
    ]);

    PurchaseOrder::query()->create([
        'supplier_id' => $otherSupplier->id,
        'currency_id' => $this->purchaseOrder->currency_id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-TAKEN-999',
        'subtotal' => 500,
        'total' => 500,
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('purchase-orders.show', $this->purchaseOrder))
        ->post(route('purchase-orders.number', $this->purchaseOrder), [
            'po_number' => 'PO-TAKEN-999',
            'po_number_suggested' => 'PO-SUGGESTED',
        ]);

    $response->assertRedirect(route('purchase-orders.show', $this->purchaseOrder));
    $response->assertSessionHasErrors([
        'po_number' => 'The PO Number PO-TAKEN-999 has already been used by supplier Conflicting Supplier Co.',
    ]);
    expect($this->purchaseOrder->fresh()->po_number)->toBe('PO-OLD-001');

    $showResponse = $this->actingAs($this->user)
        ->from(route('purchase-orders.show', $this->purchaseOrder))
        ->followingRedirects()
        ->post(route('purchase-orders.number', $this->purchaseOrder), [
            'po_number' => 'PO-TAKEN-999',
            'po_number_suggested' => 'PO-SUGGESTED',
        ]);

    $showResponse->assertSuccessful();
    $showResponse->assertSee('is-invalid', false);
    $showResponse->assertSee('The PO Number PO-TAKEN-999 has already been used by supplier Conflicting Supplier Co.');
    $showResponse->assertSee('icon: \'error\'', false);
    $showResponse->assertSee('scrollIntoView', false);
    $showResponse->assertSee('po-number-form', false);
    $showResponse->assertDontSee('data-auto-show="1"', false);
});

it('rejects a duplicate po number when printing and reopens the confirm modal with supplier feedback', function () {
    $otherSupplier = Supplier::query()->create([
        'name' => 'Print Conflict Supplier',
        'code' => 'SUP-PO-DUP-PRINT',
        'created_by' => $this->user->id,
    ]);

    PurchaseOrder::query()->create([
        'supplier_id' => $otherSupplier->id,
        'currency_id' => $this->purchaseOrder->currency_id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-PRINT-DUP',
        'subtotal' => 750,
        'total' => 750,
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('purchase-orders.show', $this->purchaseOrder))
        ->post(route('purchase-orders.print', $this->purchaseOrder), [
            'po_number' => 'PO-PRINT-DUP',
            'po_number_suggested' => 'PO-SUGGESTED',
            'decimal_places' => 2,
        ]);

    $response->assertRedirect(route('purchase-orders.show', $this->purchaseOrder));
    $response->assertSessionHasErrors([
        'po_number' => 'The PO Number PO-PRINT-DUP has already been used by supplier Print Conflict Supplier.',
    ]);
    expect($this->purchaseOrder->fresh()->po_number)->toBe('PO-OLD-001');

    $followUp = $this->actingAs($this->user)
        ->from(route('purchase-orders.show', $this->purchaseOrder))
        ->followingRedirects()
        ->post(route('purchase-orders.print', $this->purchaseOrder), [
            'po_number' => 'PO-PRINT-DUP',
            'po_number_suggested' => 'PO-SUGGESTED',
            'decimal_places' => 2,
        ]);

    $followUp->assertSuccessful();
    $followUp->assertSee('data-auto-show="1"', false);
    $followUp->assertSee('The PO Number PO-PRINT-DUP has already been used by supplier Print Conflict Supplier.');
    $followUp->assertDontSee('icon: \'error\'', false);
    $followUp->assertSee('is-invalid', false);
});
