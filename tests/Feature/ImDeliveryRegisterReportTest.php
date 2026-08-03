<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
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
        'name' => 'Inventory Management',
        'code' => '7042',
        'alias' => 'IM',
    ]);

    $this->user = User::query()->create([
        'name' => 'IM DR Register User',
        'username' => 'im-dr-register',
        'email' => 'im-dr-register@example.test',
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
        'name' => 'FINISHED GOODS',
        'code' => 'FG',
    ]);

    $item = Item::query()->create([
        'name' => 'Canned Tuna',
        'code' => 'FG-TUNA-01',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Finished Goods',
        'stock_on_hand' => 200,
        'is_active' => true,
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'DR Customer',
        'code' => 'CUS-DR-001',
        'created_by' => $this->user->id,
    ]);

    $now = now();

    $drId = (int) DB::table('deliveries')->insertGetId([
        'dr_number' => 'DR-REG-001',
        'dr_date' => '2026-06-20',
        'from_name' => 'IM - PT. SPFI',
        'supplier_id' => $supplier->id,
        'remarks' => 'DR remarks here',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('delivery_items')->insert([
        'delivery_id' => $drId,
        'item_id' => $item->id,
        'product_code' => $item->code,
        'uom' => 'PCS',
        'quantity' => 25,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

it('exports delivery register excel with line columns', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.delivery-register'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    $sheet = loadDrRegisterSheet($response);

    expect($sheet->getCell('A8')->getValue())->toBe('DR-REG-001');
    expect(Date::excelToDateTimeObject($sheet->getCell('B8')->getValue())->format('Y-m-d'))->toBe('2026-06-20');
    expect($sheet->getCell('C8')->getValue())->toBe('IM - PT. SPFI');
    expect($sheet->getCell('D8')->getValue())->toBe('DR Customer');
    expect($sheet->getCell('E8')->getValue())->toBe('FG-TUNA-01');
    expect($sheet->getCell('F8')->getValue())->toBe('Canned Tuna');
    expect((float) $sheet->getCell('H8')->getValue())->toBe(25.0);
    expect($sheet->getCell('I8')->getValue())->toBe('IM DR Register User');
    expect($sheet->getCell('J8')->getValue())->toBe('DR remarks here');
});

it('exports delivery register pdf successfully', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.delivery-register'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'format' => 'pdf',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

function loadDrRegisterSheet(TestResponse $response): Worksheet
{
    $tmp = tempnam(sys_get_temp_dir(), 'im-dr-xlsx');
    file_put_contents($tmp, $response->streamedContent());

    try {
        return IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }
}
