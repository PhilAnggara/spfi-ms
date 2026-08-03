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

    $otherDepartment = Department::query()->create([
        'name' => 'Maintenance',
        'code' => '7038',
        'alias' => 'MNT',
    ]);

    $this->user = User::query()->create([
        'name' => 'IM SWS Register User',
        'username' => 'im-sws-register',
        'email' => 'im-sws-register@example.test',
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
        'name' => 'Grease Tube',
        'code' => 'SP-GT-01',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Spare Parts',
        'stock_on_hand' => 100,
        'is_active' => true,
    ]);

    $now = now();

    $swsId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-REG-001',
        'sws_date' => '2026-06-12',
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'regular',
        'info' => 'SWS info notes',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('store_withdrawal_items')->insert([
        'store_withdrawal_id' => $swsId,
        'item_id' => $item->id,
        'product_code' => $item->code,
        'quantity' => 15,
        'uom' => 'PCS',
        'stock_on_hand_snapshot' => 100,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $otherSwsId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-REG-OTHER',
        'sws_date' => '2026-06-12',
        'department_id' => $otherDepartment->id,
        'department_code' => $otherDepartment->code,
        'type' => 'regular',
        'info' => 'Other dept',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('store_withdrawal_items')->insert([
        'store_withdrawal_id' => $otherSwsId,
        'item_id' => $item->id,
        'product_code' => $item->code,
        'quantity' => 5,
        'uom' => 'PCS',
        'stock_on_hand_snapshot' => 50,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

it('exports sws register excel filtered by department', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.sws-register'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'department_id' => $this->department->id,
        'format' => 'excel',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    $sheet = loadSwsRegisterSheet($response);

    expect($sheet->getCell('A8')->getValue())->toBe('SWS-REG-001');
    expect(Date::excelToDateTimeObject($sheet->getCell('B8')->getValue())->format('Y-m-d'))->toBe('2026-06-12');
    expect($sheet->getCell('C8')->getValue())->toBe('7037 - Production');
    expect($sheet->getCell('D8')->getValue())->toBe('SP-GT-01');
    expect((float) $sheet->getCell('G8')->getValue())->toBe(100.0);
    expect((float) $sheet->getCell('H8')->getValue())->toBe(15.0);
    expect($sheet->getCell('I8')->getValue())->toBe('IM SWS Register User');
    expect($sheet->getCell('J8')->getValue())->toBe('SWS info notes');
    expect($sheet->getCell('A9')->getValue())->not->toBe('SWS-REG-OTHER');
});

it('exports sws register pdf successfully', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.sws-register'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'format' => 'pdf',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

function loadSwsRegisterSheet(TestResponse $response): Worksheet
{
    $tmp = tempnam(sys_get_temp_dir(), 'im-sws-xlsx');
    file_put_contents($tmp, $response->streamedContent());

    try {
        return IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }
}
