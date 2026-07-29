<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReport;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7100',
        'alias' => 'IM',
    ]);

    $this->user = User::query()->create([
        'name' => 'RR Approved PO User',
        'username' => 'rr-approved-po-user',
        'email' => 'rr-approved-po-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $this->user->assignRole('administrator');

    $supplier = Supplier::query()->create([
        'name' => 'RR Gate Supplier',
        'code' => 'SUP-RR-GATE',
        'created_by' => $this->user->id,
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Consumables',
        'code' => 'CNS',
    ]);

    $item = Item::query()->create([
        'name' => 'RR Gate Item',
        'code' => 'ITM-RR-GATE',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 100,
        'is_active' => true,
    ]);

    $this->draftPo = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
        'status' => 'DRAFT',
        'po_number' => 'PO-DRAFT-RR-001',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $this->draftPoItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $this->draftPo->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'unit_price' => 100,
        'line_subtotal' => 1000,
        'discount_rate' => 0,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
        'total' => 1000,
    ]);

    $this->approvedPo = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-APPROVED-RR-001',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $this->approvedPoItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $this->approvedPo->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'unit_price' => 100,
        'line_subtotal' => 1000,
        'discount_rate' => 0,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
        'total' => 1000,
    ]);
});

it('rejects po lookup when purchase order is not approved', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('receiving-reports.po-by-number', [
            'po_number' => $this->draftPo->po_number,
        ]));

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Only approved purchase orders can be used for receiving reports.');
});

it('allows po lookup when purchase order is approved', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('receiving-reports.po-by-number', [
            'po_number' => $this->approvedPo->po_number,
        ]));

    $response->assertSuccessful();
    $response->assertJsonPath('purchase_order.id', $this->approvedPo->id);
    $response->assertJsonPath('purchase_order.status', 'APPROVED');
});

it('rejects receiving report store when purchase order is not approved', function () {
    $response = $this->actingAs($this->user)
        ->from(route('receiving-reports.index'))
        ->post(route('receiving-reports.store'), [
            'rr_number' => 'RR-GATE-001',
            'rr_number_suggested' => 'RR-GATE-001',
            'purchase_order_id' => $this->draftPo->id,
            'received_date' => now()->toDateString(),
            'requires_customs_document' => '0',
            'notes' => null,
            'items' => [
                [
                    'purchase_order_item_id' => $this->draftPoItem->id,
                    'selected' => '1',
                    'qty_good' => 1,
                    'qty_bad' => 0,
                ],
            ],
        ]);

    $response->assertRedirect(route('receiving-reports.index'));
    $response->assertSessionHasErrors('purchase_order_id');

    expect(ReceivingReport::query()->count())->toBe(0);
});

it('allows receiving report store when purchase order is approved', function () {
    $response = $this->actingAs($this->user)
        ->from(route('receiving-reports.index'))
        ->post(route('receiving-reports.store'), [
            'rr_number' => 'RR-GATE-002',
            'rr_number_suggested' => 'RR-GATE-002',
            'purchase_order_id' => $this->approvedPo->id,
            'received_date' => now()->toDateString(),
            'requires_customs_document' => '0',
            'notes' => null,
            'items' => [
                [
                    'purchase_order_item_id' => $this->approvedPoItem->id,
                    'selected' => '1',
                    'qty_good' => 1,
                    'qty_bad' => 0,
                ],
            ],
        ]);

    $response->assertRedirect(route('receiving-reports.index'));
    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    expect(ReceivingReport::query()->where('purchase_order_id', $this->approvedPo->id)->exists())->toBeTrue();
});
