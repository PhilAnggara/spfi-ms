<?php

use App\Models\Currency;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\CurrencyExchangeRateService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Accounting',
        'code' => '7200',
        'alias' => 'ACC',
    ]);

    $this->seedUser = User::query()->create([
        'name' => 'Seed User',
        'username' => 'seed-user',
        'email' => 'seed-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->usdCurrency = Currency::query()->create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'created_by' => $this->seedUser->id,
    ]);
});

function createExchangeRateUser(string $role): User
{
    $user = User::query()->create([
        'name' => "Exchange {$role}",
        'username' => "exchange-{$role}",
        'email' => "exchange-{$role}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    $user->assignRole($role);

    return $user;
}

function seedUsdRates(): CurrencyExchangeRateService
{
    $service = app(CurrencyExchangeRateService::class);
    $manager = createExchangeRateUser('accounting-manager');

    $service->storeRate(16000, '2026-07-01', 'July opening', 'USD', $manager->id);
    $service->storeRate(16100, '2026-07-15', 'Mid July', 'USD', $manager->id);
    $service->storeRate(16200, '2026-08-01', 'August opening', 'USD', $manager->id);

    return $service;
}

it('allows accounting manager to store usd exchange rate', function () {
    $user = createExchangeRateUser('accounting-manager');

    $response = $this->actingAs($user)->post(route('accounting.exchange-rates.store'), [
        'currency_code' => 'USD',
        'rate_to_idr' => 16050.5,
        'effective_date' => '2026-07-10',
        'notes' => 'Test rate',
    ]);

    $response->assertRedirect(route('accounting.exchange-rates.index', ['currency' => 'USD']));
    $response->assertSessionHas('success');

    expect(app(CurrencyExchangeRateService::class)->rateForDate('2026-07-10', 'USD')?->rate_to_idr)
        ->toBe('16050.5000');
});

it('allows accounting supervisor to store usd exchange rate', function () {
    $user = createExchangeRateUser('accounting-supervisor');

    $this->actingAs($user)->post(route('accounting.exchange-rates.store'), [
        'currency_code' => 'USD',
        'rate_to_idr' => 15990,
        'effective_date' => '2026-07-05',
    ])->assertRedirect()->assertSessionHas('success');
});

it('allows accounting staff to view exchange rates but not update', function () {
    $user = createExchangeRateUser('accounting-staff');

    $this->actingAs($user)
        ->get(route('accounting.exchange-rates.index'))
        ->assertSuccessful()
        ->assertSee('Exchange Rates')
        ->assertSee('USD — US Dollar');

    $this->actingAs($user)
        ->post(route('accounting.exchange-rates.store'), [
            'currency_code' => 'USD',
            'rate_to_idr' => 15000,
            'effective_date' => '2026-07-01',
        ])
        ->assertForbidden();
});

it('forbids purchasing staff from viewing exchange rates', function () {
    $user = createExchangeRateUser('purchasing-staff');

    $this->actingAs($user)
        ->get(route('accounting.exchange-rates.index'))
        ->assertForbidden();
});

it('rejects invalid exchange rate values', function () {
    $user = createExchangeRateUser('accounting-manager');

    $this->actingAs($user)
        ->from(route('accounting.exchange-rates.index'))
        ->post(route('accounting.exchange-rates.store'), [
            'currency_code' => 'USD',
            'rate_to_idr' => 0,
            'effective_date' => '2026-07-01',
        ])
        ->assertSessionHasErrors(['rate_to_idr']);
});

it('defaults unsupported currency filter to usd', function () {
    $user = createExchangeRateUser('accounting-staff');

    $this->actingAs($user)
        ->get(route('accounting.exchange-rates.index', ['currency' => 'EUR']))
        ->assertSuccessful()
        ->assertSee('Current USD Rate');
});

it('sorts exchange rate history by exchange rate ascending', function () {
    $user = createExchangeRateUser('accounting-staff');
    seedUsdRates();

    $this->actingAs($user)
        ->get(route('accounting.exchange-rates.index', [
            'currency' => 'USD',
            'sort_by' => 'rate_to_idr',
            'sort_direction' => 'asc',
        ]))
        ->assertSuccessful()
        ->assertSeeInOrder(['16,000.0000', '16,100.0000', '16,200.0000']);
});

it('sorts exchange rate history by recorded date descending', function () {
    $user = createExchangeRateUser('accounting-staff');
    seedUsdRates();

    $this->actingAs($user)
        ->get(route('accounting.exchange-rates.index', [
            'currency' => 'USD',
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
        ]))
        ->assertSuccessful()
        ->assertSeeInOrder(['August opening', 'Mid July', 'July opening']);
});

it('defaults invalid history sort parameters', function () {
    $service = app(CurrencyExchangeRateService::class);

    expect($service->resolveHistorySort('invalid', 'sideways'))->toBe([
        'sort_by' => 'effective_date',
        'sort_direction' => 'desc',
    ]);
});

it('rejects unsupported currency on store', function () {
    $user = createExchangeRateUser('accounting-manager');

    $this->actingAs($user)
        ->from(route('accounting.exchange-rates.index'))
        ->post(route('accounting.exchange-rates.store'), [
            'currency_code' => 'EUR',
            'rate_to_idr' => 17000,
            'effective_date' => '2026-07-01',
        ])
        ->assertSessionHasErrors(['currency_code']);
});

it('resolves rate for date using past nearest then future nearest', function () {
    $service = seedUsdRates();

    expect($service->rateForDate('2026-07-15', 'USD')?->rate_to_idr)->toBe('16100.0000');
    expect($service->rateForDate('2026-07-10', 'USD')?->rate_to_idr)->toBe('16000.0000');
    expect($service->rateForDate('2026-07-20', 'USD')?->rate_to_idr)->toBe('16100.0000');
    expect($service->rateForDate('2026-06-05', 'USD')?->rate_to_idr)->toBe('16000.0000');
    expect($service->currentRate('USD')?->rate_to_idr)->toBe('16200.0000');
});

it('converts usd receiving report amounts using received date rate on print', function () {
    $service = seedUsdRates();
    $admin = createExchangeRateUser('administrator');

    $supplier = Supplier::query()->create([
        'name' => 'USD Supplier',
        'code' => 'USD-SUP',
        'created_by' => $admin->id,
    ]);

    $unit = UnitOfMeasure::query()->create(['name' => 'Pieces', 'code' => 'PCS']);
    $category = ItemCategory::query()->create(['name' => 'Spare Parts', 'code' => 'SPR']);
    $item = Item::query()->create([
        'name' => 'USD Item',
        'code' => 'USD-ITEM',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $prs = Prs::query()->create([
        'prs_number' => 'PRS-USD-001',
        'prs_date' => '2026-07-01',
        'date_needed' => '2026-07-15',
        'department_id' => $this->department->id,
        'user_id' => $admin->id,
        'status' => 'PO_CREATED',
        'is_capex' => false,
    ]);

    $prsItem = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'status' => 'PO_CREATED',
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'currency_id' => $this->usdCurrency->id,
        'created_by' => $admin->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-USD-001',
    ]);

    $poItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'prs_item_id' => $prsItem->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'unit_price' => 100,
        'total' => 1000,
        'line_subtotal' => 1000,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
    ]);

    $receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-USD-001',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => '2026-07-10',
        'created_by' => $admin->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $receivingReport->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 2,
        'qty_bad' => 0,
    ]);

    $conversion = $service->resolveConversionForPurchaseOrder('USD', '2026-07-10');

    expect($conversion['should_convert'])->toBeTrue();
    expect($conversion['rate_to_idr'])->toBe(16000.0);
    expect($conversion['effective_date'])->toBe('2026-07-01');

    $html = view('pdf.receiving-report', [
        'receivingReport' => $receivingReport->load([
            'purchaseOrder.supplier',
            'purchaseOrder.currency',
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
        'currencyConversion' => $conversion,
    ])->render();

    expect($html)
        ->toContain('USD rate 16,000.0000 (effective 01 Jul 2026)')
        ->toContain('3,200,000.00');
});
