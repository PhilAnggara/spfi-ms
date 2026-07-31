<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\DocumentNumberService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7100',
        'alias' => 'IM',
    ]);

    $this->user = User::query()->create([
        'name' => 'DR Duplicate User',
        'username' => 'dr-dup-user',
        'email' => 'dr-dup-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('administrator');

    $this->supplier = Supplier::query()->create([
        'name' => 'DR Conflict Supplier',
        'code' => 'SUP-DR-DUP',
        'created_by' => $this->user->id,
    ]);

    $now = now();
    DB::table('deliveries')->insert([
        'dr_number' => 'DR-TAKEN-999',
        'dr_date' => $now->toDateString(),
        'from_name' => 'IM - PT. SPFI',
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

it('rejects a duplicate dr number with supplier context', function () {
    expect(fn () => app(DocumentNumberService::class)->assertUnique('DR', 'DR-TAKEN-999'))
        ->toThrow(ValidationException::class);

    try {
        app(DocumentNumberService::class)->assertUnique('DR', 'DR-TAKEN-999');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('dr_number');
        expect($exception->errors()['dr_number'][0])
            ->toBe('The DR Number DR-TAKEN-999 has already been used by supplier DR Conflict Supplier.');
    }
});

it('shows duplicate dr number feedback on the create page', function () {
    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);
    $category = ItemCategory::query()->create([
        'name' => 'Consumables',
        'code' => 'CNS',
    ]);
    $item = Item::query()->create([
        'name' => 'Delivery Item',
        'code' => 'DR-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 50,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('deliveries.create'))
        ->post(route('deliveries.store'), [
            'dr_number' => 'DR-TAKEN-999',
            'dr_number_suggested' => 'DR-NEXT',
            'dr_date' => now()->toDateString(),
            'from_name' => 'IM - PT. SPFI',
            'from_location' => 'Warehouse',
            'supplier_id' => $this->supplier->id,
            'to_location' => 'Customer',
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
        ]);

    $response->assertRedirect(route('deliveries.create'));
    $response->assertSessionHasErrors([
        'dr_number' => 'The DR Number DR-TAKEN-999 has already been used by supplier DR Conflict Supplier.',
    ]);

    $followUp = $this->actingAs($this->user)
        ->from(route('deliveries.create'))
        ->followingRedirects()
        ->post(route('deliveries.store'), [
            'dr_number' => 'DR-TAKEN-999',
            'dr_number_suggested' => 'DR-NEXT',
            'dr_date' => now()->toDateString(),
            'from_name' => 'IM - PT. SPFI',
            'from_location' => 'Warehouse',
            'supplier_id' => $this->supplier->id,
            'to_location' => 'Customer',
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
        ]);

    $followUp->assertSuccessful();
    $followUp->assertSee('is-invalid', false);
    $followUp->assertSee('The DR Number DR-TAKEN-999 has already been used by supplier DR Conflict Supplier.');
    $followUp->assertSee('icon: \'error\'', false);
    $followUp->assertSee('scrollIntoView', false);
});
