<?php

use App\Models\CountTagLocation;
use App\Models\CountTagSection;
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
        'code' => '7042-CTS',
        'alias' => 'IM-CTS',
    ]);

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-CTS',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'PARTS',
        'code' => 'PARTS-CTS',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Store Count Tag Item',
        'code' => 'CTSTORE1',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Spare Part',
        'stock_on_hand' => 8,
        'is_active' => true,
    ]);

    $this->location = CountTagLocation::query()->create([
        'name' => 'BONITO',
        'legacy_id' => 101,
    ]);

    $this->section = CountTagSection::query()->create([
        'location_id' => $this->location->id,
        'code' => 'A',
        'max_row' => 5,
        'max_column' => 8,
        'legacy_id' => 201,
    ]);
});

function createCountTagStoreUser(string $username, array $permissions = []): User
{
    $user = User::query()->create([
        'name' => "Count Tag Store {$username}",
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

it('stores a count tag from scan with null number and month-end period', function () {
    $user = createCountTagStoreUser('ct-store-ok', [
        'view-count-tag-non-fg',
        'create-count-tag-non-fg',
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('count-tags.non-fg.store'), [
            'item_id' => $this->item->id,
            'location_id' => $this->location->id,
            'section_id' => $this->section->id,
            'qty' => 12.5,
            'row' => 2,
            'col' => 3,
            'level' => 1,
            'condition' => 'Good',
            'size' => 'M',
            'count_tag_date' => now()->format('Y-m'),
        ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Count tag saved.')
        ->assertJsonPath('data.item_code', 'CTSTORE1')
        ->assertJsonPath('data.qty', 12.5)
        ->assertJsonPath('data.count_tag_number', null);

    $tag = NonFgCountTag::query()->first();
    expect($tag)->not->toBeNull();
    expect($tag->count_tag_number)->toBeNull();
    expect($tag->tran_date?->toDateString())->toBe(now()->toDateString());
    expect($tag->count_tag_date?->toDateString())->toBe(now()->endOfMonth()->toDateString());
    expect($tag->item_id)->toBe($this->item->id);
    expect($tag->item_category_id)->toBe($this->category->id);
    expect($tag->location_id)->toBe($this->location->id);
    expect($tag->section_id)->toBe($this->section->id);
    expect($tag->created_by)->toBe($user->id);
    expect($tag->created_by_name)->toBe($user->name);
    expect($tag->condition?->value)->toBe('Good');
    expect($tag->meta['source'])->toBe('scan');
});

it('validates required fields and section bounds', function () {
    $user = createCountTagStoreUser('ct-store-val', [
        'view-count-tag-non-fg',
        'create-count-tag-non-fg',
    ]);

    $this->actingAs($user)
        ->postJson(route('count-tags.non-fg.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['item_id', 'location_id', 'qty', 'count_tag_date'])
        ->assertJsonMissingValidationErrors(['section_id']);

    $this->actingAs($user)
        ->postJson(route('count-tags.non-fg.store'), [
            'item_id' => $this->item->id,
            'location_id' => $this->location->id,
            'section_id' => $this->section->id,
            'qty' => 1,
            'row' => 99,
            'col' => 99,
            'count_tag_date' => now()->format('Y-m'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['row', 'col']);
});

it('allows storing a count tag without a section', function () {
    $user = createCountTagStoreUser('ct-store-no-sec', [
        'view-count-tag-non-fg',
        'create-count-tag-non-fg',
    ]);

    $this->actingAs($user)
        ->postJson(route('count-tags.non-fg.store'), [
            'item_id' => $this->item->id,
            'location_id' => $this->location->id,
            'section_id' => null,
            'qty' => 4,
            'count_tag_date' => now()->format('Y-m'),
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Count tag saved.');

    $tag = NonFgCountTag::query()->first();
    expect($tag)->not->toBeNull();
    expect($tag->section_id)->toBeNull();
    expect($tag->section_code)->toBeNull();
    expect($tag->location_id)->toBe($this->location->id);
});

it('rejects section from a different location', function () {
    $user = createCountTagStoreUser('ct-store-sec', [
        'view-count-tag-non-fg',
        'create-count-tag-non-fg',
    ]);

    $otherLocation = CountTagLocation::query()->create([
        'name' => 'SKIPJACK',
        'legacy_id' => 102,
    ]);

    $otherSection = CountTagSection::query()->create([
        'location_id' => $otherLocation->id,
        'code' => 'B',
        'max_row' => 3,
        'max_column' => 3,
        'legacy_id' => 202,
    ]);

    $this->actingAs($user)
        ->postJson(route('count-tags.non-fg.store'), [
            'item_id' => $this->item->id,
            'location_id' => $this->location->id,
            'section_id' => $otherSection->id,
            'qty' => 1,
            'count_tag_date' => now()->format('Y-m'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['section_id']);
});

it('lists locations and sections for the scan form', function () {
    $user = createCountTagStoreUser('ct-store-loc', [
        'view-count-tag-non-fg',
        'create-count-tag-non-fg',
    ]);

    $this->actingAs($user)
        ->getJson(route('count-tags.non-fg.locations'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'BONITO');

    $this->actingAs($user)
        ->getJson(route('count-tags.non-fg.sections', $this->location))
        ->assertSuccessful()
        ->assertJsonPath('data.0.code', 'A')
        ->assertJsonPath('data.0.max_row', 5)
        ->assertJsonPath('data.0.max_column', 8);
});

it('forbids store without create permission', function () {
    $user = createCountTagStoreUser('ct-store-denied', ['view-count-tag-non-fg']);

    $this->actingAs($user)
        ->postJson(route('count-tags.non-fg.store'), [
            'item_id' => $this->item->id,
            'location_id' => $this->location->id,
            'section_id' => $this->section->id,
            'qty' => 1,
            'count_tag_date' => now()->format('Y-m'),
        ])
        ->assertForbidden();
});

it('renders scan page with store form fields', function () {
    $user = createCountTagStoreUser('ct-store-scan', [
        'view-count-tag-non-fg',
        'create-count-tag-non-fg',
    ]);

    $this->actingAs($user)
        ->get(route('count-tags.non-fg.scan'))
        ->assertSuccessful()
        ->assertSee('id="count-tag-entry-form"', false)
        ->assertSee('modal-fullscreen-sm-down', false)
        ->assertSee('id="count-tag-mode-banner"', false)
        ->assertSee('id="count-tag-qty"', false)
        ->assertSee('id="count-tag-location"', false)
        ->assertSee('col-12 col-sm-4', false)
        ->assertSee('<select id="count-tag-row"', false)
        ->assertSee('<select id="count-tag-col"', false)
        ->assertSee('id="count-tag-period-unlock"', false)
        ->assertSee('Save &amp; scan next', false);
});
