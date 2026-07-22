<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Notification::fake();

    $this->department = Department::query()->create([
        'name' => 'Engineering',
        'code' => '7101',
        'alias' => 'ENG',
    ]);

    $this->otherDepartment = Department::query()->create([
        'name' => 'Legacy Engineering',
        'code' => '9999',
        'alias' => 'LEG',
    ]);

    $this->creator = User::query()->create([
        'name' => 'PRS Number Creator',
        'username' => 'prs-number-creator',
        'email' => 'prs-number-creator@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Spare Parts',
        'code' => 'SPR',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Bolt',
        'code' => 'BLT-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 25,
        'is_active' => true,
    ]);
});

function validPrsStorePayload(Department $department, Item $item): array
{
    return [
        'department_id' => $department->id,
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => '0',
        'remarks' => 'Number generation test',
        'prsItems' => [
            [
                'item_id' => $item->id,
                'quantity' => 2,
            ],
        ],
    ];
}

it('skips legacy prs numbers that share the department code but a different department id', function () {
    Prs::query()->create([
        'prs_number' => '71010000456',
        'user_id' => $this->creator->id,
        'department_id' => $this->otherDepartment->id,
        'prs_date' => '2016-05-01',
        'date_needed' => '2016-05-10',
        'is_capex' => false,
        'remarks' => 'Legacy import',
        'status' => 'APPROVED',
        'created_at' => '2016-05-01 08:00:00',
        'updated_at' => '2016-05-01 08:00:00',
    ]);

    $response = $this->actingAs($this->creator)
        ->post(route('prs.store'), validPrsStorePayload($this->department, $this->item));

    $response->assertRedirect(route('prs.index'));
    $response->assertSessionHas('success');

    $created = Prs::query()
        ->where('department_id', $this->department->id)
        ->latest('id')
        ->first();

    expect($created)->not->toBeNull()
        ->and($created->prs_number)->toBe('71010000457');
});

it('continues past soft-deleted prs numbers with the same department code prefix', function () {
    $legacy = Prs::query()->create([
        'prs_number' => '71010000099',
        'user_id' => $this->creator->id,
        'department_id' => $this->otherDepartment->id,
        'prs_date' => '2016-01-15',
        'date_needed' => '2016-01-20',
        'is_capex' => false,
        'remarks' => 'Soft-deleted legacy',
        'status' => 'REJECTED',
    ]);
    $legacy->delete();

    $this->actingAs($this->creator)
        ->post(route('prs.store'), validPrsStorePayload($this->department, $this->item))
        ->assertRedirect(route('prs.index'));

    $created = Prs::query()
        ->where('department_id', $this->department->id)
        ->latest('id')
        ->first();

    expect($created->prs_number)->toBe('71010000100');
});

it('starts at 0000001 when no matching department code prefix exists', function () {
    $this->actingAs($this->creator)
        ->post(route('prs.store'), validPrsStorePayload($this->department, $this->item))
        ->assertRedirect(route('prs.index'));

    $created = Prs::query()
        ->where('department_id', $this->department->id)
        ->latest('id')
        ->first();

    expect($created->prs_number)->toBe('71010000001');
});
