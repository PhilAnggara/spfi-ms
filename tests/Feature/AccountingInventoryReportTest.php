<?php

use App\Models\AccountingInventoryLedger;
use App\Models\AccountingInventoryTransaction;
use App\Models\AccountingInventoryTransactionLine;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\AccountingInventoryReportService;
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
        'name' => 'Report User',
        'username' => 'report-user-'.uniqid(),
        'email' => 'report-'.uniqid().'@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('accounting-staff');

    $this->category = ItemCategory::query()->create([
        'name' => 'CHEMICAL',
        'code' => 'CHEM-RPT-'.uniqid(),
    ]);

    $this->unit = UnitOfMeasure::query()->create(['name' => 'Kilogram', 'code' => 'KG-RPT-'.uniqid()]);
    $this->item = Item::query()->create([
        'name' => 'Report Chemical',
        'code' => 'CHEM-RPT-ITEM-'.uniqid(),
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'is_active' => true,
    ]);
});

it('builds stock card rows from accounting inventory ledger', function () {
    $transaction = AccountingInventoryTransaction::query()->create([
        'category_id' => $this->category->id,
        'doc_type' => 'RR',
        'doc_number' => 'RR|RR-RPT-001|'.$this->category->id,
        'doc_date' => now()->startOfMonth()->toDateString(),
        'status' => AccountingInventoryTransaction::STATUS_ENCODED,
        'total_amount' => 100,
        'encoded_by' => $this->user->id,
        'encoded_at' => now(),
        'gl_status' => 'not_required',
        'created_by' => $this->user->id,
    ]);

    $line = $transaction->lines()->create([
        'item_id' => $this->item->id,
        'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
        'quantity' => 10,
        'unit_of_measure_id' => $this->unit->id,
        'unit_cost' => 10,
        'amount' => 100,
        'sort_order' => 0,
    ]);

    AccountingInventoryLedger::query()->create([
        'accounting_inventory_transaction_id' => $transaction->id,
        'accounting_inventory_transaction_line_id' => $line->id,
        'category_id' => $this->category->id,
        'item_id' => $this->item->id,
        'doc_type' => 'RR',
        'doc_number' => $transaction->doc_number,
        'doc_date' => $transaction->doc_date,
        'movement_date' => $transaction->doc_date,
        'direction' => 'in',
        'quantity' => 10,
        'unit_cost' => 10,
        'amount' => 100,
        'balance_qty' => 10,
        'balance_amount' => 100,
        'weighted_unit_cost' => 10,
        'created_by' => $this->user->id,
    ]);

    $service = app(AccountingInventoryReportService::class);
    $rows = $service->stockCardRows(now()->format('Y-m'), 'CHEMICAL');

    expect($rows)->not->toBeEmpty();
    expect((float) $rows->first()['qty'])->toBe(10.0);
    expect((float) $rows->first()['unit_cost'])->toBe(10.0);
});

it('exports accounting stock card from ledger when encoded data exists', function () {
    $transaction = AccountingInventoryTransaction::query()->create([
        'category_id' => $this->category->id,
        'doc_type' => 'CV',
        'doc_number' => 'CV-RPT-001',
        'doc_date' => now()->startOfMonth()->toDateString(),
        'status' => AccountingInventoryTransaction::STATUS_ENCODED,
        'total_amount' => 50,
        'encoded_by' => $this->user->id,
        'encoded_at' => now(),
        'gl_status' => 'not_required',
        'created_by' => $this->user->id,
    ]);

    $line = $transaction->lines()->create([
        'item_id' => $this->item->id,
        'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
        'quantity' => 5,
        'unit_of_measure_id' => $this->unit->id,
        'unit_cost' => 10,
        'amount' => 50,
        'sort_order' => 0,
    ]);

    AccountingInventoryLedger::query()->create([
        'accounting_inventory_transaction_id' => $transaction->id,
        'accounting_inventory_transaction_line_id' => $line->id,
        'category_id' => $this->category->id,
        'item_id' => $this->item->id,
        'doc_type' => 'CV',
        'doc_number' => $transaction->doc_number,
        'doc_date' => $transaction->doc_date,
        'movement_date' => $transaction->doc_date,
        'direction' => 'in',
        'quantity' => 5,
        'unit_cost' => 10,
        'amount' => 50,
        'balance_qty' => 5,
        'balance_amount' => 50,
        'weighted_unit_cost' => 10,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->post(route('accounting.reports.stock-card'), [
        'month' => now()->format('Y-m'),
        'category' => 'CHEMICAL',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();
});
