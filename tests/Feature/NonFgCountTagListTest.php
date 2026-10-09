<?php

use App\Models\CountTagLocation;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NonFgCountTag;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7042-CTL',
        'alias' => 'IM-CTL',
    ]);

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-CTL',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'PARTS',
        'code' => 'PARTS-CTL',
    ]);

    $this->item = Item::query()->create([
        'name' => 'List Count Tag Item',
        'code' => 'CTLIST01',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Spare Part',
        'stock_on_hand' => 8,
        'is_active' => true,
    ]);

    $this->location = CountTagLocation::query()->create([
        'name' => 'BONITO',
        'legacy_id' => 1,
    ]);
});

function createCountTagListUser(string $username, array $permissions = []): User
{
    $user = User::query()->create([
        'name' => "Count Tag List {$username}",
        'username' => $username,
        'email' => "{$username}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

it('lists imported count tags for users with view permission', function () {
    $user = createCountTagListUser('ct-list-view', ['view-count-tag-non-fg']);

    NonFgCountTag::query()->create([
        'legacy_id' => 501,
        'count_tag_number' => 'CT-501',
        'count_tag_date' => '2024-06-15',
        'item_id' => $this->item->id,
        'item_code' => $this->item->code,
        'item_category_id' => $this->category->id,
        'location_id' => $this->location->id,
        'location_name' => 'BONITO',
        'qty' => 4.5,
        'uom_code' => 'PCS',
        'tran_date' => '2024-06-30',
        'created_by_name' => 'STOREKEEPER',
    ]);

    $this->actingAs($user)
        ->get(route('count-tags.non-fg.index'))
        ->assertSuccessful()
        ->assertSee('CT-501')
        ->assertSee('CTLIST01')
        ->assertSee('PARTS')
        ->assertSee('BONITO')
        ->assertSee('id="filter-ct-category"', false)
        ->assertSee('id="ct-detail-modal"', false)
        ->assertSee('1 records');
});

it('filters count tags by keyword, location, and category', function () {
    $user = createCountTagListUser('ct-list-filter', ['view-count-tag-non-fg']);

    $otherLocation = CountTagLocation::query()->create([
        'name' => 'SKIPJACK',
        'legacy_id' => 2,
    ]);

    $otherCategory = ItemCategory::query()->create([
        'name' => 'CAN',
        'code' => 'CAN-CTL',
    ]);

    NonFgCountTag::query()->create([
        'legacy_id' => 601,
        'count_tag_number' => 'CT-KEEP',
        'count_tag_date' => '2024-07-01',
        'item_code' => $this->item->code,
        'item_id' => $this->item->id,
        'item_category_id' => $this->category->id,
        'location_id' => $this->location->id,
        'location_name' => 'BONITO',
        'qty' => 1,
        'created_by_name' => 'STOREKEEPER',
    ]);

    NonFgCountTag::query()->create([
        'legacy_id' => 602,
        'count_tag_number' => 'CT-HIDE',
        'count_tag_date' => '2024-07-01',
        'item_code' => 'OTHER99',
        'item_category_id' => $otherCategory->id,
        'location_id' => $otherLocation->id,
        'location_name' => 'SKIPJACK',
        'qty' => 2,
        'created_by_name' => 'AUDITOR',
    ]);

    $this->actingAs($user)
        ->get(route('count-tags.non-fg.index', [
            'keyword' => 'CT-KEEP',
            'location_id' => $this->location->id,
        ]))
        ->assertSuccessful()
        ->assertSee('CT-KEEP')
        ->assertDontSee('CT-HIDE');

    $this->actingAs($user)
        ->get(route('count-tags.non-fg.index', [
            'category_id' => $this->category->id,
        ]))
        ->assertSuccessful()
        ->assertSee('CT-KEEP')
        ->assertDontSee('CT-HIDE');
});

it('returns count tag detail json for the modal', function () {
    $user = createCountTagListUser('ct-list-show', ['view-count-tag-non-fg']);

    $tag = NonFgCountTag::query()->create([
        'legacy_id' => 701,
        'count_tag_number' => 'CT-701',
        'count_tag_date' => '2024-08-01',
        'item_id' => $this->item->id,
        'item_code' => $this->item->code,
        'item_category_id' => $this->category->id,
        'location_id' => $this->location->id,
        'location_name' => 'BONITO',
        'section_code' => 'A',
        'row' => 2,
        'col' => 3,
        'level' => 1,
        'qty' => 9,
        'uom_code' => 'PCS',
        'tran_date' => '2024-08-31',
        'group_name' => 'STOREKEEPER',
        'created_by_name' => 'STOREKEEPER',
        'meta' => [
            'legacy' => ['Id' => 701],
            'mapping' => ['category_legacy_id' => 5],
        ],
    ]);

    $this->actingAs($user)
        ->getJson(route('count-tags.non-fg.show', $tag))
        ->assertSuccessful()
        ->assertJsonPath('count_tag_number', 'CT-701')
        ->assertJsonPath('item_code', 'CTLIST01')
        ->assertJsonPath('item_name', 'List Count Tag Item')
        ->assertJsonPath('category_name', 'PARTS')
        ->assertJsonPath('location_name', 'BONITO')
        ->assertJsonPath('section_code', 'A')
        ->assertJsonPath('qty', 9)
        ->assertJsonMissingPath('legacy_id')
        ->assertJsonMissingPath('meta')
        ->assertJsonMissingPath('item_matched')
        ->assertJsonMissingPath('location_matched');
});

it('forbids users without view permission from list and detail', function () {
    $user = createCountTagListUser('ct-list-denied');

    $tag = NonFgCountTag::query()->create([
        'legacy_id' => 801,
        'count_tag_number' => 'CT-801',
        'item_code' => 'X',
        'qty' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('count-tags.non-fg.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('count-tags.non-fg.show', $tag))
        ->assertForbidden();
});
