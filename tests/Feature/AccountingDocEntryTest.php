<?php

use App\Models\AccountingCode;
use App\Models\AccountingDocTransaction;
use App\Models\AccountingDocTransactionLine;
use App\Models\Delivery;
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
use App\Services\Accounting\ReceivingReportEntryGenerator;
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

    AccountingCode::query()->create([
        'code' => '169',
        'desc' => 'CAPEX ASSET',
    ]);

    AccountingCode::query()->create([
        'code' => '551',
        'desc' => 'PPN MASUKAN',
    ]);
});

function createDocEntryUser(string $role): User
{
    $user = User::query()->create([
        'name' => "Doc Entry {$role}",
        'username' => 'doc-entry-'.str_replace(' ', '-', strtolower($role)).'-'.uniqid(),
        'email' => 'doc-entry-'.str_replace(' ', '-', strtolower($role)).'-'.uniqid().'@example.test',
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    $user->assignRole($role);

    return $user;
}

function createCapexReceivingReport(User $creator): ReceivingReport
{
    $supplier = Supplier::query()->create([
        'name' => 'CAPEX Supplier',
        'code' => 'CPX-SUP',
        'created_by' => $creator->id,
    ]);

    $unit = UnitOfMeasure::query()->create(['name' => 'Pieces', 'code' => 'PCS-'.uniqid()]);
    $category = ItemCategory::query()->create(['name' => 'Spare Parts', 'code' => 'SPR-'.uniqid()]);
    $item = Item::query()->create([
        'name' => 'CAPEX Item',
        'code' => 'CPX-ITEM-'.uniqid(),
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $prs = Prs::query()->create([
        'prs_number' => 'PRS-CPX-'.uniqid(),
        'prs_date' => '2026-07-01',
        'date_needed' => '2026-07-15',
        'department_id' => test()->department->id,
        'user_id' => $creator->id,
        'status' => 'PO_CREATED',
        'is_capex' => true,
    ]);

    $prsItem = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $item->id,
        'quantity' => 5,
        'status' => 'PO_CREATED',
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $creator->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-CPX-'.uniqid(),
    ]);

    $poItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'prs_item_id' => $prsItem->id,
        'item_id' => $item->id,
        'quantity' => 5,
        'unit_price' => 1000,
        'total' => 5000,
        'line_subtotal' => 5000,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
    ]);

    $receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-CPX-001',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => '2026-07-10',
        'created_by' => $creator->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $receivingReport->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 2,
        'qty_bad' => 0,
    ]);

    return $receivingReport;
}

function createDeliveryForDocEntry(User $creator): Delivery
{
    return Delivery::query()->create([
        'dr_number' => 'DR-DOC-001',
        'dr_date' => '2026-07-12',
        'from_name' => 'Main Warehouse',
        'created_by' => $creator->id,
    ]);
}

it('allows accounting staff to view doc entry index and show', function () {
    $user = createDocEntryUser('accounting-staff');
    $receivingReport = createCapexReceivingReport($user);

    $this->actingAs($user)
        ->get(route('accounting.doc-entries.index'))
        ->assertSuccessful()
        ->assertSee('Document Entry')
        ->assertSee('Pending Entry')
        ->assertSee('Reset');

    $this->actingAs($user)
        ->get(route('accounting.doc-entries.show', ['docType' => 'rr', 'id' => $receivingReport->id]))
        ->assertSuccessful()
        ->assertSee('RR-CPX-001')
        ->assertSee('169');
});

it('returns realtime list panel over ajax', function () {
    $user = createDocEntryUser('accounting-staff');
    createCapexReceivingReport($user);

    $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('accounting.doc-entries.index', ['status' => 'pending']))
        ->assertSuccessful()
        ->assertSee('Pending Entry')
        ->assertSee('RR-CPX-001');
});

it('shows estimated amount for pending rr and links doc number to source', function () {
    $user = createDocEntryUser('accounting-staff');
    $receivingReport = createCapexReceivingReport($user);

    $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('accounting.doc-entries.index', ['status' => 'pending']))
        ->assertSuccessful()
        ->assertSee('2,000.00')
        ->assertSee(route('receiving-reports.print', [
            'receivingReport' => $receivingReport->id,
            'mode' => 'preview',
        ]), false);
});

it('filters documents by doc type', function () {
    $user = createDocEntryUser('accounting-staff');
    createCapexReceivingReport($user);
    createDeliveryForDocEntry($user);

    $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('accounting.doc-entries.index', ['doc_type' => 'DR']))
        ->assertSuccessful()
        ->assertSee('DR-DOC-001')
        ->assertDontSee('RR-CPX-001');
});

it('does not include transfer slips in the register', function () {
    $user = createDocEntryUser('accounting-staff');
    createCapexReceivingReport($user);

    $this->actingAs($user)
        ->get(route('accounting.doc-entries.index'))
        ->assertSuccessful()
        ->assertSee('Receiving Reports and Delivery Receipts')
        ->assertDontSee('>TS</option>', false)
        ->assertDontSee('value="TS"', false);
});

