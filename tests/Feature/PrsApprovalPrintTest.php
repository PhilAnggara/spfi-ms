<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Office Supplies',
        'code' => 'OFF',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Test Item',
        'code' => 'ITM-PRINT-001',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);
});

function createDepartment(string $code, string $name, string $alias): Department
{
    return Department::query()->create([
        'name' => $name,
        'code' => $code,
        'alias' => $alias,
    ]);
}

function createPrsForPrint(Department $department, User $creator, Item $item): Prs
{
    $prs = Prs::query()->create([
        'prs_number' => 'PRS-'.$department->code.'-'.uniqid(),
        'user_id' => $creator->id,
        'department_id' => $department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Print approval test',
        'status' => 'REQUESTED',
    ]);

    PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $item->id,
        'quantity' => 2,
    ]);

    return $prs->fresh(['user.department', 'department', 'items.item.unit']);
}

function createPrintUser(Department $department, string $usernameSuffix): User
{
    return User::query()->create([
        'name' => 'PRS Print User '.$usernameSuffix,
        'username' => 'prs-print-'.$usernameSuffix,
        'email' => 'prs-print-'.$usernameSuffix.'@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
}

function resolveApproversForDepartment(Department $department): array
{
    $prefix = substr((string) $department->code, 0, 4);
    $usesOperationsApproval = in_array(
        $prefix,
        config('prs.operations_approval_department_prefixes', []),
        true
    );

    return $usesOperationsApproval
        ? config('prs.operations_approvers')
        : [config('prs.general_manager_approver')];
}

function renderApprovalPrintHtml(Prs $prs): string
{
    $approvers = resolveApproversForDepartment($prs->department);

    return view('pdf.prs-for-approval', [
        'prs' => $prs,
        'approvers' => $approvers,
        'usesOperationsApproval' => count($approvers) > 1,
    ])->render();
}

it('uses operations approvers for department prefix 7042', function () {
    $department = createDepartment('7042', 'Production', 'PRD');
    $creator = createPrintUser($department, '7042');
    $prs = createPrsForPrint($department, $creator, $this->item);
    $operationsApprovers = config('prs.operations_approvers');
    $gmApprover = config('prs.general_manager_approver');

    $html = renderApprovalPrintHtml($prs);

    expect($html)->toContain('Purchase Requisition Slip')
        ->and($html)->toContain('PT. SINAR PURE FOODS INTERNATIONAL')
        ->and($html)->toContain($operationsApprovers[0]['name'])
        ->and($html)->toContain($operationsApprovers[0]['title'])
        ->and($html)->toContain($operationsApprovers[1]['name'])
        ->and($html)->toContain($operationsApprovers[1]['title'])
        ->and(substr_count($html, 'Approved By'))->toBe(1)
        ->and($html)->not->toContain($gmApprover['name'])
        ->and($html)->not->toContain('QR Code')
        ->and($html)->not->toContain('qrCodeBase64')
        ->and($html)->not->toContain('for GM Approval');
});

it('uses operations approvers for sub-department codes like 7033C', function () {
    $department = createDepartment('7033C', 'Canning Line C', 'CNC');
    $creator = createPrintUser($department, '7033c');
    $prs = createPrsForPrint($department, $creator, $this->item);
    $operationsApprovers = config('prs.operations_approvers');
    $gmApprover = config('prs.general_manager_approver');

    $html = renderApprovalPrintHtml($prs);

    expect($html)->toContain($operationsApprovers[0]['name'])
        ->and($html)->toContain($operationsApprovers[1]['name'])
        ->and(substr_count($html, 'Approved By'))->toBe(1)
        ->and($html)->not->toContain($gmApprover['name'])
        ->and($html)->not->toContain('General Manager');
});

it('uses hardcoded general manager for other departments', function () {
    $department = createDepartment('8000', 'Administration', 'ADM');
    $creator = createPrintUser($department, '8000');
    $prs = createPrsForPrint($department, $creator, $this->item);
    $operationsApprovers = config('prs.operations_approvers');
    $gmApprover = config('prs.general_manager_approver');

    $html = renderApprovalPrintHtml($prs);

    expect($html)->toContain($gmApprover['name'])
        ->and($html)->toContain($gmApprover['title'])
        ->and($html)->not->toContain($operationsApprovers[0]['name'])
        ->and($html)->not->toContain($operationsApprovers[1]['name'])
        ->and($html)->toContain('This document requires approval before processing');
});

it('streams a pdf for the print route without qr payload', function () {
    $department = createDepartment('7056', 'Information Technology', 'IT');
    $creator = createPrintUser($department, 'route');
    $prs = createPrsForPrint($department, $creator, $this->item);

    $response = $this->actingAs($creator)->get(route('prs.print', $prs->id));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->getContent())->not->toContain('data:image/svg+xml;base64')
        ->and($response->getContent())->not->toContain('PRS QR Code');
});

it('uses reviewed_by fallback override when department has no manager', function () {
    $department = createDepartment('9001', 'No Manager Dept', 'NMD');
    $creator = createPrintUser($department, 'nofallback');
    $prs = createPrsForPrint($department, $creator, $this->item);

    config()->set('prs.reviewed_by_overrides', [
        '9001' => [
            'name' => 'PRS Supervisor',
            'title' => 'Department Supervisor',
            'priority' => 'fallback',
        ],
    ]);

    $html = renderApprovalPrintHtml($prs);

    expect($html)->toContain('PRS Supervisor')
        ->and($html)->toContain('Department Supervisor')
        ->and($html)->toContain('Reviewed By');
});

it('keeps department manager for reviewed_by when fallback override exists', function () {
    $department = createDepartment('9002', 'With Manager Dept', 'WMD');
    $creator = createPrintUser($department, 'keepmgr');
    $manager = User::query()->create([
        'name' => 'DB Manager Person',
        'username' => 'prs-print-db-mgr',
        'email' => 'prs-print-db-mgr@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Manager',
    ]);
    $prs = createPrsForPrint($department, $creator, $this->item);

    config()->set('prs.reviewed_by_overrides', [
        '9002' => [
            'name' => 'PRS Supervisor',
            'title' => 'Department Supervisor',
            'priority' => 'fallback',
        ],
    ]);

    $html = renderApprovalPrintHtml($prs);

    expect($html)->toContain($manager->name)
        ->and($html)->toContain(get_job_title($manager->load('department')))
        ->and($html)->not->toContain('PRS Supervisor');
});

it('uses reviewed_by override priority even when department has a manager', function () {
    $department = createDepartment('9003', 'Forced Reviewer Dept', 'FRD');
    $creator = createPrintUser($department, 'forceovr');
    User::query()->create([
        'name' => 'Should Be Ignored Manager',
        'username' => 'prs-print-ignored-mgr',
        'email' => 'prs-print-ignored-mgr@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Manager',
    ]);
    $prs = createPrsForPrint($department, $creator, $this->item);

    config()->set('prs.reviewed_by_overrides', [
        '9003' => [
            'name' => 'Forced Reviewer',
            'title' => 'Acting Supervisor',
            'priority' => 'override',
        ],
    ]);

    $html = renderApprovalPrintHtml($prs);

    expect($html)->toContain('Forced Reviewer')
        ->and($html)->toContain('Acting Supervisor')
        ->and($html)->not->toContain('Should Be Ignored Manager');
});

it('keys reviewed_by overrides by creator department not document department', function () {
    $creatorDepartment = createDepartment('9101', 'Creator Responsible Dept', 'CRD');
    $documentDepartment = createDepartment('9102', 'Target Document Dept', 'TDD');
    $creator = createPrintUser($creatorDepartment, 'crossdept');
    $prs = createPrsForPrint($documentDepartment, $creator, $this->item);

    config()->set('prs.reviewed_by_overrides', [
        '9101' => [
            'name' => 'Creator Dept Supervisor',
            'title' => 'Responsible Supervisor',
            'priority' => 'fallback',
        ],
        '9102' => [
            'name' => 'Document Dept Supervisor',
            'title' => 'Target Supervisor',
            'priority' => 'fallback',
        ],
    ]);

    $html = renderApprovalPrintHtml($prs);

    expect($html)->toContain('Creator Dept Supervisor')
        ->and($html)->toContain('Responsible Supervisor')
        ->and($html)->not->toContain('Document Dept Supervisor')
        ->and($html)->not->toContain('Target Supervisor');
});
