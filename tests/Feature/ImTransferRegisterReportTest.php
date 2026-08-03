<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Production',
        'code' => '7037',
        'alias' => 'PROD',
    ]);

    $this->user = User::query()->create([
        'name' => 'IM TS Register User',
        'username' => 'im-ts-register',
        'email' => 'im-ts-register@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'SPARE PARTS',
        'code' => 'SP',
    ]);

    $item = Item::query()->create([
        'name' => 'Bolt M8',
        'code' => 'SP-BOLT-8',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Spare Parts',
        'stock_on_hand' => 50,
        'is_active' => true,
    ]);

    $now = now();

    $swsId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-TS-REG-001',
        'sws_date' => '2026-06-10',
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'regular',
        'info' => null,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $swsItemId = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $swsId,
        'item_id' => $item->id,
        'product_code' => $item->code,
        'quantity' => 20,
        'uom' => 'PCS',
        'stock_on_hand_snapshot' => 50,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $normalTsId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-REG-NORMAL',
        'ts_date' => '2026-06-15',
        'store_withdrawal_id' => $swsId,
        'for_production' => false,
        'remarks' => 'Normal TS info',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $normalTsId,
        'store_withdrawal_item_id' => $swsItemId,
        'item_id' => $item->id,
        'product_code' => $item->code,
        'quantity' => 12,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $othersTsId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-REG-OTHERS',
        'ts_date' => '2026-06-16',
        'store_withdrawal_id' => $swsId,
        'for_production' => true,
        'remarks' => 'Others TS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $othersTsId,
        'store_withdrawal_item_id' => $swsItemId,
        'item_id' => $item->id,
        'product_code' => $item->code,
        'quantity' => 8,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

it('exports transfer register excel filtered to normal type', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.transfer-register'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'ts_type' => 'normal',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    $sheet = loadTsRegisterSheet($response);

    expect($sheet->getCell('A8')->getValue())->toBe('TS-REG-NORMAL');
    expect(Date::excelToDateTimeObject($sheet->getCell('B8')->getValue())->format('Y-m-d'))->toBe('2026-06-15');
    expect($sheet->getCell('C8')->getValue())->toBe('SWS-TS-REG-001');
    expect($sheet->getCell('D8')->getValue())->toBe('Normal');
    expect($sheet->getCell('E8')->getValue())->toBe('7037 - Production');
    expect($sheet->getCell('F8')->getValue())->toBe('SP-BOLT-8');
    expect((float) $sheet->getCell('I8')->getValue())->toBe(20.0);
    expect((float) $sheet->getCell('J8')->getValue())->toBe(12.0);
    expect($sheet->getCell('K8')->getValue())->toBe('IM TS Register User');
    expect($sheet->getCell('L8')->getValue())->toBe('Normal TS info');
    expect($sheet->getCell('A9')->getValue())->not->toBe('TS-REG-OTHERS');
});

it('exports transfer register excel for others type only', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.transfer-register'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'ts_type' => 'others',
        'format' => 'excel',
    ]);

    $sheet = loadTsRegisterSheet($response);

    expect($sheet->getCell('A8')->getValue())->toBe('TS-REG-OTHERS');
    expect($sheet->getCell('D8')->getValue())->toBe('Others');
});

it('exports transfer register pdf successfully', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.transfer-register'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'ts_type' => 'all',
        'format' => 'pdf',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

function loadTsRegisterSheet(TestResponse $response): Worksheet
{
    $tmp = tempnam(sys_get_temp_dir(), 'im-ts-xlsx');
    file_put_contents($tmp, $response->streamedContent());

    try {
        return IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }
}
