<?php

use App\Models\Currency;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsCanvassingItem;
use App\Models\PrsItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'History Export Dept',
        'code' => 'HX01',
        'alias' => 'HX',
    ]);

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'Spare Parts',
        'code' => 'SPR',
    ]);

    $this->itemA = Item::query()->create([
        'name' => 'History Product A',
        'code' => 'HXPROD01',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 1,
        'is_active' => true,
    ]);

    $this->itemB = Item::query()->create([
        'name' => 'History Product B',
        'code' => 'HXPROD02',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 2,
        'is_active' => true,
    ]);

    $this->seedUser = User::query()->create([
        'name' => 'History Export Seed',
        'username' => 'history-export-seed',
        'email' => 'history-export-seed@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->currency = Currency::query()->create([
        'name' => 'US Dollar',
        'code' => 'USD',
        'symbol' => '$',
        'created_by' => $this->seedUser->id,
    ]);

    $this->supplier = Supplier::query()->create([
        'name' => 'History Export Supplier',
        'code' => 'HX-SUP',
        'address' => 'Test Address',
        'phone' => '0811111111',
        'email' => 'hx-sup@example.test',
        'contact_person' => 'Contact Person',
        'created_by' => $this->seedUser->id,
    ]);

    $this->supplierOutOfRange = Supplier::query()->create([
        'name' => 'History Export Supplier Out',
        'code' => 'HX-SUP-O',
        'address' => 'Test Address 2',
        'phone' => '0822222222',
        'email' => 'hx-sup-o@example.test',
        'contact_person' => 'Contact Person 2',
        'created_by' => $this->seedUser->id,
    ]);

    $this->inRangePo = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'created_by' => $this->seedUser->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-HX-IN',
    ]);
    $this->inRangePo->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

    $this->outOfRangePo = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'created_by' => $this->seedUser->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-HX-OUT',
    ]);
    $this->outOfRangePo->forceFill(['created_at' => now()->subYears(6)])->saveQuietly();

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $this->inRangePo->id,
        'item_id' => $this->itemA->id,
        'quantity' => 5,
        'unit_price' => 100,
        'line_subtotal' => 500,
        'total' => 500,
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $this->outOfRangePo->id,
        'item_id' => $this->itemA->id,
        'quantity' => 2,
        'unit_price' => 50,
        'line_subtotal' => 100,
        'total' => 100,
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $this->inRangePo->id,
        'item_id' => $this->itemB->id,
        'quantity' => 3,
        'unit_price' => 200,
        'line_subtotal' => 600,
        'total' => 600,
    ]);

    $this->canvasser = User::query()->create([
        'name' => 'History Export Canvasser',
        'username' => 'history-export-canvasser',
        'email' => 'history-export-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->prs = Prs::query()->create([
        'prs_number' => 'PRS-HX-001',
        'user_id' => $this->seedUser->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'status' => 'CANVASSING',
    ]);

    $this->prsItemA = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->itemA->id,
        'quantity' => 10,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $this->inRangeQuote = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItemA->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 1000,
        'lead_time_days' => 5,
        'term_of_payment_type' => 'cash',
        'term_of_payment' => 'COD',
        'term_of_delivery' => 'FOB',
        'notes' => 'In range quote',
        'canvased_by' => $this->canvasser->id,
    ]);
    $this->inRangeQuote->forceFill(['created_at' => now()->subDays(5)])->saveQuietly();

    $this->outOfRangeQuote = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItemA->id,
        'supplier_id' => $this->supplierOutOfRange->id,
        'unit_price' => 900,
        'lead_time_days' => 4,
        'term_of_payment_type' => 'credit',
        'term_of_payment' => '30 days',
        'term_of_delivery' => 'CIF',
        'notes' => 'Out of range quote',
        'canvased_by' => $this->canvasser->id,
    ]);
    $this->outOfRangeQuote->forceFill(['created_at' => now()->subYears(6)])->saveQuietly();

    $this->prsItemA->update([
        'selected_canvassing_item_id' => $this->inRangeQuote->id,
        'selection_reason' => 'Best price',
    ]);
});

