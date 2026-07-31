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
        'name' => 'Inventory',
        'code' => '7100',
        'alias' => 'INV',
    ]);

    $this->user = User::query()->create([
        'name' => 'TS Print User',
        'username' => 'ts-print-user',
        'email' => 'ts-print-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Consumables',
        'code' => 'CNS',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Transfer Print Item',
        'code' => 'TS-PRINT-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 20,
        'is_active' => true,
    ]);

    $now = now();

    $this->storeWithdrawalId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-TS-PRINT-001',
        'sws_date' => $now->toDateString(),
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'regular',
        'info' => 'Print test SWS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->storeWithdrawalItemId = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $this->storeWithdrawalId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 5,
        'uom' => 'PCS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->transferSlipId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-PRINT-001',
        'ts_date' => $now->toDateString(),
        'store_withdrawal_id' => $this->storeWithdrawalId,
        'for_production' => false,
        'remarks' => 'Print test transfer slip',
        'transfer_to' => 'Production Floor',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $this->transferSlipId,
        'store_withdrawal_item_id' => $this->storeWithdrawalItemId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 3,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

it('shows print confirm modal and preview link on the transfer slips index', function () {
    $response = $this->actingAs($this->user)
        ->get(route('transfer-slips.index'));

    $response->assertSuccessful();
    $response->assertSee('data-bs-target="#tsPrintConfirm-'.$this->transferSlipId.'"', false);
    $response->assertSee('id="tsPrintConfirm-'.$this->transferSlipId.'"', false);
    $response->assertSee('Confirm TS Number');
    $response->assertSee(config('transfer-slip.paper.label'));
    $response->assertSee('Actual size / 100%');
    $response->assertSee(route('transfer-slips.print', ['transferSlip' => $this->transferSlipId, 'mode' => 'preview']), false);
    $response->assertDontSee(
        'href="'.route('transfer-slips.print', ['transferSlip' => $this->transferSlipId, 'mode' => 'print']).'"',
        false
    );
});

it('saves an edited ts number when printing from the confirmation modal', function () {
    $response = $this->actingAs($this->user)
        ->post(route('transfer-slips.print', ['transferSlip' => $this->transferSlipId, 'mode' => 'print']), [
            'ts_number' => 'TS-PAPER-777',
            'ts_number_suggested' => 'TS-SUGGESTED',
            'print_confirm_id' => $this->transferSlipId,
        ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->getContent())->toContain('/PrintScaling /None');

    $tsNumber = DB::table('transfer_slips')->where('id', $this->transferSlipId)->value('ts_number');
    expect($tsNumber)->toBe('TS-PAPER-777');
});

it('rejects a duplicate ts number when printing and shows sws feedback', function () {
    $now = now();

    $otherStoreWithdrawalId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-TS-DUP-001',
        'sws_date' => $now->toDateString(),
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'regular',
        'info' => 'Duplicate SWS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('transfer_slips')->insert([
        'ts_number' => 'TS-TAKEN-999',
        'ts_date' => $now->toDateString(),
        'store_withdrawal_id' => $otherStoreWithdrawalId,
        'for_production' => false,
        'remarks' => 'Taken TS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->post(route('transfer-slips.print', ['transferSlip' => $this->transferSlipId, 'mode' => 'print']), [
            'ts_number' => 'TS-TAKEN-999',
            'ts_number_suggested' => 'TS-SUGGESTED',
            'print_confirm_id' => $this->transferSlipId,
        ]);

    $response->assertRedirect(route('transfer-slips.index'));
    $response->assertSessionHasErrors([
        'ts_number' => 'The TS Number TS-TAKEN-999 has already been used by SWS SWS-TS-DUP-001.',
    ]);
    expect(DB::table('transfer_slips')->where('id', $this->transferSlipId)->value('ts_number'))->toBe('TS-PRINT-001');

    $followUp = $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->followingRedirects()
        ->post(route('transfer-slips.print', ['transferSlip' => $this->transferSlipId, 'mode' => 'print']), [
            'ts_number' => 'TS-TAKEN-999',
            'ts_number_suggested' => 'TS-SUGGESTED',
            'print_confirm_id' => $this->transferSlipId,
        ]);

    $followUp->assertSuccessful();
    $followUp->assertSee('data-auto-show="1"', false);
    $followUp->assertSee('The TS Number TS-TAKEN-999 has already been used by SWS SWS-TS-DUP-001.');
    $followUp->assertDontSee('icon: \'error\'', false);
    $followUp->assertSee('is-invalid', false);
    $followUp->assertDontSee('Transfer slip could not be saved.');
});

