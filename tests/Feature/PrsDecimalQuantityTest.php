<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\PdfFormatters;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Information Technology',
        'code' => '7056',
        'alias' => 'IT',
    ]);

    $this->creator = User::query()->create([
        'name' => 'PRS Decimal Creator',
        'username' => 'prs-decimal-creator',
        'email' => 'prs-decimal-creator@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Kilogram',
        'code' => 'KG',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Raw Materials',
        'code' => 'RAW',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Decimal Qty Item',
        'code' => 'DEC-QTY-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);
});

it('stores fractional prs item quantities', function () {
    $response = $this->actingAs($this->creator)
        ->post(route('prs.store'), [
            'department_id' => $this->department->id,
            'date_needed' => now()->addDays(7)->toDateString(),
            'is_capex' => '0',
            'remarks' => 'Decimal quantity PRS',
            'prsItems' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => 0.5,
                ],
            ],
        ]);

    $response->assertRedirect(route('prs.index'));
    $response->assertSessionHas('success');

    $prsItem = PrsItem::query()->where('item_id', $this->item->id)->first();

    expect($prsItem)->not->toBeNull()
        ->and((float) $prsItem->quantity)->toBe(0.5)
        ->and((new PrsItem)->getCasts())->toHaveKey('quantity', 'decimal:5');
});

it('updates prs item quantities with five decimal precision', function () {
    $prs = Prs::query()->create([
        'prs_number' => 'PRS-IT-2026-DEC01',
        'user_id' => $this->creator->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Editable decimal PRS',
        'status' => 'REQUESTED',
    ]);

    PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $this->item->id,
        'quantity' => 2,
    ]);

    $response = $this->actingAs($this->creator)
        ->from(route('prs.edit', $prs))
        ->put(route('prs.update', $prs), [
            'department_id' => $this->department->id,
            'date_needed' => now()->addDays(7)->toDateString(),
            'is_capex' => '0',
            'remarks' => 'Updated decimal quantity',
            'prsItems' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => 1.25,
                ],
            ],
        ]);

    $response->assertRedirect(route('prs.edit', $prs));
    $response->assertSessionHas('success');

    $updatedItem = PrsItem::query()
        ->where('prs_id', $prs->id)
        ->where('item_id', $this->item->id)
        ->first();

    expect($updatedItem)->not->toBeNull()
        ->and((float) $updatedItem->quantity)->toBe(1.25);
});

it('rejects prs quantities below the minimum decimal threshold', function () {
    $response = $this->actingAs($this->creator)
        ->from(route('prs.create'))
        ->post(route('prs.store'), [
            'department_id' => $this->department->id,
            'date_needed' => now()->addDays(7)->toDateString(),
            'is_capex' => '0',
            'remarks' => 'Invalid quantity',
            'prsItems' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => 0,
                ],
            ],
        ]);

    $response->assertRedirect(route('prs.create'));
    $response->assertSessionHasErrors('prsItems.0.quantity');
});

it('formats quantities with smart decimals for display', function () {
    expect(PdfFormatters::qty(2))->toBe('2')
        ->and(PdfFormatters::qty(1.25))->toBe('1,25')
        ->and(PdfFormatters::qty(0.5))->toBe('0,5');
});
