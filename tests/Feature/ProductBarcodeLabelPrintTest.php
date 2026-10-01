<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\QrCodeImage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7101',
        'alias' => 'PUR',
    ]);

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'Frozen Goods',
        'code' => 'FRZ',
    ]);

    $this->itemA = Item::query()->create([
        'name' => 'QR Product Alpha',
        'code' => 'QRALPHA1',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->itemB = Item::query()->create([
        'name' => 'QR Product Beta',
        'code' => 'QRBETA01',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 5,
        'is_active' => true,
    ]);

    $this->user = User::query()->create([
        'name' => 'Product QR User',
        'username' => 'product-qr-user',
        'email' => 'product-qr@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->user->assignRole('purchasing-staff');
});

it('generates png qr images with gd', function () {
    $png = QrCodeImage::png('QRALPHA1', 120);

    expect($png)->not->toBeEmpty()
        ->and(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('allows authorized users to preview product qr codes', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('product.barcode.show', $this->itemA));

    $response->assertSuccessful()
        ->assertJsonPath('id', $this->itemA->id)
        ->assertJsonPath('code', 'QRALPHA1')
        ->assertJsonPath('name', 'QR Product Alpha');

    expect($response->json('qr_svg'))->toContain('<svg');
});

it('forbids unauthorized users from previewing product qr codes', function () {
    $role = Role::findByName('purchasing-staff');
    $role->revokePermissionTo('view-products');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->refresh();

    $this->actingAs($this->user)
        ->getJson(route('product.barcode.show', $this->itemA))
        ->assertForbidden();
});

it('prints selected product qr labels as pdf', function () {
    $response = $this->actingAs($this->user)->get(route('product.barcodes.print', [
        'item_ids' => [$this->itemA->id, $this->itemB->id],
    ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('validates item ids when printing product qr labels', function () {
    $this->actingAs($this->user)
        ->get(route('product.barcodes.print'))
        ->assertSessionHasErrors(['item_ids']);

    $this->actingAs($this->user)
        ->from(route('product.index'))
        ->get(route('product.barcodes.print', [
            'item_ids' => [999999],
        ]))
        ->assertSessionHasErrors(['item_ids.0']);
});

it('forbids unauthorized users from printing product qr labels', function () {
    $role = Role::findByName('purchasing-staff');
    $role->revokePermissionTo('view-products');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->refresh();

    $this->actingAs($this->user)
        ->get(route('product.barcodes.print', [
            'item_ids' => [$this->itemA->id],
        ]))
        ->assertForbidden();
});

it('returns filtered product ids for bulk selection', function () {
    $response = $this->actingAs($this->user)->getJson(route('product.datatables', [
        'selection_scope' => 'all_ids',
        'keyword' => 'QRALPHA',
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('ids.0', $this->itemA->id);
});