function createHistoryExportUser(string $username, ?string $spatieRole = null): User
{
    $user = User::query()->create([
        'name' => "User {$username}",
        'username' => $username,
        'email' => "{$username}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    if ($spatieRole) {
        $user->assignRole($spatieRole);
    }

    return $user;
}

it('shows export history controls for users with history permissions', function () {
    $user = createHistoryExportUser('hx-show-controls', 'purchasing-staff');

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertSuccessful()
        ->assertSee('product-export-history-btn', false)
        ->assertSee('product-history-export-modal', false)
        ->assertSee(route('product.history.export'), false);
});

it('forbids users without history permissions from exporting', function () {
    $user = createHistoryExportUser('hx-no-perm', 'im-manager');

    $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [$this->itemA->id],
            'history_type' => 'purchase',
            'date_from' => now()->subYears(5)->toDateString(),
            'date_to' => now()->toDateString(),
            'format' => 'pdf',
        ])
        ->assertForbidden();
});

it('forbids purchase-only users from exporting canvassing history', function () {
    $user = createHistoryExportUser('hx-purchase-only');
    $user->givePermissionTo(Permission::findByName('view-purchase-history'));

    $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [$this->itemA->id],
            'history_type' => 'canvassing',
            'date_from' => now()->subYears(5)->toDateString(),
            'date_to' => now()->toDateString(),
            'format' => 'pdf',
        ])
        ->assertForbidden();
});

it('forbids canvassing-only users from exporting purchase history', function () {
    $user = createHistoryExportUser('hx-canvass-only');
    $user->givePermissionTo(Permission::findByName('view-canvassing-history'));

    $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [$this->itemA->id],
            'history_type' => 'purchase',
            'date_from' => now()->subYears(5)->toDateString(),
            'date_to' => now()->toDateString(),
            'format' => 'pdf',
        ])
        ->assertForbidden();
});

it('exports purchase history as pdf for selected items within date range', function () {
    $user = createHistoryExportUser('hx-purchase-pdf', 'purchasing-staff');

    $response = $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [$this->itemA->id, $this->itemB->id],
            'history_type' => 'purchase',
            'date_from' => now()->subYears(5)->toDateString(),
            'date_to' => now()->toDateString(),
            'format' => 'pdf',
        ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('purchase-history-');
});

it('exports purchase history as excel for selected items', function () {
    $user = createHistoryExportUser('hx-purchase-xlsx', 'purchasing-staff');

    $response = $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [$this->itemA->id],
            'history_type' => 'purchase',
            'date_from' => now()->subYears(5)->toDateString(),
            'date_to' => now()->toDateString(),
            'format' => 'excel',
        ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

it('exports canvassing history as pdf for selected items within date range', function () {
    $user = createHistoryExportUser('hx-canvass-pdf', 'purchasing-staff');

    $response = $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [$this->itemA->id],
            'history_type' => 'canvassing',
            'date_from' => now()->subYears(5)->toDateString(),
            'date_to' => now()->toDateString(),
            'format' => 'pdf',
        ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('canvassing-history-');
});

it('exports canvassing history as excel for selected items', function () {
    $user = createHistoryExportUser('hx-canvass-xlsx', 'purchasing-staff');

    $response = $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [$this->itemA->id],
            'history_type' => 'canvassing',
            'date_from' => now()->subYears(5)->toDateString(),
            'date_to' => now()->toDateString(),
            'format' => 'excel',
        ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
});

it('rejects empty item selection', function () {
    $user = createHistoryExportUser('hx-empty-ids', 'purchasing-staff');

    $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [],
            'history_type' => 'purchase',
            'date_from' => now()->subYears(5)->toDateString(),
            'date_to' => now()->toDateString(),
            'format' => 'pdf',
        ])
        ->assertSessionHasErrors('item_ids');
});

