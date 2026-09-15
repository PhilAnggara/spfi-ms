<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsCanvassingItem;
use App\Models\PrsItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Canvassing History Dept',
        'code' => 'CH01',
        'alias' => 'CH',
    ]);

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'Spare Parts',
        'code' => 'SPR',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Canvass History Product',
        'code' => 'CANVHST1',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 1,
        'is_active' => true,
    ]);

    $this->seedUser = User::query()->create([
        'name' => 'Canvass History Seed',
        'username' => 'canvass-history-seed',
        'email' => 'canvass-history-seed@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->supplierSelected = Supplier::query()->create([
        'name' => 'Selected Supplier',
        'code' => 'SEL-SUP',
        'address' => 'Test Address',
        'phone' => '0811111111',
        'email' => 'sel-sup@example.test',
        'contact_person' => 'Contact Person',
        'created_by' => $this->seedUser->id,
    ]);

    $this->supplierNotSelected = Supplier::query()->create([
        'name' => 'Not Selected Supplier',
        'code' => 'NOS-SUP',
        'address' => 'Test Address 2',
        'phone' => '0822222222',
        'email' => 'nos-sup@example.test',
        'contact_person' => 'Contact Person 2',
        'created_by' => $this->seedUser->id,
    ]);

    $this->canvasser = User::query()->create([
        'name' => 'History Canvasser',
        'username' => 'canvass-history-canvasser',
        'email' => 'canvass-history-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->prs = Prs::query()->create([
        'prs_number' => 'PRS-CH-001',
        'user_id' => $this->seedUser->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'status' => 'CANVASSING',
    ]);

    $this->prsItem = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->item->id,
        'quantity' => 10,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $this->selectedQuote = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $this->supplierSelected->id,
        'unit_price' => 1000,
        'lead_time_days' => 5,
        'term_of_payment_type' => 'cash',
        'term_of_payment' => 'COD',
        'term_of_delivery' => 'FOB',
        'notes' => 'Selected quote',
        'canvased_by' => $this->canvasser->id,
    ]);

    $this->notSelectedQuote = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $this->supplierNotSelected->id,
        'unit_price' => 1200,
        'lead_time_days' => 7,
        'term_of_payment_type' => 'credit',
        'term_of_payment' => '30 days',
        'term_of_delivery' => 'CIF',
        'notes' => 'Not selected quote',
        'canvased_by' => $this->canvasser->id,
    ]);

    $this->prsItem->update([
        'selected_canvassing_item_id' => $this->selectedQuote->id,
        'selection_reason' => 'Lowest price',
    ]);
});

