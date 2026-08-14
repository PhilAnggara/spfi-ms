<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
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
        'name' => 'RR Print User',
        'username' => 'rr-print-user',
        'email' => 'rr-print-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $this->user->assignRole('administrator');

    $supplier = Supplier::query()->create([
        'name' => 'Test Supplier',
        'code' => 'SUP-001',
        'created_by' => $this->user->id,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-TEST-001',
    ]);

    $this->receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-TEST-001',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => now()->toDateString(),
        'created_by' => $this->user->id,
    ]);
});

it('renders receiving report pdf preview with custom paper size', function () {
    $response = $this->actingAs($this->user)
        ->get(route('receiving-reports.print', [
            'receivingReport' => $this->receivingReport,
            'mode' => 'preview',
        ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('renders receiving report pdf print with custom paper size', function () {
    $response = $this->actingAs($this->user)
        ->get(route('receiving-reports.print', [
            'receivingReport' => $this->receivingReport,
            'mode' => 'print',
        ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->getContent())->toContain('/PrintScaling /None');
});

it('shows print confirm modal and preview link on the receiving reports index', function () {
    $response = $this->actingAs($this->user)
        ->get(route('receiving-reports.index'));

    $response->assertSuccessful();
    $response->assertSee('data-bs-target="#rrPrintConfirm-'.$this->receivingReport->id.'"', false);
    $response->assertSee('id="rrPrintConfirm-'.$this->receivingReport->id.'"', false);
    $response->assertSee('Confirm RR Number');
    $response->assertSee(config('receiving-report.paper.label'));
    $response->assertSee('Actual size / 100%');
    $response->assertSee(route('receiving-reports.print', ['receivingReport' => $this->receivingReport, 'mode' => 'preview']), false);
    $response->assertDontSee(
        'href="'.route('receiving-reports.print', ['receivingReport' => $this->receivingReport, 'mode' => 'print']).'"',
        false
    );
});

it('saves an edited rr number when printing from the confirmation modal', function () {
    $response = $this->actingAs($this->user)
        ->post(route('receiving-reports.print', ['receivingReport' => $this->receivingReport, 'mode' => 'print']), [
            'rr_number' => 'RR-PAPER-777',
            'rr_number_suggested' => 'RR-SUGGESTED',
            'print_confirm_id' => $this->receivingReport->id,
        ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->getContent())->toContain('/PrintScaling /None');
    expect($this->receivingReport->fresh()->rr_number)->toBe('RR-PAPER-777');
});

it('rejects a duplicate rr number when printing and shows supplier feedback', function () {
    $otherSupplier = Supplier::query()->create([
        'name' => 'RR Conflict Supplier',
        'code' => 'SUP-RR-DUP',
        'created_by' => $this->user->id,
    ]);

    $otherPurchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $otherSupplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-RR-DUP-001',
    ]);

    ReceivingReport::query()->create([
        'rr_number' => 'RR-TAKEN-999',
        'purchase_order_id' => $otherPurchaseOrder->id,
        'received_date' => now()->toDateString(),
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('receiving-reports.index'))
        ->post(route('receiving-reports.print', ['receivingReport' => $this->receivingReport, 'mode' => 'print']), [
            'rr_number' => 'RR-TAKEN-999',
            'rr_number_suggested' => 'RR-SUGGESTED',
            'print_confirm_id' => $this->receivingReport->id,
        ]);

    $response->assertRedirect(route('receiving-reports.index'));
    $response->assertSessionHasErrors([
        'rr_number' => 'The RR Number RR-TAKEN-999 has already been used by supplier RR Conflict Supplier.',
    ]);
    expect($this->receivingReport->fresh()->rr_number)->toBe('RR-TEST-001');

    $followUp = $this->actingAs($this->user)
        ->from(route('receiving-reports.index'))
        ->followingRedirects()
        ->post(route('receiving-reports.print', ['receivingReport' => $this->receivingReport, 'mode' => 'print']), [
            'rr_number' => 'RR-TAKEN-999',
            'rr_number_suggested' => 'RR-SUGGESTED',
            'print_confirm_id' => $this->receivingReport->id,
        ]);

    $followUp->assertSuccessful();
    $followUp->assertSee('data-auto-show="1"', false);
    $followUp->assertSee('The RR Number RR-TAKEN-999 has already been used by supplier RR Conflict Supplier.');
    $followUp->assertDontSee('icon: \'error\'', false);
    $followUp->assertSee('is-invalid', false);
});

it('uses 215mm by 160mm page dimensions in receiving report view', function () {
    $html = view('pdf.receiving-report', [
        'receivingReport' => $this->receivingReport->load([
            'purchaseOrder.supplier',
            'purchaseOrder.items.prsItem.prs',
            'items.purchaseOrderItem.item.unit',
            'items.purchaseOrderItem.item.category',
            'items.purchaseOrderItem.prsItem.prs.department',
            'customsDocumentType',
            'createdBy',
        ]),
        'isPreview' => true,
        'approvedByName' => 'Approver',
        'backgroundImageDataUri' => null,
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
    ])->render();

    expect($html)
        ->toContain('size: 215mm 160mm')
        ->toContain('width: 215mm')
        ->toContain('height: 160mm')
        ->toContain('font-family: Courier, monospace')
        ->not->toContain('DejaVu Sans')
        ->not->toContain('font-family: Arial');
});

it('uses flexible item row heights so a long name pushes the next row down', function () {
    $unit = UnitOfMeasure::query()->create(['name' => 'Pieces', 'code' => 'PCS']);
    $category = ItemCategory::query()->create(['name' => 'Spare Parts', 'code' => 'SPR']);
    $longName = 'HEAVY DUTY INDUSTRIAL GRADE STAINLESS STEEL BEARING HOUSING ASSEMBLY WITH EXTENDED SHAFT AND SEALED LUBRICATION PORT FOR HIGH SPEED APPLICATIONS';
    $shortName = 'SHORT BOLT';

    $longItem = Item::query()->create([
        'name' => $longName,
        'code' => 'LONG-ITEM',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);
    $shortItem = Item::query()->create([
        'name' => $shortName,
        'code' => 'SHORT-ITEM',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $purchaseOrder = $this->receivingReport->purchaseOrder;

    $longPoItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $longItem->id,
        'quantity' => 1,
        'unit_price' => 10,
        'total' => 10,
        'line_subtotal' => 10,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
    ]);
    $shortPoItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $shortItem->id,
        'quantity' => 1,
        'unit_price' => 5,
        'total' => 5,
        'line_subtotal' => 5,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $this->receivingReport->id,
        'purchase_order_item_id' => $longPoItem->id,
        'qty_good' => 1,
        'qty_bad' => 0,
    ]);
    ReceivingReportItem::query()->create([
        'receiving_report_id' => $this->receivingReport->id,
        'purchase_order_item_id' => $shortPoItem->id,
        'qty_good' => 1,
        'qty_bad' => 0,
    ]);

    $html = view('pdf.receiving-report', [
        'receivingReport' => $this->receivingReport->fresh()->load([
            'purchaseOrder.supplier',
            'purchaseOrder.items.prsItem.prs',
            'items.purchaseOrderItem.item.unit',
            'items.purchaseOrderItem.item.category',
            'items.purchaseOrderItem.prsItem.prs.department',
            'customsDocumentType',
            'createdBy',
        ]),
        'isPreview' => true,
        'approvedByName' => 'Approver',
        'backgroundImageDataUri' => null,
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
    ])->render();

    expect($html)
        ->toContain('white-space: pre-line')
        ->toContain('overflow: visible')
        ->toContain('font-weight: normal')
        ->toContain('line-height: 1.12')
        ->toContain($shortName)
        ->toContain('po-number-bold')
        ->toContain('font-weight: bold')
        ->not->toContain('max-height:')
        ->not->toContain('…');

    expect($html)->toMatch('/\.item-cell\s*\{[^}]*font-weight:\s*normal/s');
    expect($html)->toMatch('/\.po-number-bold\s*\{[^}]*font-weight:\s*bold/s');
    expect($html)->toContain('PO-TEST-001');

    preg_match_all('/class="cell item-cell"[^>]*>(.*?)<\/div>/s', $html, $matches);
    $itemCells = $matches[1] ?? [];

    expect($itemCells)->toHaveCount(2);
    expect(substr_count($itemCells[0], "\n"))->toBeGreaterThanOrEqual(2);

    $longLines = explode("\n", html_entity_decode($itemCells[0]));
    expect($longLines[0])->not->toEndWith('…');
    expect($longLines[1])->not->toBeEmpty();
    expect(html_entity_decode($itemCells[1]))->toBe($shortName);

    preg_match_all('/class="cell item-cell" style="left: [^;]+; top: ([0-9.]+)mm/', $html, $topMatches);
    $tops = array_map('floatval', $topMatches[1] ?? []);

    expect($tops)->toHaveCount(2);
    expect($tops[1])->toBeGreaterThan($tops[0] + 3);
});

it('keeps three single-line rr items from overlapping', function () {
    $unit = UnitOfMeasure::query()->create(['name' => 'Pieces', 'code' => 'PCS']);
    $category = ItemCategory::query()->create(['name' => 'Spare Parts', 'code' => 'SPR']);
    $purchaseOrder = $this->receivingReport->purchaseOrder;

    $names = ['BOLT M8', 'NUT M8', 'WASHER M8'];

    foreach ($names as $index => $name) {
        $item = Item::query()->create([
            'name' => $name,
            'code' => 'SHORT-'.$index,
            'unit_of_measure_id' => $unit->id,
            'category_id' => $category->id,
            'type' => 'Raw Material',
            'stock_on_hand' => 0,
            'is_active' => true,
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 10,
            'total' => 10,
            'line_subtotal' => 10,
            'discount_amount' => 0,
            'ppn_rate' => 0,
            'ppn_amount' => 0,
            'pph_rate' => 0,
            'pph_amount' => 0,
        ]);

        ReceivingReportItem::query()->create([
            'receiving_report_id' => $this->receivingReport->id,
            'purchase_order_item_id' => $poItem->id,
            'qty_good' => 1,
            'qty_bad' => 0,
        ]);
    }

    $html = view('pdf.receiving-report', [
        'receivingReport' => $this->receivingReport->fresh()->load([
            'purchaseOrder.supplier',
            'purchaseOrder.items.prsItem.prs',
            'items.purchaseOrderItem.item.unit',
            'items.purchaseOrderItem.item.category',
            'items.purchaseOrderItem.prsItem.prs.department',
            'customsDocumentType',
            'createdBy',
        ]),
        'isPreview' => true,
        'approvedByName' => 'Approver',
        'backgroundImageDataUri' => null,
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
    ])->render();

    preg_match_all('/class="cell item-cell" style="left: [^;]+; top: ([0-9.]+)mm/', $html, $topMatches);
    $tops = array_map('floatval', $topMatches[1] ?? []);

    expect($tops)->toHaveCount(3);

    $pitch = $tops[1] - $tops[0];

    // Antar item 1-baris: cukup agar tidak overlap, tapi lebih rapat dari 1 full line-box kosong.
    expect($pitch)->toBeGreaterThanOrEqual(3.0);
    expect($pitch)->toBeLessThan(4.5);
    expect($tops[2] - $tops[1])->toEqualWithDelta($pitch, 0.05);
});

it('packs space after multi-line item names tighter than a full wrap stride', function () {
    $unit = UnitOfMeasure::query()->create(['name' => 'Pieces', 'code' => 'PCS']);
    $category = ItemCategory::query()->create(['name' => 'Spare Parts', 'code' => 'SPR']);
    $purchaseOrder = $this->receivingReport->purchaseOrder;
    $longName = 'HEAVY DUTY INDUSTRIAL GRADE STAINLESS STEEL BEARING HOUSING ASSEMBLY WITH EXTENDED SHAFT';

    foreach (['A', 'B'] as $suffix) {
        $item = Item::query()->create([
            'name' => $longName.' '.$suffix,
            'code' => 'LONG-'.$suffix,
            'unit_of_measure_id' => $unit->id,
            'category_id' => $category->id,
            'type' => 'Raw Material',
            'stock_on_hand' => 0,
            'is_active' => true,
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 10,
            'total' => 10,
            'line_subtotal' => 10,
            'discount_amount' => 0,
            'ppn_rate' => 0,
            'ppn_amount' => 0,
            'pph_rate' => 0,
            'pph_amount' => 0,
        ]);

        ReceivingReportItem::query()->create([
            'receiving_report_id' => $this->receivingReport->id,
            'purchase_order_item_id' => $poItem->id,
            'qty_good' => 1,
            'qty_bad' => 0,
        ]);
    }

    $html = view('pdf.receiving-report', [
        'receivingReport' => $this->receivingReport->fresh()->load([
            'purchaseOrder.supplier',
            'purchaseOrder.items.prsItem.prs',
            'items.purchaseOrderItem.item.unit',
            'items.purchaseOrderItem.item.category',
            'items.purchaseOrderItem.prsItem.prs.department',
            'customsDocumentType',
            'createdBy',
        ]),
        'isPreview' => true,
        'approvedByName' => 'Approver',
        'backgroundImageDataUri' => null,
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
    ])->render();

    preg_match_all('/class="cell item-cell"[^>]*>(.*?)<\/div>/s', $html, $cellMatches);
    $firstCell = html_entity_decode($cellMatches[1][0] ?? '');
    $lineCount = substr_count($firstCell, "\n") + 1;

    expect($lineCount)->toBeGreaterThan(1);

    preg_match_all('/class="cell item-cell" style="left: [^;]+; top: ([0-9.]+)mm/', $html, $topMatches);
    $tops = array_map('floatval', $topMatches[1] ?? []);
    expect($tops)->toHaveCount(2);

    $advance = $tops[1] - $tops[0];
    $cellFontSize = round(16 * (160 / 210), 1);
    $wrapStrideMm = round(($cellFontSize * 1.12) * (25.4 / 72), 2);
    $glyphHeightMm = round(($cellFontSize * 0.72) * (25.4 / 72), 2);
    $naiveFullAdvance = $glyphHeightMm + (($lineCount - 1) * $wrapStrideMm) + 0.35;

    // Multi-line harus lebih rapat dari rumus full stride tanpa tail-pack.
    expect($advance)->toBeLessThan($naiveFullAdvance - 0.5);
    expect($advance)->toBeGreaterThan($glyphHeightMm + (($lineCount - 2) * $wrapStrideMm));
});

it('applies global overlay offset to receiving report field coordinates', function () {
    config([
        'receiving-report.offset_x_mm' => 2,
        'receiving-report.offset_y_mm' => 1.5,
    ]);

    $html = view('pdf.receiving-report', [
        'receivingReport' => $this->receivingReport->load([
            'purchaseOrder.supplier',
            'purchaseOrder.items.prsItem.prs',
            'items.purchaseOrderItem.item.unit',
            'items.purchaseOrderItem.item.category',
            'items.purchaseOrderItem.prsItem.prs.department',
            'customsDocumentType',
            'createdBy',
        ]),
        'isPreview' => true,
        'approvedByName' => 'Approver',
        'backgroundImageDataUri' => null,
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
    ])->render();

    $scaleX = 215 / 297;
    $scaleY = 160 / 210;
    $expectedLeft = round(round(37 * $scaleX, 2) + 2, 2);
    $expectedTop = round(round(41 * $scaleY, 2) + 1.5, 2);

    expect($html)
        ->toContain("left: {$expectedLeft}mm")
        ->toContain("top: {$expectedTop}mm");
});

it('renders full accounting entry debit and credit amounts without clipping large values', function () {
    $unit = UnitOfMeasure::query()->create(['name' => 'Pieces', 'code' => 'PCS']);
    $category = ItemCategory::query()->create(['name' => 'Spare Parts', 'code' => 'SPR']);
    $item = Item::query()->create([
        'name' => 'High Value Part',
        'code' => 'HV-ITEM',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $purchaseOrder = $this->receivingReport->purchaseOrder;

    $poItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 21360000,
        'total' => 21360000,
        'line_subtotal' => 21360000,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
        'meta' => ['term_of_payment_type' => 'credit'],
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $this->receivingReport->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 1,
        'qty_bad' => 0,
    ]);

    $html = view('pdf.receiving-report', [
        'receivingReport' => $this->receivingReport->fresh()->load([
            'purchaseOrder.supplier',
            'purchaseOrder.items.prsItem.prs',
            'items.purchaseOrderItem.item.unit',
            'items.purchaseOrderItem.item.category',
            'items.purchaseOrderItem.prsItem.prs.department',
            'customsDocumentType',
            'createdBy',
        ]),
        'isPreview' => true,
        'approvedByName' => 'Approver',
        'backgroundImageDataUri' => null,
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
    ])->render();

    expect($html)
        ->toContain('.acct-amount-cell')
        ->toContain('overflow: visible')
        ->toContain('21,360,000.00');

    preg_match_all('/class="acct-amount-cell right"[^>]*>([^<]+)</', $html, $amountMatches);
    $amounts = $amountMatches[1] ?? [];

    expect($amounts)->toContain('21,360,000.00');
    expect(collect($amounts)->filter(fn (string $amount) => $amount === '1,360,000.00'))->toBeEmpty();
});

it('prints rr items in purchase order item order even when rr rows were inserted reversed', function () {
    $unit = UnitOfMeasure::query()->create(['name' => 'Pieces', 'code' => 'PCS']);
    $category = ItemCategory::query()->create(['name' => 'Spare Parts', 'code' => 'SPR']);

    $firstItem = Item::query()->create([
        'name' => 'ALPHA LINE',
        'code' => 'PO-LINE-1',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);
    $secondItem = Item::query()->create([
        'name' => 'BRAVO LINE',
        'code' => 'PO-LINE-2',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $purchaseOrder = $this->receivingReport->purchaseOrder;

    $firstPoItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $firstItem->id,
        'quantity' => 1,
        'unit_price' => 10,
        'total' => 10,
        'line_subtotal' => 10,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
    ]);
    $secondPoItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $secondItem->id,
        'quantity' => 1,
        'unit_price' => 5,
        'total' => 5,
        'line_subtotal' => 5,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
    ]);

    // Insert RR rows in reverse of PO line order.
    ReceivingReportItem::query()->create([
        'receiving_report_id' => $this->receivingReport->id,
        'purchase_order_item_id' => $secondPoItem->id,
        'qty_good' => 1,
        'qty_bad' => 0,
    ]);
    ReceivingReportItem::query()->create([
        'receiving_report_id' => $this->receivingReport->id,
        'purchase_order_item_id' => $firstPoItem->id,
        'qty_good' => 1,
        'qty_bad' => 0,
    ]);

    $html = view('pdf.receiving-report', [
        'receivingReport' => $this->receivingReport->fresh()->load([
            'purchaseOrder.supplier',
            'purchaseOrder.items.prsItem.prs',
            'items.purchaseOrderItem.item.unit',
            'items.purchaseOrderItem.item.category',
            'items.purchaseOrderItem.prsItem.prs.department',
            'customsDocumentType',
            'createdBy',
        ]),
        'isPreview' => true,
        'approvedByName' => 'Approver',
        'backgroundImageDataUri' => null,
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
    ])->render();

    preg_match_all('/class="cell item-cell"[^>]*>(.*?)<\/div>/s', $html, $matches);
    $itemCells = array_map(
        fn (string $cell) => trim(html_entity_decode($cell)),
        $matches[1] ?? []
    );

    expect($itemCells)->toHaveCount(2);
    expect($itemCells[0])->toBe('ALPHA LINE');
    expect($itemCells[1])->toBe('BRAVO LINE');

    expect($html)
        ->toContain('po-number-bold')
        ->toContain('PO-TEST-001')
        ->toMatch('/\.po-number-bold\s*\{[^}]*font-weight:\s*bold/s')
        ->toMatch('/class="field po-number"[^>]*>RR-TEST-001/');
});
