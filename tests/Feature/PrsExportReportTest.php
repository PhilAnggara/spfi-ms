<?php

use App\Http\Controllers\PrsController;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Information Technology',
        'code' => '7056',
        'alias' => 'IT',
    ]);

    $this->admin = User::query()->create([
        'name' => 'PRS Export Admin',
        'username' => 'prs-export-admin',
        'email' => 'prs-export-admin@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->admin->assignRole('administrator');

    $this->imStaff = User::query()->create([
        'name' => 'PRS Export IM Staff',
        'username' => 'prs-export-im-staff',
        'email' => 'prs-export-im-staff@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->imStaff->assignRole('im-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'OFFICE SUPPLIES',
        'code' => 'OFF',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Export Test Item',
        'code' => 'PRS-EXP-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->itemTwo = Item::query()->create([
        'name' => 'Export Test Item Two',
        'code' => 'PRS-EXP-002',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 5,
        'is_active' => true,
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => '70560000901',
        'user_id' => $this->admin->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'PRS export test',
        'status' => 'APPROVED',
    ]);

    PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->item->id,
        'quantity' => 3,
        'is_direct_purchase' => false,
    ]);

    PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->itemTwo->id,
        'quantity' => 2,
        'is_direct_purchase' => false,
    ]);

    $this->month = now()->format('Y-m');
});

function prsExportRequest(string $uri, array $payload, User $user): Request
{
    $request = Request::create($uri, 'POST', $payload);
    $request->setUserResolver(fn () => $user);

    return $request;
}

function assertPrsOpenXmlStream(StreamedResponse $response): string
{
    expect($response->getStatusCode())->toBeGreaterThanOrEqual(200);
    expect($response->getStatusCode())->toBeLessThan(300);
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    expect($response->headers->get('content-type'))->not->toContain('application/vnd.ms-excel');
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');

    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

function loadPrsSheetFromBinary(string $content)
{
    $tmp = tempnam(sys_get_temp_dir(), 'prs-xlsx');
    file_put_contents($tmp, $content);

    try {
        return IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }
}

it('exports prs list as analytical pdf', function () {
    $response = app(PrsController::class)->export(prsExportRequest(route('prs.export'), [
        'start_month' => $this->month,
        'end_month' => $this->month,
        'format' => 'pdf',
    ], $this->admin));

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(200);
    expect($response->getStatusCode())->toBeLessThan(300);
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('exports prs list as openxml xlsx with item lines and blank continuation', function () {
    $response = app(PrsController::class)->export(prsExportRequest(route('prs.export'), [
        'start_month' => $this->month,
        'end_month' => $this->month,
        'format' => 'excel',
    ], $this->admin));

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    $content = assertPrsOpenXmlStream($response);
    $sheet = loadPrsSheetFromBinary($content);

    expect((string) $sheet->getCell('B8')->getValue())->toBe('70560000901');
    expect((string) $sheet->getCell('F8')->getValue())->toBe('PRS-EXP-001');
    expect((string) $sheet->getCell('B9')->getValue())->toBe('');
    expect((string) $sheet->getCell('F9')->getValue())->toBe('PRS-EXP-002');
});

it('forbids prs list export for roles outside the allow list', function () {
    try {
        app(PrsController::class)->export(prsExportRequest(route('prs.export'), [
            'start_month' => $this->month,
            'end_month' => $this->month,
            'format' => 'pdf',
        ], $this->imStaff));

        expect(false)->toBeTrue('Expected HttpException was not thrown.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('allows im-staff to export prs by department', function () {
    $this->actingAs($this->imStaff);

    $response = app(PrsController::class)->exportByDepartment(prsExportRequest(route('prs.export-by-department'), [
        'start_month' => $this->month,
        'end_month' => $this->month,
        'format' => 'pdf',
    ], $this->imStaff));

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(200);
    expect($response->getStatusCode())->toBeLessThan(300);
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('exports prs by department as analytical pdf', function () {
    $this->actingAs($this->admin);

    $response = app(PrsController::class)->exportByDepartment(prsExportRequest(route('prs.export-by-department'), [
        'start_month' => $this->month,
        'end_month' => $this->month,
        'format' => 'pdf',
    ], $this->admin));

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(200);
    expect($response->getStatusCode())->toBeLessThan(300);
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('exports prs by department as openxml xlsx with blank continuation rows', function () {
    $this->actingAs($this->admin);

    $response = app(PrsController::class)->exportByDepartment(prsExportRequest(route('prs.export-by-department'), [
        'start_month' => $this->month,
        'end_month' => $this->month,
        'format' => 'excel',
    ], $this->admin));

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    $content = assertPrsOpenXmlStream($response);
    $sheet = loadPrsSheetFromBinary($content);

    expect($sheet->getCell('A8')->getValue())->toContain('Department:');
    expect($sheet->getCell('A8')->getValue())->toContain('7056');
    expect((string) $sheet->getCell('A9')->getValue())->toBe('70560000901');
    expect((string) $sheet->getCell('G9')->getValue())->toBe('PRS-EXP-001');
    expect((string) $sheet->getCell('A10')->getValue())->toBe('');
    expect((string) $sheet->getCell('G10')->getValue())->toBe('PRS-EXP-002');
});