function createCanvassingHistoryUser(string $username, ?string $spatieRole = null): User
{
    $user = User::query()->create([
        'name' => "User {$username}",
        'username' => $username,
        'email' => "{$username}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    if ($spatieRole) {
        $user->assignRole($spatieRole);
    }

    return $user;
}

it('seeds view-canvassing-history for purchasing it and administrator but not im', function () {
    expect(Permission::findByName('view-canvassing-history'))->not->toBeNull();

    expect(Role::findByName('administrator')->hasPermissionTo('view-canvassing-history'))->toBeTrue();
    expect(Role::findByName('purchasing-manager')->hasPermissionTo('view-canvassing-history'))->toBeTrue();
    expect(Role::findByName('purchasing-staff')->hasPermissionTo('view-canvassing-history'))->toBeTrue();
    expect(Role::findByName('it-manager')->hasPermissionTo('view-canvassing-history'))->toBeTrue();
    expect(Role::findByName('it-staff')->hasPermissionTo('view-canvassing-history'))->toBeTrue();

    expect(Role::findByName('im-manager')->hasPermissionTo('view-canvassing-history'))->toBeFalse();
    expect(Role::findByName('im-supervisor')->hasPermissionTo('view-canvassing-history'))->toBeFalse();
    expect(Role::findByName('im-staff')->hasPermissionTo('view-canvassing-history'))->toBeFalse();
});

it('forbids im manager from product canvassing history and hides modal', function () {
    $user = createCanvassingHistoryUser('im-canvass-hist', 'im-manager');

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertSuccessful()
        ->assertSee('data-can-view-canvassing-history="0"', false)
        ->assertDontSee('product-canvassing-history-modal', false);

    $this->actingAs($user)
        ->get(route('product.canvassing-history', $this->item))
        ->assertForbidden();
});

it('allows purchasing staff to open canvassing history including non-selected quotes', function () {
    $user = createCanvassingHistoryUser('pur-canvass-hist', 'purchasing-staff');

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertSuccessful()
        ->assertSee('data-can-view-canvassing-history="1"', false)
        ->assertSee('product-canvassing-history-modal', false);

    $response = $this->actingAs($user)
        ->get(route('product.canvassing-history', $this->item))
        ->assertSuccessful();

    $data = collect($response->json('data'));

    expect($data)->toHaveCount(2)
        ->and($response->json('summary.quote_count'))->toBe(2)
        ->and($response->json('summary.supplier_count'))->toBe(2)
        ->and($response->json('summary.avg_unit_price'))->toEqual(1100.0)
        ->and($response->json('summary.min_unit_price'))->toEqual(1000.0);

    $selected = $data->firstWhere('id', $this->selectedQuote->id);
    $notSelected = $data->firstWhere('id', $this->notSelectedQuote->id);

    expect($selected)->not->toBeNull()
        ->and($selected['is_selected'])->toBeTrue()
        ->and($selected['supplier_code'])->toBe('SEL-SUP')
        ->and($selected['prs_number'])->toBe('PRS-CH-001')
        ->and($notSelected)->not->toBeNull()
        ->and($notSelected['is_selected'])->toBeFalse()
        ->and($notSelected['supplier_code'])->toBe('NOS-SUP')
        ->and($notSelected['notes'])->toBe('Not selected quote');
});

it('excludes soft-deleted canvassing quotes from history', function () {
    $thirdSupplier = Supplier::query()->create([
        'name' => 'Deleted Supplier',
        'code' => 'DEL-SUP',
        'address' => 'Deleted Address',
        'phone' => '0833333333',
        'email' => 'del-sup@example.test',
        'contact_person' => 'Deleted Contact',
        'created_by' => $this->seedUser->id,
    ]);

    $deletedQuote = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $thirdSupplier->id,
        'unit_price' => 500,
        'canvased_by' => $this->canvasser->id,
    ]);
    $deletedQuote->delete();

    $user = createCanvassingHistoryUser('soft-del-canvass-hist', 'purchasing-staff');

    $response = $this->actingAs($user)
        ->get(route('product.canvassing-history', $this->item))
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($this->selectedQuote->id)
        ->and($ids)->toContain($this->notSelectedQuote->id)
        ->and($ids)->not->toContain($deletedQuote->id)
        ->and($response->json('summary.quote_count'))->toBe(2);
});

it('paginates canvassing history beyond the first page', function () {
    foreach (range(1, 12) as $index) {
        $supplier = Supplier::query()->create([
            'name' => "Paged Supplier {$index}",
            'code' => sprintf('PG%02d', $index),
            'address' => 'Paged Address',
            'phone' => '0800000000',
            'email' => "paged{$index}@example.test",
            'contact_person' => 'Paged Contact',
            'created_by' => $this->seedUser->id,
        ]);

        PrsCanvassingItem::query()->create([
            'prs_id' => $this->prs->id,
            'prs_item_id' => $this->prsItem->id,
            'supplier_id' => $supplier->id,
            'unit_price' => 100 + $index,
            'canvased_by' => $this->canvasser->id,
        ]);
    }

    $user = createCanvassingHistoryUser('page-canvass-hist', 'purchasing-staff');

    $response = $this->actingAs($user)
        ->get(route('product.canvassing-history', [
            'item' => $this->item,
            'draw' => 2,
            'start' => 10,
            'length' => 5,
            'order' => [
                ['column' => 0, 'dir' => 'desc'],
            ],
        ]))
        ->assertSuccessful();

    expect($response->json('recordsTotal'))->toBe(14)
        ->and($response->json('recordsFiltered'))->toBe(14)
        ->and($response->json('data'))->toHaveCount(4);
});

it('forbids users without permission from exporting canvassing history', function () {
    $user = createCanvassingHistoryUser('im-export-canvass-hist', 'im-manager');

    $this->actingAs($user)
        ->post(route('product.canvassing-history.export', $this->item), [
            'format' => 'pdf',
        ])
        ->assertForbidden();
});

it('exports canvassing history as pdf', function () {
    $user = createCanvassingHistoryUser('pdf-export-canvass-hist', 'purchasing-staff');

    $response = $this->actingAs($user)
        ->post(route('product.canvassing-history.export', $this->item), [
            'format' => 'pdf',
        ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('exports canvassing history as excel', function () {
    $user = createCanvassingHistoryUser('xlsx-export-canvass-hist', 'purchasing-staff');

    $response = $this->actingAs($user)
        ->post(route('product.canvassing-history.export', $this->item), [
            'format' => 'excel',
        ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

it('rejects invalid canvassing history export format', function () {
    $user = createCanvassingHistoryUser('bad-export-canvass-hist', 'purchasing-staff');

    $this->actingAs($user)
        ->post(route('product.canvassing-history.export', $this->item), [
            'format' => 'csv',
        ])
        ->assertSessionHasErrors('format');
});