it('forbids purchasing staff from doc entry pages', function () {
    $user = createDocEntryUser('purchasing-staff');

    $this->actingAs($user)
        ->get(route('accounting.doc-entries.index'))
        ->assertForbidden();
});

it('creates draft transaction when opening a pending receiving report', function () {
    $user = createDocEntryUser('accounting-manager');
    $receivingReport = createCapexReceivingReport($user);

    expect(AccountingDocTransaction::query()->count())->toBe(0);

    $this->actingAs($user)
        ->get(route('accounting.doc-entries.show', ['docType' => 'rr', 'id' => $receivingReport->id]))
        ->assertSuccessful()
        ->assertSee('Save & Encode');

    $transaction = AccountingDocTransaction::query()->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe('draft');
    expect($transaction->lines)->not->toBeEmpty();
    expect($transaction->lines->first()->account_code)->toBe('169');
});

it('loads entry panel in modal ajax mode', function () {
    $user = createDocEntryUser('accounting-manager');
    $receivingReport = createCapexReceivingReport($user);

    $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('accounting.doc-entries.show', [
            'docType' => 'rr',
            'id' => $receivingReport->id,
            'modal' => 1,
        ]))
        ->assertSuccessful()
        ->assertSee('Save & Encode')
        ->assertDontSee('Back to List');
});

it('encodes edited lines and marks transaction as encoded locally', function () {
    $user = createDocEntryUser('accounting-manager');
    $receivingReport = createCapexReceivingReport($user);

    $this->actingAs($user)->get(route('accounting.doc-entries.show', [
        'docType' => 'rr',
        'id' => $receivingReport->id,
    ]));
    $transaction = AccountingDocTransaction::query()->firstOrFail();

    $this->actingAs($user)
        ->put(route('accounting.doc-entries.update', $transaction), [
            'lines' => [
                [
                    'group_code' => '',
                    'account_code' => '169',
                    'description' => 'CAPEX ASSET',
                    'debit' => 2000,
                    'credit' => 0,
                ],
                [
                    'group_code' => '',
                    'account_code' => '201',
                    'description' => 'ACCOUNTS PAYABLE',
                    'debit' => 0,
                    'credit' => 2000,
                ],
            ],
        ])
        ->assertRedirect(route('accounting.doc-entries.index'))
        ->assertSessionHas('success');

    $transaction->refresh();
    expect($transaction->status)->toBe('encoded');
    expect($transaction->encoded_by)->toBe($user->id);
    expect($transaction->encoded_at)->not->toBeNull();
    expect($transaction->lines)->toHaveCount(2);
    expect((float) $transaction->total_debit)->toBe(2000.0);
    expect((float) $transaction->total_credit)->toBe(2000.0);
    expect((float) $transaction->variance)->toBe(0.0);

    $this->actingAs($user)
        ->get(route('accounting.doc-entries.index', ['status' => 'encoded']))
        ->assertSuccessful()
        ->assertSee('Encoded')
        ->assertSee('RR-CPX-001');
});

