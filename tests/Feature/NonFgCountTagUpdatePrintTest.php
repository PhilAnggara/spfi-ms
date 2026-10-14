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
        'code' => '7042-CTU',
        'alias' => 'IM-CTU',
    ]);

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-CTU',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'PARTS',
        'code' => 'PARTS-CTU',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Update Print Count Tag Item',
        'code' => 'CTUPD01',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Spare Part',
        'stock_on_hand' => 8,
        'is_active' => true,
    ]);

    $this->location = CountTagLocation::query()->create([
        'name' => 'BONITO',
        'legacy_id' => 301,
    ]);

    $this->section = CountTagSection::query()->create([
        'location_id' => $this->location->id,
        'code' => 'A',
        'max_row' => 5,
        'max_column' => 8,
        'legacy_id' => 401,
    ]);
});

function createCountTagUpdateUser(string $username, array $permissions = []): User
{
    $user = User::query()->create([
        'name' => "Count Tag Update {$username}",
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

function createCountTagForUpdateTests(array $overrides = []): NonFgCountTag
{
    return NonFgCountTag::query()->create(array_merge([
        'count_tag_number' => null,
        'count_tag_date' => '2026-10-31',
        'item_id' => test()->item->id,
        'item_code' => test()->item->code,
        'item_category_id' => test()->category->id,
        'unit_of_measure_id' => test()->unit->id,
        'uom_code' => 'PCS',
        'location_id' => test()->location->id,
        'location_name' => 'BONITO',
        'section_id' => test()->section->id,
        'section_code' => 'A',
        'row' => 1,
        'col' => 2,
        'level' => 1,
        'condition' => 'Good',
        'size' => 'M',
        'qty' => 5,
        'tran_date' => '2026-10-09',
        'created_by_name' => 'STOREKEEPER',
    ], $overrides));
}

it('stores a count tag with null number, auto tran_date, and month-end count_tag_date', function () {
    $user = createCountTagUpdateUser('ct-upd-store', [
        'view-count-tag-non-fg',
        'create-count-tag-non-fg',
    ]);

    $periodMonth = now()->format('Y-m');

    $this->actingAs($user)
        ->postJson(route('count-tags.non-fg.store'), [
            'item_id' => $this->item->id,
            'location_id' => $this->location->id,
            'section_id' => $this->section->id,
            'qty' => 3,
            'count_tag_date' => $periodMonth,
        ])
        ->assertCreated()
        ->assertJsonPath('data.count_tag_number', null);

    $tag = NonFgCountTag::query()->first();
    expect($tag)->not->toBeNull();
    expect($tag->count_tag_number)->toBeNull();
    expect($tag->tran_date?->toDateString())->toBe(now()->toDateString());
    expect($tag->count_tag_date?->toDateString())->toBe(now()->endOfMonth()->toDateString());
});

it('rejects duplicate create for the same item and count_tag_date month', function () {
    $user = createCountTagUpdateUser('ct-upd-dup', [
        'view-count-tag-non-fg',
        'create-count-tag-non-fg',
    ]);

    createCountTagForUpdateTests([
        'count_tag_date' => '2026-10-31',
    ]);

    $this->actingAs($user)
        ->postJson(route('count-tags.non-fg.store'), [
            'item_id' => $this->item->id,
            'location_id' => $this->location->id,
            'section_id' => $this->section->id,
            'qty' => 2,
            'count_tag_date' => '2026-10',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['item_id']);
});

it('rejects future period month on store', function () {
    $user = createCountTagUpdateUser('ct-upd-future', [
        'view-count-tag-non-fg',
        'create-count-tag-non-fg',
    ]);

    $this->actingAs($user)
        ->postJson(route('count-tags.non-fg.store'), [
            'item_id' => $this->item->id,
            'location_id' => $this->location->id,
            'section_id' => $this->section->id,
            'qty' => 2,
            'count_tag_date' => now()->addMonthNoOverflow()->format('Y-m'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['count_tag_date']);
});

it('returns existing and defaults from lookup for the selected period', function () {
    $user = createCountTagUpdateUser('ct-upd-lookup', [
        'create-count-tag-non-fg',
    ]);

    $existing = createCountTagForUpdateTests([
        'count_tag_date' => '2026-10-31',
        'row' => 4,
        'col' => 5,
        'qty' => 11,
    ]);

    createCountTagForUpdateTests([
        'count_tag_date' => '2026-09-30',
        'row' => 9,
        'col' => 9,
        'qty' => 99,
    ]);

    $this->actingAs($user)
        ->getJson(route('count-tags.non-fg.lookup', [
            'code' => $this->item->code,
            'count_tag_date' => '2026-10',
        ]))
        ->assertSuccessful()
        ->assertJsonPath('existing.id', $existing->id)
        ->assertJsonPath('existing.row', 4)
        ->assertJsonPath('defaults.id', $existing->id)
        ->assertJsonPath('defaults.row', 4);

    $this->actingAs($user)
        ->getJson(route('count-tags.non-fg.lookup', [
            'code' => $this->item->code,
            'count_tag_date' => '2026-08',
        ]))
        ->assertSuccessful()
        ->assertJsonPath('existing', null)
        ->assertJsonPath('defaults.row', 4);
});

it('updates a count tag period without changing tran_date', function () {
    $user = createCountTagUpdateUser('ct-upd-edit', [
        'view-count-tag-non-fg',
        'update-count-tag-non-fg',
    ]);

    $tag = createCountTagForUpdateTests([
        'count_tag_date' => '2026-09-30',
        'qty' => 5,
        'tran_date' => '2026-09-01',
    ]);

    $this->actingAs($user)
        ->putJson(route('count-tags.non-fg.update', $tag), [
            'location_id' => $this->location->id,
            'section_id' => $this->section->id,
            'qty' => 8.5,
            'row' => 3,
            'col' => 4,
            'level' => 2,
            'condition' => 'Damaged',
            'size' => 'L',
            'count_tag_date' => '2026-10',
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Count tag updated.')
        ->assertJsonPath('data.qty', 8.5);

    $tag->refresh();
    expect($tag->qty)->toBe('8.50000');
    expect($tag->count_tag_date?->toDateString())->toBe('2026-10-31');
    expect($tag->tran_date?->toDateString())->toBe('2026-09-01');
    expect($tag->condition?->value)->toBe('Damaged');
});

it('allows clearing section on update', function () {
    $user = createCountTagUpdateUser('ct-upd-clear-sec', [
        'update-count-tag-non-fg',
    ]);

    $tag = createCountTagForUpdateTests([
        'count_tag_date' => '2026-09-30',
        'qty' => 5,
    ]);

    expect($tag->section_id)->not->toBeNull();

    $this->actingAs($user)
        ->putJson(route('count-tags.non-fg.update', $tag), [
            'location_id' => $this->location->id,
            'section_id' => null,
            'qty' => 5,
            'count_tag_date' => '2026-09',
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Count tag updated.');

    $tag->refresh();
    expect($tag->section_id)->toBeNull();
    expect($tag->section_code)->toBeNull();
});

it('rejects update that collides with another tag in the target period', function () {
    $user = createCountTagUpdateUser('ct-upd-collide', [
        'update-count-tag-non-fg',
    ]);

    $tag = createCountTagForUpdateTests([
        'count_tag_date' => '2026-09-30',
    ]);

    createCountTagForUpdateTests([
        'count_tag_date' => '2026-10-31',
    ]);

    $this->actingAs($user)
        ->putJson(route('count-tags.non-fg.update', $tag), [
            'location_id' => $this->location->id,
            'section_id' => $this->section->id,
            'qty' => 2,
            'count_tag_date' => '2026-10',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['count_tag_date']);
});

it('soft deletes a count tag with delete permission', function () {
    $user = createCountTagUpdateUser('ct-upd-del', [
        'view-count-tag-non-fg',
        'delete-count-tag-non-fg',
    ]);

    $tag = createCountTagForUpdateTests();

    $this->actingAs($user)
        ->deleteJson(route('count-tags.non-fg.destroy', $tag))
        ->assertSuccessful()
        ->assertJsonPath('message', 'Count tag deleted.');

    expect(NonFgCountTag::query()->find($tag->id))->toBeNull();
    expect(NonFgCountTag::withTrashed()->find($tag->id))->not->toBeNull();
});

it('forbids update and delete without permissions', function () {
    $user = createCountTagUpdateUser('ct-upd-denied', [
        'view-count-tag-non-fg',
    ]);

    $tag = createCountTagForUpdateTests();

    $this->actingAs($user)
        ->putJson(route('count-tags.non-fg.update', $tag), [
            'location_id' => $this->location->id,
            'section_id' => $this->section->id,
            'qty' => 2,
            'count_tag_date' => now()->format('Y-m'),
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('count-tags.non-fg.destroy', $tag))
        ->assertForbidden();
});

it('assigns a count tag number on print and returns a pdf', function () {
    $user = createCountTagUpdateUser('ct-upd-print', [
        'view-count-tag-non-fg',
    ]);

    $tag = createCountTagForUpdateTests([
        'count_tag_number' => null,
    ]);

    $response = $this->actingAs($user)
        ->post(route('count-tags.non-fg.print', $tag), [
            'count_tag_number' => 'CT-PAPER-001',
            'count_tag_number_suggested' => 'CT000001',
            'print_confirm_id' => $tag->id,
        ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($tag->fresh()->count_tag_number)->toBe('CT-PAPER-001');
});

it('shows print confirm controls and period column on the list page', function () {
    $user = createCountTagUpdateUser('ct-upd-list', [
        'view-count-tag-non-fg',
        'update-count-tag-non-fg',
        'delete-count-tag-non-fg',
    ]);

    $tag = createCountTagForUpdateTests([
        'count_tag_number' => null,
        'count_tag_date' => '2026-10-31',
    ]);

    $this->actingAs($user)
        ->get(route('count-tags.non-fg.index'))
        ->assertSuccessful()
        ->assertSee('2026-10')
        ->assertSee('Period From')
        ->assertSee('btn-group btn-group-sm', false)
        ->assertSee('fa-light fa-eye text-primary', false)
        ->assertSee('fa-light fa-print text-primary', false)
        ->assertSee('data-bs-target="#ctPrintConfirm-'.$tag->id.'"', false)
        ->assertSee('id="ctPrintConfirm-'.$tag->id.'"', false)
        ->assertSee('ct-edit-btn', false)
        ->assertSee('ct-delete-btn', false)
        ->assertSee('type="month"', false)
        ->assertSee('name="count_tag_date"', false)
        ->assertSee('id="ct-edit-row"', false)
        ->assertSee('<select id="ct-edit-row"', false)
        ->assertSee('<select id="ct-edit-col"', false)
        ->assertSee('modal-fullscreen-sm-down', false)
        ->assertSee('col-12 col-sm-4', false);
});

it('filters the list by count_tag_date period month', function () {
    $user = createCountTagUpdateUser('ct-upd-filter', [
        'view-count-tag-non-fg',
    ]);

    createCountTagForUpdateTests([
        'count_tag_number' => 'CT-KEEP-PERIOD',
        'count_tag_date' => '2026-10-31',
        'tran_date' => '2026-01-01',
    ]);

    createCountTagForUpdateTests([
        'count_tag_number' => 'CT-HIDE-PERIOD',
        'count_tag_date' => '2026-09-30',
        'tran_date' => '2026-10-09',
    ]);

    $this->actingAs($user)
        ->get(route('count-tags.non-fg.index', [
            'date_start' => '2026-10',
            'date_end' => '2026-10',
        ]))
        ->assertSuccessful()
        ->assertSee('CT-KEEP-PERIOD')
        ->assertDontSee('CT-HIDE-PERIOD');
});

it('renders scan form with month period input', function () {
    $user = createCountTagUpdateUser('ct-upd-scan', [
        'create-count-tag-non-fg',
    ]);

    $this->actingAs($user)
        ->get(route('count-tags.non-fg.scan'))
        ->assertSuccessful()
        ->assertSee('id="count-tag-period"', false)
        ->assertSee('name="count_tag_date"', false)
        ->assertSee('type="month"', false)
        ->assertDontSee('name="tran_date"', false)
        ->assertSee('modal-fullscreen-sm-down', false)
        ->assertSee('col-12 col-sm-4', false)
        ->assertSee('<select id="count-tag-row"', false)
        ->assertSee('<select id="count-tag-col"', false)
        ->assertSee('id="count-tag-level"', false)
        ->assertSee('data-can-update="0"', false);
});
