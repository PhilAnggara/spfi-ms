<?php

use App\Models\AccountingInventoryTransaction;
use App\Models\AccountingInventoryTransactionLine;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\AccountingInventoryReportService;
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

it('builds stock card rows from accounting inventory monthly', function () {
    $document = AccountingInventoryTransaction::make([
        'category_id' => $this->category->id,
        'doc_type' => 'RR',
        'doc_number' => 'RR-RPT-001',
        'doc_date' => now()->startOfMonth()->toDateString(),
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'category' => $this->category,
    ]);

    app(AccountingInventoryService::class)->encodeDocument($document, [
        [
            'item_id' => $this->item->id,
            'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
            'quantity' => 10,
            'unit_of_measure_id' => $this->unit->id,
            'unit_cost' => 10,
            'amount' => 100,
        ],
    ], $this->user);

    $rows = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        $this->category->name,
    );

    expect($rows)->not->toBeEmpty();
    expect((float) $rows->first()['qty'])->toBe(10.0);
    expect((float) $rows->first()['unit_cost'])->toBe(10.0);
});

it('reports hasEncodedData when doc_tran rows exist', function () {
    expect(app(AccountingInventoryReportService::class)->hasEncodedData())->toBeFalse();

    $document = AccountingInventoryTransaction::make([
        'category_id' => $this->category->id,
        'doc_type' => 'CV',
        'doc_number' => 'CV-RPT-001',
        'doc_date' => now()->toDateString(),
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'category' => $this->category,
    ]);

    app(AccountingInventoryService::class)->encodeDocument($document, [
        [
            'item_id' => $this->item->id,
            'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
            'quantity' => 1,
            'unit_of_measure_id' => $this->unit->id,
            'unit_cost' => 5,
            'amount' => 5,
        ],
    ], $this->user);

    expect(app(AccountingInventoryReportService::class)->hasEncodedData())->toBeTrue();
});