it('blocks editing when document is already encoded locally', function () {
    $user = createDocEntryUser('accounting-manager');
    $receivingReport = createCapexReceivingReport($user);

    $transaction = AccountingDocTransaction::query()->create([
        'doc_type' => 'RR',
        'source_type' => ReceivingReport::class,
        'source_id' => $receivingReport->id,
        'doc_number' => 'RR-CPX-001',
        'doc_date' => '2026-07-10',
        'po_number' => 'PO-CPX-001',
        'supplier_code' => 'CPX-SUP',
        'supplier_name' => 'CAPEX Supplier',
        'cost_code_total' => 169,
        'acct_code_total' => 169,
        'total_debit' => 2000,
        'total_credit' => 2000,
        'variance' => 0,
        'status' => 'encoded',
        'encoded_by' => $user->id,
        'encoded_at' => now(),
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    AccountingDocTransactionLine::query()->create([
        'accounting_doc_transaction_id' => $transaction->id,
        'line_no' => 1,
        'account_code' => '169',
        'description' => 'CAPEX ASSET',
        'debit' => 2000,
        'credit' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('accounting.doc-entries.show', ['docType' => 'rr', 'id' => $receivingReport->id]))
        ->assertSuccessful()
        ->assertSee('Encoded')
        ->assertDontSee('Save & Encode');

    $this->actingAs($user)
        ->put(route('accounting.doc-entries.update', $transaction), [
            'lines' => [
                [
                    'group_code' => '',
                    'account_code' => '169',
                    'description' => 'CAPEX ASSET',
                    'debit' => 1000,
                    'credit' => 0,
                ],
            ],
        ])
        ->assertRedirect(route('accounting.doc-entries.index'))
        ->assertSessionHas('error');
});

it('opens empty draft for delivery and includes orphan imported encoded docs', function () {
    $user = createDocEntryUser('accounting-manager');
    $delivery = createDeliveryForDocEntry($user);

    $this->actingAs($user)
        ->get(route('accounting.doc-entries.show', ['docType' => 'dr', 'id' => $delivery->id]))
        ->assertSuccessful()
        ->assertSee('DR-DOC-001')
        ->assertSee('Add Line');

    expect(
        AccountingDocTransaction::query()->where('doc_type', 'DR')->where('doc_number', 'DR-DOC-001')->value('status')
    )->toBe('draft');

    $orphan = AccountingDocTransaction::query()->create([
        'doc_type' => 'RR',
        'source_type' => null,
        'source_id' => null,
        'doc_number' => 'RR-LEGACY-ORPHAN',
        'doc_date' => '2026-01-05',
        'cost_code_total' => 0,
        'acct_code_total' => 500,
        'total_debit' => 500,
        'total_credit' => 500,
        'variance' => 0,
        'status' => 'encoded',
        'legacy_tran_id' => 999001,
        'encoded_at' => now(),
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('accounting.doc-entries.index', ['status' => 'encoded']))
        ->assertSuccessful()
        ->assertSee('RR-LEGACY-ORPHAN')
        ->assertDontSee('Imported');

    $this->actingAs($user)
        ->get(route('accounting.doc-entries.transaction', $orphan))
        ->assertSuccessful()
        ->assertSee('RR-LEGACY-ORPHAN')
        ->assertDontSee('Save & Encode');
});

it('shows encoded amount from total debit and cost center only for 4-digit accounts', function () {
    $user = createDocEntryUser('accounting-staff');

    $transaction = AccountingDocTransaction::query()->create([
        'doc_type' => 'RR',
        'source_type' => null,
        'source_id' => null,
        'doc_number' => 'RR-LEGACY-AMOUNT',
        'doc_date' => '2026-01-06',
        'cost_code_total' => 7010,
        'acct_code_total' => 2213,
        'total_debit' => 16344000,
        'total_credit' => 16344000,
        'variance' => 0,
        'status' => 'encoded',
        'legacy_tran_id' => 999002,
        'encoded_at' => now(),
    ]);

    AccountingDocTransactionLine::query()->create([
        'accounting_doc_transaction_id' => $transaction->id,
        'line_no' => 1,
        'group_code' => '7010',
        'account_code' => '2003',
        'description' => 'FISH',
        'debit' => 16344000,
        'credit' => 0,
    ]);

    AccountingDocTransactionLine::query()->create([
        'accounting_doc_transaction_id' => $transaction->id,
        'line_no' => 2,
        'group_code' => '1011',
        'account_code' => '201',
        'description' => 'AP',
        'debit' => 0,
        'credit' => 16344000,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('accounting.doc-entries.index', ['status' => 'encoded', 'keyword' => 'RR-LEGACY-AMOUNT']))
        ->assertSuccessful()
        ->assertSee('16,344,000.00')
        ->assertDontSee('7,010.00');

    $this->actingAs($user)
        ->get(route('accounting.doc-entries.transaction', $transaction))
        ->assertSuccessful()
        ->assertSee('Cost Center')
        ->assertDontSee('Group Code')
        ->assertSee('7010')
        ->assertSee('2003')
        ->assertSee('cost-center-display', false);
});

it('returns account descriptions from lookup endpoint', function () {
    $user = createDocEntryUser('accounting-staff');

    $this->actingAs($user)
        ->getJson(route('accounting.doc-entries.account-lookup', ['q' => '169']))
        ->assertSuccessful()
        ->assertJsonFragment([
            'code' => '169',
            'description' => 'CAPEX ASSET',
        ]);
});

it('uses capex account 169 in receiving report entry generator', function () {
    $user = createDocEntryUser('accounting-manager');
    $receivingReport = createCapexReceivingReport($user)->load([
        'purchaseOrder.supplier',
        'purchaseOrder.currency',
        'purchaseOrder.items.prsItem.prs',
        'items.purchaseOrderItem.item.unit',
        'items.purchaseOrderItem.item.category',
        'items.purchaseOrderItem.prsItem.prs.department',
    ]);

    $payload = app(ReceivingReportEntryGenerator::class)->generate($receivingReport);

    expect(collect($payload['lines'])->pluck('account_code'))->toContain('169');
});

it('does not create duplicate transactions for the same receiving report', function () {
    $user = createDocEntryUser('accounting-manager');
    $receivingReport = createCapexReceivingReport($user);

    $this->actingAs($user)->get(route('accounting.doc-entries.show', [
        'docType' => 'rr',
        'id' => $receivingReport->id,
    ]));
    $this->actingAs($user)->get(route('accounting.doc-entries.show', [
        'docType' => 'rr',
        'id' => $receivingReport->id,
    ]));

    expect(AccountingDocTransaction::query()->count())->toBe(1);
});
