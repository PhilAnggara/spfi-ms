<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockBalance;
use App\Models\StockInventory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7042',
        'alias' => 'IM',
    ]);

    $this->user = User::query()->create([
        'name' => 'IM Stock Report User',
        'username' => 'im-stock-report',
        'email' => 'im-stock-report@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'SPARE PARTS',
        'code' => 'SP',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Bearing 6205',
        'code' => 'SP-6205',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Spare Parts',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    StockInventory::query()->create([
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'balance' => 999,
        'start_balance' => 0,
        'average_price' => 0,
        'is_active' => true,
        'is_delete' => false,
    ]);

    // Prior month last end → Beginning 100
    StockBalance::query()->create([
        'date' => '2026-05-31',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 80,
        'qty_in1' => 20,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 0,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => 100,
        'acc_qty_in1' => 20,
        'acc_average_price_in1' => 0,
        'acc_qty_total' => 100,
        'acc_average_price_total' => 0,
    ]);

    // In-month movements on/before as_of → RR 50, TS 20, DR 10, Ending 120
    StockBalance::query()->create([
        'date' => '2026-06-15',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 100,
        'qty_in1' => 50,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 20,
        'qty_out2' => 0,
        'qty_out3' => 10,
        'end' => 120,
        'acc_qty_in1' => 50,
        'acc_average_price_in1' => 0,
        'acc_qty_total' => 120,
        'acc_average_price_total' => 0,
    ]);

    // After as_of — excluded from movements and ending
    StockBalance::query()->create([
        'date' => '2026-07-20',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 120,
        'qty_in1' => 99,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 77,
        'qty_out2' => 0,
        'qty_out3' => 55,
        'end' => 87,
        'acc_qty_in1' => 99,
        'acc_average_price_in1' => 0,
        'acc_qty_total' => 87,
        'acc_average_price_total' => 0,
    ]);
});

it('exports stock inventory excel from stock_balances ledger', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.stock-inventory'), [
        'as_of' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    expect($response->headers->get('content-type'))->not->toContain('application/vnd.ms-excel');
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');

    $sheet = loadStockInventorySheet($response);

    // Beginning 100, RR 50, TS 20, DR 10, Ending 120 (not inventory 999; not July moves)
    expect($sheet->getCell('A8')->getValue())->toBe('Bearing 6205');
    expect($sheet->getCell('B8')->getValue())->toBe('SP-6205');
    expect((float) $sheet->getCell('D8')->getValue())->toBe(100.0);
    expect((float) $sheet->getCell('E8')->getValue())->toBe(50.0);
    expect((float) $sheet->getCell('F8')->getValue())->toBe(20.0);
    expect((float) $sheet->getCell('G8')->getValue())->toBe(10.0);
    expect((float) $sheet->getCell('H8')->getValue())->toBe(120.0);
    expect((float) $sheet->getCell('D8')->getValue())->not->toBe(999.0);
    expect((float) $sheet->getCell('E8')->getValue())->not->toBe(99.0);

    $asOfCell = $sheet->getCell('B3');
    expect(is_numeric($asOfCell->getValue()))->toBeTrue();
    expect(Date::excelToDateTimeObject($asOfCell->getValue())->format('Y-m-d'))->toBe('2026-06-30');
    expect($sheet->getStyle('B3')->getNumberFormat()->getFormatCode())->toBe('dd-mmm-yyyy');

    expect($sheet->getCell('A12')->getValue())->toBe('Prepared by');
    expect($sheet->getCell('A15')->getValue())->toBe('IM Stock Report User');
    expect($sheet->getCell('D12')->getValue())->toBe('Checked by');
    expect($sheet->getCell('D15')->getValue())->toBe('Daniel Watuna');
    expect($sheet->getCell('D17')->getValue())->toBe('IM Supervisor');
    expect($sheet->getCell('G12')->getValue())->toBe('Approved by');
    expect($sheet->getCell('G15')->getValue())->toBe('Rommy Tendean');
    expect($sheet->getCell('G17')->getValue())->toBe('IM Manager');
});

it('falls back to earliest begin in as-of month when no prior month end', function () {
    StockBalance::query()->where('date', '2026-05-31')->delete();

    $response = $this->actingAs($this->user)->post(route('im.reports.stock-inventory'), [
        'as_of' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    $sheet = loadStockInventorySheet($response);

    // Earliest June begin = 100
    expect((float) $sheet->getCell('D8')->getValue())->toBe(100.0);
});

it('falls back to stock_inventories balance when no ledger ending', function () {
    StockBalance::query()->delete();

    $response = $this->actingAs($this->user)->post(route('im.reports.stock-inventory'), [
        'as_of' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    $sheet = loadStockInventorySheet($response);

    expect((float) $sheet->getCell('H8')->getValue())->toBe(999.0);
});

it('exports stock inventory pdf successfully', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.stock-inventory'), [
        'as_of' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'format' => 'pdf',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('resolves spare parts filter to stored PARTS category name', function () {
    $this->category->update(['name' => 'PARTS', 'code' => 'PARTS']);

    $response = $this->actingAs($this->user)->post(route('im.reports.stock-inventory'), [
        'as_of' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();
    $sheet = loadStockInventorySheet($response);

    expect($sheet->getCell('A8')->getValue())->toBe('Bearing 6205');
    expect($sheet->getCell('B8')->getValue())->toBe('SP-6205');
    expect((float) $sheet->getCell('D8')->getValue())->toBe(100.0);
});

it('forbids unrelated roles from exporting stock inventory', function () {
    $outsider = User::query()->create([
        'name' => 'Purchasing Outsider',
        'username' => 'purchasing-stock-outsider',
        'email' => 'purchasing-stock-outsider@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $outsider->assignRole('purchasing-staff');

    $this->actingAs($outsider)->post(route('im.reports.stock-inventory'), [
        'as_of' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'format' => 'excel',
    ])->assertForbidden();
});

function loadStockInventorySheet(TestResponse $response): Worksheet
{
    $tmp = tempnam(sys_get_temp_dir(), 'im-stock-xlsx');
    file_put_contents($tmp, $response->streamedContent());

    try {
        return IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }
}
