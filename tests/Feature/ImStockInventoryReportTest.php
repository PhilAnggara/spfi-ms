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
    expect($response->headers->get('content-type'))->toContain('application/vnd.ms-excel');

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    // Beginning 100, RR 50, TS 20, DR 10, Ending 120 (not inventory 999; not July moves)
    expect($content)
        ->toContain('Bearing 6205')
        ->toContain('SP-6205')
        ->toContain('100,00')
        ->toContain('50,00')
        ->toContain('20,00')
        ->toContain('10,00')
        ->toContain('120,00')
        ->toContain('Prepared by')
        ->toContain('IM Stock Report User')
        ->toContain('Checked by')
        ->toContain('Daniel Watuna')
        ->toContain('IM Supervisor')
        ->toContain('Approved by')
        ->toContain('Rommy Tendean')
        ->toContain('IM Manager')
        ->not->toContain('999,00')
        ->not->toContain('99,00')
        ->not->toContain('77,00')
        ->not->toContain('55,00')
        ->not->toContain('87,00');
});

it('falls back to earliest begin in as-of month when no prior month end', function () {
    StockBalance::query()->where('date', '2026-05-31')->delete();

    $response = $this->actingAs($this->user)->post(route('im.reports.stock-inventory'), [
        'as_of' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    // Earliest June begin = 100
    expect($content)->toContain('100,00');
});

it('falls back to stock_inventories balance when no ledger ending', function () {
    StockBalance::query()->delete();

    $response = $this->actingAs($this->user)->post(route('im.reports.stock-inventory'), [
        'as_of' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('999,00');
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
