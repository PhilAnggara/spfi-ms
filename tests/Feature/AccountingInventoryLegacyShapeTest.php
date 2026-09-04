<?php

use App\Models\AccountingInventoryDocTran;
use App\Models\AccountingInventoryMonthly;
use App\Models\AccountingInventoryTransaction;
use App\Models\AccountingInventoryTransactionLine;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\AccountingInventoryLegacyPostingService;
use App\Services\Accounting\AccountingInventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Accounting',
        'code' => '7200',
        'alias' => 'ACC',
    ]);

    $this->user = User::query()->create([
        'name' => 'Legacy Shape User',
        'username' => 'legacy-shape-'.uniqid(),
        'email' => 'legacy-shape-'.uniqid().'@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('accounting-staff');

    $this->category = ItemCategory::query()->create([
        'name' => 'SPICES AND INGREDIENTS',
        'code' => 'SPICE-'.uniqid(),
    ]);

    $this->unit = UnitOfMeasure::query()->create(['name' => 'Kilogram', 'code' => 'KG-'.uniqid()]);
    $this->item = Item::query()->create([
        'name' => 'Spice Item',
        'code' => '10006',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'is_active' => true,
    ]);
});

it('posts signed qty into doc_tran and monthly when encoding', function () {
    $transaction = AccountingInventoryTransaction::query()->create([
        'category_id' => $this->category->id,
        'doc_type' => 'CV',
        'doc_number' => 'CV-LEGACY-001',
        'doc_date' => '2024-03-15',
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'gl_status' => 'not_required',
        'created_by' => $this->user->id,
    ]);

    $transaction->lines()->create([
        'item_id' => $this->item->id,
        'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
        'quantity' => 10,
        'unit_of_measure_id' => $this->unit->id,
        'unit_cost' => 100,
        'amount' => 1000,
        'sort_order' => 0,
    ]);

    $transaction->lines()->create([
        'item_id' => $this->item->id,
        'direction' => AccountingInventoryTransactionLine::DIRECTION_OUT,
        'quantity' => 3,
        'unit_of_measure_id' => $this->unit->id,
        'unit_cost' => 100,
        'amount' => 300,
        'sort_order' => 1,
    ]);

    app(AccountingInventoryService::class)->encode($transaction->fresh(['lines.item.unit', 'category']), $this->user);

    $rows = AccountingInventoryDocTran::query()
        ->where('accounting_inventory_transaction_id', $transaction->id)
        ->orderBy('id')
        ->get();

    expect($rows)->toHaveCount(2);
    expect((float) $rows[0]->qty)->toBe(10.0);
    expect((float) $rows[1]->qty)->toBe(-3.0);
    expect($rows[0]->doc_code)->toBe('CV');
    expect($rows[0]->doc_no)->toBe('CV-LEGACY-001');
    expect($rows[0]->item_code)->toBe('10006');
    expect($rows[0]->category)->toBe('SPICES AND INGREDIENTS');

    $monthly = AccountingInventoryMonthly::query()
        ->whereIn('accounting_inventory_doc_tran_id', $rows->pluck('id'))
        ->orderBy('id')
        ->get();

    expect($monthly)->toHaveCount(2);
    expect((float) $monthly[0]->begining)->toBe(0.0);
    expect((float) $monthly[0]->ending)->toBe(10.0);
    expect((float) $monthly[1]->begining)->toBe(10.0);
    expect((float) $monthly[1]->ending)->toBe(7.0);
    expect($monthly[0]->tran_date->format('Y-m-d'))->toBe('2024-03-31');
});

it('resolves master ids and maps signed legacy qty semantics', function () {
    $service = app(AccountingInventoryLegacyPostingService::class);

    $resolved = $service->resolveMasterIds('10006', 'SPICES AND INGREDIENTS');
    expect($resolved['item_id'])->toBe($this->item->id);
    expect($resolved['category_id'])->toBe($this->category->id);

    $missing = $service->resolveMasterIds('NO-SUCH-ITEM', 'UNKNOWN CAT');
    expect($missing['item_id'])->toBeNull();
    expect($missing['category_id'])->toBeNull();
});

it('registers legacy import and parity artisan commands', function () {
    $commands = array_keys(\Illuminate\Support\Facades\Artisan::all());

    expect($commands)->toContain('accounting-inventory:import-legacy');
    expect($commands)->toContain('accounting-inventory:validate-legacy-parity');
});

it('removes doc_tran and monthly rows when voiding an encoded transaction', function () {
    $transaction = AccountingInventoryTransaction::query()->create([
        'category_id' => $this->category->id,
        'doc_type' => 'JV',
        'doc_number' => 'JV-LEGACY-VOID',
        'doc_date' => '2024-04-01',
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'gl_status' => 'not_required',
        'created_by' => $this->user->id,
    ]);

    $transaction->lines()->create([
        'item_id' => $this->item->id,
        'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
        'quantity' => 5,
        'unit_of_measure_id' => $this->unit->id,
        'unit_cost' => 20,
        'amount' => 100,
        'sort_order' => 0,
    ]);

    $service = app(AccountingInventoryService::class);
    $encoded = $service->encode($transaction->fresh(['lines.item.unit', 'category']), $this->user);

    expect(AccountingInventoryDocTran::query()->where('accounting_inventory_transaction_id', $encoded->id)->count())->toBe(1);

    $service->voidTransaction($encoded->fresh(['lines']), $this->user, 'test void');

    expect(AccountingInventoryDocTran::query()->where('accounting_inventory_transaction_id', $encoded->id)->count())->toBe(0);
    expect(AccountingInventoryMonthly::query()->whereHas('docTran', fn ($q) => $q->where('accounting_inventory_transaction_id', $encoded->id))->count())->toBe(0);
});
