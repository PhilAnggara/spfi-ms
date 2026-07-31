<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Production',
        'code' => '7037',
        'alias' => 'PROD',
    ]);

    $this->regularUser = User::query()->create([
        'name' => 'Regular Staff',
        'username' => 'regular.staff',
        'email' => 'regular.staff@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->otherUser = User::query()->create([
        'name' => 'Other Staff',
        'username' => 'other.staff',
        'email' => 'other.staff@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->imStaff = User::query()->create([
        'name' => 'IM Staff',
        'username' => 'im.staff',
        'email' => 'im.staff@example.test',
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
        'name' => 'Supplies',
        'code' => 'SUP',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Withdrawal Item',
        'code' => 'WDI-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'stock_on_hand' => 50,
        'is_active' => true,
    ]);
});

function createStoreWithdrawalForUser(User $user, Department $department, Item $item, string $suffix = '001'): int
{
    $now = now();

    $storeWithdrawalId = DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-'.$suffix,
        'sws_date' => $now->toDateString(),
        'department_id' => $department->id,
        'department_code' => $department->code,
        'type' => 'normal',
        'info' => 'Test withdrawal',
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('store_withdrawal_items')->insert([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $item->id,
        'product_code' => $item->code,
        'quantity' => 2,
        'stock_on_hand_snapshot' => $item->stock_on_hand,
        'uom' => 'PCS',
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $storeWithdrawalId;
}

it('allows regular staff to access stores withdrawal index', function () {
    $response = $this->actingAs($this->regularUser)
        ->get(route('stores-withdrawals.index'));

    $response->assertSuccessful();
});

it('allows regular staff to create a normal stores withdrawal', function () {
    $response = $this->actingAs($this->regularUser)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'info' => 'Regular staff withdrawal',
        'items' => [
            [
                'item_id' => $this->item->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertRedirect(route('stores-withdrawals.index'));

    expect(DB::table('store_withdrawals')->where('created_by', $this->regularUser->id)->count())->toBe(1);
});

it('shows only own stores withdrawals for regular staff', function () {
    createStoreWithdrawalForUser($this->regularUser, $this->department, $this->item, 'OWN-001');
    createStoreWithdrawalForUser($this->otherUser, $this->department, $this->item, 'OTH-001');

    $response = $this->actingAs($this->regularUser)
        ->get(route('stores-withdrawals.index'));

    $response->assertSuccessful();
    $response->assertSee('SWS-OWN-001');
    $response->assertDontSee('SWS-OTH-001');
});

it('forbids regular staff from updating another users stores withdrawal', function () {
    $otherWithdrawalId = createStoreWithdrawalForUser($this->otherUser, $this->department, $this->item, 'OTH-002');

    $response = $this->actingAs($this->regularUser)->put(route('stores-withdrawals.update', $otherWithdrawalId), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'items' => [
            [
                'item_id' => $this->item->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertForbidden();
});

it('forbids regular staff from deleting another users stores withdrawal', function () {
    $otherWithdrawalId = createStoreWithdrawalForUser($this->otherUser, $this->department, $this->item, 'OTH-003');

    $response = $this->actingAs($this->regularUser)->delete(route('stores-withdrawals.destroy', $otherWithdrawalId));

    $response->assertForbidden();
});

it('allows im staff to see all stores withdrawals', function () {
    createStoreWithdrawalForUser($this->regularUser, $this->department, $this->item, 'OWN-002');
    createStoreWithdrawalForUser($this->otherUser, $this->department, $this->item, 'OTH-004');

    $response = $this->actingAs($this->imStaff)
        ->get(route('stores-withdrawals.index'));

    $response->assertSuccessful();
    $response->assertSee('SWS-OWN-002');
    $response->assertSee('SWS-OTH-004');
});

it('allows im staff to update another users stores withdrawal', function () {
    $otherWithdrawalId = createStoreWithdrawalForUser($this->otherUser, $this->department, $this->item, 'OTH-005');

    $response = $this->actingAs($this->imStaff)->put(route('stores-withdrawals.update', $otherWithdrawalId), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'info' => 'Updated by IM',
        'items' => [
            [
                'item_id' => $this->item->id,
                'quantity' => 1.25,
            ],
        ],
    ]);

    $response->assertRedirect(route('stores-withdrawals.index'));
    $response->assertSessionHasNoErrors();

    $activeQty = (float) DB::table('store_withdrawal_items')
        ->where('store_withdrawal_id', $otherWithdrawalId)
        ->whereNull('deleted_at')
        ->value('quantity');

    expect($activeQty)->toBe(1.25);
});