it('rejects invalid history type', function () {
    $user = createHistoryExportUser('hx-bad-type', 'purchasing-staff');

    $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [$this->itemA->id],
            'history_type' => 'inventory',
            'date_from' => now()->subYears(5)->toDateString(),
            'date_to' => now()->toDateString(),
            'format' => 'pdf',
        ])
        ->assertSessionHasErrors('history_type');
});

it('rejects date_to earlier than date_from', function () {
    $user = createHistoryExportUser('hx-bad-dates', 'purchasing-staff');

    $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [$this->itemA->id],
            'history_type' => 'purchase',
            'date_from' => now()->toDateString(),
            'date_to' => now()->subDay()->toDateString(),
            'format' => 'pdf',
        ])
        ->assertSessionHasErrors('date_to');
});

it('rejects invalid export format', function () {
    $user = createHistoryExportUser('hx-bad-format', 'purchasing-staff');

    $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [$this->itemA->id],
            'history_type' => 'purchase',
            'date_from' => now()->subYears(5)->toDateString(),
            'date_to' => now()->toDateString(),
            'format' => 'csv',
        ])
        ->assertSessionHasErrors('format');
});

it('groups purchase history excel rows by item and places currency beside unit price', function () {
    $user = createHistoryExportUser('hx-purchase-grouped', 'purchasing-staff');

    $response = $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [$this->itemA->id, $this->itemB->id],
            'history_type' => 'purchase',
            'date_from' => now()->subYears(5)->toDateString(),
            'date_to' => now()->toDateString(),
            'format' => 'excel',
        ]);

    $response->assertSuccessful();
    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
    $sheet = loadHistoryExportSheetFromBinary($response->streamedContent());

    expect($sheet->getCell('F7')->getValue())->toBe('Unit Price')
        ->and($sheet->getCell('G7')->getValue())->toBe('Curr')
        ->and((string) $sheet->getCell('A8')->getValue())->toContain('[HXPROD01]')
        ->and((string) $sheet->getCell('A8')->getValue())->toContain('History Product A')
        ->and((string) $sheet->getCell('A8')->getValue())->not->toContain('Product:')
        ->and((string) $sheet->getCell('G9')->getValue())->toBe('USD')
        ->and((string) $sheet->getCell('A9')->getValue())->not->toBe('HXPROD01');
});

it('shows currency from related po on canvassing export only for supplier with po', function () {
    $linkedPo = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'created_by' => $this->seedUser->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-HX-CANVASS',
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $linkedPo->id,
        'prs_item_id' => $this->prsItemA->id,
        'item_id' => $this->itemA->id,
        'quantity' => 10,
        'unit_price' => 1000,
        'line_subtotal' => 10000,
        'total' => 10000,
    ]);

    $user = createHistoryExportUser('hx-canvass-currency', 'purchasing-staff');

    $response = $this->actingAs($user)
        ->post(route('product.history.export'), [
            'item_ids' => [$this->itemA->id],
            'history_type' => 'canvassing',
            'date_from' => now()->subYears(5)->toDateString(),
            'date_to' => now()->toDateString(),
            'format' => 'excel',
        ]);

    $response->assertSuccessful();
    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
    $sheet = loadHistoryExportSheetFromBinary($response->streamedContent());

    expect($sheet->getCell('E7')->getValue())->toBe('Unit Price')
        ->and($sheet->getCell('F7')->getValue())->toBe('Curr')
        ->and((string) $sheet->getCell('A8')->getValue())->toContain('[HXPROD01]')
        ->and((string) $sheet->getCell('A8')->getValue())->not->toContain('Product:')
        ->and((string) $sheet->getCell('F9')->getValue())->toBe('USD')
        ->and((string) $sheet->getCell('C9')->getValue())->toBe('HX-SUP');
});

function loadHistoryExportSheetFromBinary(string $content)
{
    $tmp = tempnam(sys_get_temp_dir(), 'hx-xlsx');
    file_put_contents($tmp, $content);

    try {
        return IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }
}