it('omits blank form background and ts number in print mode', function () {
    $transferSlip = DB::table('transfer_slips as ts')
        ->leftJoin('store_withdrawals as sw', 'sw.id', '=', 'ts.store_withdrawal_id')
        ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
        ->leftJoin('users as creator', 'creator.id', '=', 'ts.created_by')
        ->where('ts.id', $this->transferSlipId)
        ->select([
            'ts.*',
            'sw.sws_number',
            'sw.department_code',
            'd.name as department_name',
            'creator.name as created_by_name',
        ])
        ->first();

    $items = DB::table('transfer_slip_items as tsi')
        ->leftJoin('items as i', 'i.id', '=', 'tsi.item_id')
        ->where('tsi.transfer_slip_id', $this->transferSlipId)
        ->select([
            'tsi.*',
            'i.name as item_name',
            'i.code as item_code',
            'i.type as item_type',
        ])
        ->get();

    $html = view('pdf.transfer-slip', [
        'transferSlip' => $transferSlip,
        'items' => $items,
        'isPreview' => false,
        'backgroundImageSrc' => null,
        'backgroundWidthPt' => 215 * 2.834645669,
        'backgroundHeightPt' => 105 * 2.834645669,
        'pageWidthMm' => 215,
        'pageHeightMm' => 105,
    ])->render();

    expect($html)
        ->not->toContain('class="ts-bg"')
        ->not->toContain('class="field ts-number"')
        ->toContain('Transfer Print Item');
});

it('renders transfer slip pdf preview with blank form background', function () {
    $response = $this->actingAs($this->user)
        ->get(route('transfer-slips.print', [
            'transferSlip' => $this->transferSlipId,
            'mode' => 'preview',
        ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('renders transfer slip pdf print without requiring blank form background', function () {
    $response = $this->actingAs($this->user)
        ->get(route('transfer-slips.print', [
            'transferSlip' => $this->transferSlipId,
            'mode' => 'print',
        ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('uses 215mm by 105mm page dimensions in transfer slip view', function () {
    $transferSlip = DB::table('transfer_slips as ts')
        ->leftJoin('store_withdrawals as sw', 'sw.id', '=', 'ts.store_withdrawal_id')
        ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
        ->leftJoin('users as creator', 'creator.id', '=', 'ts.created_by')
        ->where('ts.id', $this->transferSlipId)
        ->select([
            'ts.*',
            'sw.sws_number',
            'sw.department_code',
            'd.name as department_name',
            'creator.name as created_by_name',
        ])
        ->first();

    $items = DB::table('transfer_slip_items as tsi')
        ->leftJoin('items as i', 'i.id', '=', 'tsi.item_id')
        ->where('tsi.transfer_slip_id', $this->transferSlipId)
        ->select([
            'tsi.*',
            'i.name as item_name',
            'i.code as item_code',
            'i.type as item_type',
        ])
        ->get();

    $html = view('pdf.transfer-slip', [
        'transferSlip' => $transferSlip,
        'items' => $items,
        'isPreview' => true,
        'backgroundImageSrc' => 'data:image/jpeg;base64,/9j/4AAQ',
        'backgroundWidthPt' => 215 * 2.834645669,
        'backgroundHeightPt' => 105 * 2.834645669,
        'pageWidthMm' => 215,
        'pageHeightMm' => 105,
    ])->render();

    expect($html)
        ->toContain('size: 215mm 105mm')
        ->toContain('width: 215mm')
        ->toContain('height: 105mm')
        ->toContain('class="ts-bg"')
        ->toContain('data:image/jpeg;base64,')
        ->toContain('left: 25mm; top: 25.2mm')
        ->toContain('class="remarks"')
        ->toContain('Inventory Management')
        ->toContain('Production Floor')
        ->toContain('Transfer Print Item');
});

it('embeds blank form background only in preview mode', function () {
    $preview = $this->actingAs($this->user)
        ->get(route('transfer-slips.print', [
            'transferSlip' => $this->transferSlipId,
            'mode' => 'preview',
        ]));

    $print = $this->actingAs($this->user)
        ->get(route('transfer-slips.print', [
            'transferSlip' => $this->transferSlipId,
            'mode' => 'print',
        ]));

    $preview->assertSuccessful();
    $print->assertSuccessful();

    expect(strlen((string) $preview->getContent()))
        ->toBeGreaterThan(strlen((string) $print->getContent()));
});

it('returns not found for a deleted transfer slip', function () {
    DB::table('transfer_slips')
        ->where('id', $this->transferSlipId)
        ->update(['deleted_at' => now()]);

    $response = $this->actingAs($this->user)
        ->get(route('transfer-slips.print', $this->transferSlipId));

    $response->assertNotFound();
});
