<?php

use App\Models\Currency;
use App\Models\Department;
use App\Models\PurchaseOrder;
use App\Models\Session;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserActivityLog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $itDepartment = Department::query()->create([
        'name' => 'Information Technology',
        'code' => '7056',
        'alias' => 'IT',
    ]);

    $purchasingDepartment = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7001',
        'alias' => 'PUR',
    ]);

    $this->admin = User::query()->create([
        'name' => 'Admin Active Sessions',
        'username' => 'admin-sessions',
        'email' => 'admin-sessions@example.test',
        'password' => Hash::make('password'),
        'department_id' => $itDepartment->id,
        'role' => 'Manager',
    ]);
    $this->admin->assignRole('administrator');

    $this->itStaff = User::query()->create([
        'name' => 'IT Staff Active Sessions',
        'username' => 'it-staff-sessions',
        'email' => 'it-staff-sessions@example.test',
        'password' => Hash::make('password'),
        'department_id' => $itDepartment->id,
        'role' => 'Staff',
    ]);
    $this->itStaff->assignRole('it-staff');

    $this->purchasingStaff = User::query()->create([
        'name' => 'Purchasing Staff Sessions',
        'username' => 'purchasing-sessions',
        'email' => 'purchasing-sessions@example.test',
        'password' => Hash::make('password'),
        'department_id' => $purchasingDepartment->id,
        'role' => 'Staff',
    ]);
    $this->purchasingStaff->assignRole('purchasing-staff');

    $this->monitoredUser = User::query()->create([
        'name' => 'Monitored User',
        'username' => 'monitored-user',
        'email' => 'monitored-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $purchasingDepartment->id,
        'role' => 'Staff',
        'last_seen_at' => now()->subHours(2),
        'last_ip_address' => '198.51.100.20',
        'last_user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    $this->monitoredUser->assignRole('purchasing-staff');
});

function insertSession(User $user, int $lastActivity, string $ip = '203.0.113.10', ?string $userAgent = null): string
{
    $sessionId = Str::random(40);

    DB::table('sessions')->insert([
        'id' => $sessionId,
        'user_id' => $user->id,
        'ip_address' => $ip,
        'user_agent' => $userAgent ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'payload' => base64_encode('test-payload'),
        'last_activity' => $lastActivity,
    ]);

    return $sessionId;
}

it('lists all users including those without active sessions', function () {
    $this->actingAs($this->admin)
        ->get(route('active-sessions.index'))
        ->assertSuccessful()
        ->assertSee('Active Users / Sessions')
        ->assertSee('Monitored User')
        ->assertSee('198.51.100.20')
        ->assertSee('Chrome on Windows')
        ->assertSee('Never', false);
});

it('marks users online or offline based on fresh sessions', function () {
    insertSession($this->monitoredUser, now()->timestamp);

    $this->actingAs($this->admin)
        ->get(route('active-sessions.index'))
        ->assertSuccessful()
        ->assertSee('Online')
        ->assertSee('Monitored User');
});

it('shows offline for idle users while still listing persisted last seen', function () {
    insertSession($this->monitoredUser, now()->subMinutes(10)->timestamp);

    $this->actingAs($this->admin)
        ->get(route('active-sessions.index'))
        ->assertSuccessful()
        ->assertSee('Monitored User')
        ->assertSee('198.51.100.20')
        ->assertSee('Offline');
});

it('allows it-staff to open the page', function () {
    $this->actingAs($this->itStaff)
        ->get(route('active-sessions.index'))
        ->assertSuccessful()
        ->assertSee('Monitored User');
});

it('forbids purchasing-staff from viewing active sessions', function () {
    $this->actingAs($this->purchasingStaff)
        ->get(route('active-sessions.index'))
        ->assertForbidden();
});

it('records last seen and login activity when a user signs in', function () {
    $this->post('/login', [
        'username' => 'monitored-user',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->monitoredUser->refresh();

    expect($this->monitoredUser->last_seen_at)->not->toBeNull()
        ->and($this->monitoredUser->last_ip_address)->not->toBeNull();

    expect(UserActivityLog::query()
        ->where('user_id', $this->monitoredUser->id)
        ->where('action', UserActivityLog::ACTION_LOGIN)
        ->exists())->toBeTrue();
});

it('shows activity history in the detail panel', function () {
    UserActivityLog::query()->create([
        'user_id' => $this->monitoredUser->id,
        'action' => UserActivityLog::ACTION_LOGIN,
        'ip_address' => '203.0.113.50',
        'user_agent' => 'Chrome',
        'meta' => null,
    ]);

    $this->actingAs($this->admin)
        ->get(route('active-sessions.show', $this->monitoredUser))
        ->assertSuccessful()
        ->assertSee('Activity History')
        ->assertSee('Logged in')
        ->assertSee('203.0.113.50');
});

it('shows a friendly page name instead of a raw path in activity detail', function () {
    UserActivityLog::query()->create([
        'user_id' => $this->monitoredUser->id,
        'action' => UserActivityLog::ACTION_ACTIVE,
        'ip_address' => '203.0.113.50',
        'user_agent' => 'Chrome',
        'meta' => [
            'route' => 'prs.index',
            'path' => '/prs',
            'page' => 'Purchase Requisitions',
        ],
    ]);

    UserActivityLog::query()->create([
        'user_id' => $this->monitoredUser->id,
        'action' => UserActivityLog::ACTION_ACTIVE,
        'ip_address' => '203.0.113.51',
        'user_agent' => 'Chrome',
        'meta' => [
            'route' => 'notifications.recent',
            'path' => '/notifications/recent',
        ],
    ]);

    $this->actingAs($this->admin)
        ->get(route('active-sessions.show', $this->monitoredUser))
        ->assertSuccessful()
        ->assertSee('Visited page')
        ->assertSee('Purchase Requisitions')
        ->assertSee('Notifications refresh')
        ->assertDontSee('/notifications/recent');
});

it('does not create activity logs for notification polling endpoints', function () {
    $this->actingAs($this->monitoredUser)
        ->get(route('notifications.recent'))
        ->assertSuccessful();

    expect(UserActivityLog::query()
        ->where('user_id', $this->monitoredUser->id)
        ->where('action', UserActivityLog::ACTION_ACTIVE)
        ->exists())->toBeFalse();
});

it('logs distinct page visits even within the last-seen throttle window', function () {
    $this->admin->forceFill([
        'last_seen_at' => now()->subSeconds(10),
    ])->save();

    $this->actingAs($this->admin)
        ->get(route('prs.index'))
        ->assertSuccessful();

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.approval'))
        ->assertSuccessful();

    $pages = UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->where('action', UserActivityLog::ACTION_ACTIVE)
        ->latest('id')
        ->limit(5)
        ->get()
        ->pluck('meta.page')
        ->all();

    expect($pages)->toContain('Purchase Requisitions')
        ->and($pages)->toContain('PO Approval');
});

it('does not duplicate the same page visit within five minutes', function () {
    $this->actingAs($this->admin)
        ->get(route('purchase-orders.approval'))
        ->assertSuccessful();

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.approval'))
        ->assertSuccessful();

    expect(UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->where('action', UserActivityLog::ACTION_ACTIVE)
        ->where('meta->route', 'purchase-orders.approval')
        ->count())->toBe(1);
});

it('force logs out a user and records the activity', function () {
    insertSession($this->monitoredUser, now()->timestamp);

    $this->actingAs($this->admin)
        ->delete(route('active-sessions.destroy-sessions', $this->monitoredUser))
        ->assertRedirect();

    expect(Session::query()->where('user_id', $this->monitoredUser->id)->exists())->toBeFalse();

    $log = UserActivityLog::query()
        ->where('user_id', $this->monitoredUser->id)
        ->where('action', UserActivityLog::ACTION_FORCE_LOGOUT)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($this->admin->id);
});

it('does not allow force logging out yourself', function () {
    insertSession($this->admin, now()->timestamp);

    $this->actingAs($this->admin)
        ->delete(route('active-sessions.destroy-sessions', $this->admin))
        ->assertRedirect();

    expect(Session::query()->where('user_id', $this->admin->id)->exists())->toBeTrue();

    expect(UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->where('action', UserActivityLog::ACTION_FORCE_LOGOUT)
        ->exists())->toBeFalse();
});

it('defaults to last activity sorting in the list order', function () {
    $this->admin->forceFill(['last_seen_at' => now()->subDay()])->save();
    $this->monitoredUser->forceFill(['last_seen_at' => now()])->save();

    $content = $this->actingAs($this->admin)
        ->get(route('active-sessions.index'))
        ->assertSuccessful()
        ->assertSee('value="last_seen" selected', false)
        ->getContent();

    expect(strpos($content, 'data-username="monitored-user"'))
        ->toBeLessThan(strpos($content, 'data-username="admin-sessions"'));
});

it('shows latest activity history in the list and exposes sort option', function () {
    UserActivityLog::query()->create([
        'user_id' => $this->monitoredUser->id,
        'action' => UserActivityLog::ACTION_LOGIN,
        'ip_address' => '203.0.113.50',
        'user_agent' => 'Chrome',
        'meta' => null,
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('active-sessions.index'))
        ->assertSuccessful()
        ->assertSee('Activity History')
        ->assertSee('Logged in')
        ->assertSee('value="activity_history"', false)
        ->assertSee('data-last-history=', false)
        ->assertSee('btn-light-secondary', false)
        ->assertDontSee('list-pagination', false);
});

it('exposes activity history timestamps for client-side sorting', function () {
    $this->travelTo(now()->startOfMinute());

    $monitoredLog = UserActivityLog::query()->create([
        'user_id' => $this->monitoredUser->id,
        'action' => UserActivityLog::ACTION_LOGIN,
        'ip_address' => '203.0.113.50',
        'user_agent' => 'Chrome',
        'meta' => null,
        'created_at' => now()->subMinutes(30),
        'updated_at' => now()->subMinutes(30),
    ]);

    $content = $this->actingAs($this->admin)
        ->get(route('active-sessions.index'))
        ->assertSuccessful()
        ->getContent();

    expect($content)->toContain('data-last-history="'.$monitoredLog->created_at->timestamp.'"', false);
});

it('renders status data attributes for realtime client filtering', function () {
    insertSession($this->monitoredUser, now()->timestamp);

    $this->actingAs($this->admin)
        ->get(route('active-sessions.index'))
        ->assertSuccessful()
        ->assertSee('data-status="online"', false)
        ->assertSee('data-status="offline"', false)
        ->assertSee('as-filter-reset', false)
        ->assertSee('Activity detail', false)
        ->assertSee('Force logout', false);
});

it('logs successful create activity without a subject id', function () {
    $this->actingAs($this->admin)
        ->post(route('currency.store'), [
            'code' => 'TST',
            'name' => 'Test Currency',
            'symbol' => 'T',
        ])
        ->assertRedirect();

    $log = UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->where('action', UserActivityLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->meta['route'] ?? null)->toBe('currency.store')
        ->and($log->meta['page'] ?? null)->toBe('Currencies')
        ->and($log->meta['subject'] ?? null)->toBeNull()
        ->and($log->meta['subject_id'] ?? null)->toBeNull();
});

it('does not log crud activity for login or broadcasting auth posts', function () {
    $this->post('/login', [
        'username' => 'monitored-user',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    expect(UserActivityLog::query()
        ->where('user_id', $this->monitoredUser->id)
        ->where('action', UserActivityLog::ACTION_CREATED)
        ->exists())->toBeFalse();

    $this->actingAs($this->admin)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$this->admin->id,
        ]);

    expect(UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->where('action', UserActivityLog::ACTION_CREATED)
        ->exists())->toBeFalse();
});

it('logs successful update activity with subject id from the route', function () {
    $currency = Currency::query()->create([
        'code' => 'UPD',
        'name' => 'Update Currency',
        'symbol' => 'U',
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('currency.update', $currency), [
            'code' => 'UPD',
            'name' => 'Updated Currency',
            'symbol' => 'U',
        ])
        ->assertRedirect();

    $log = UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->where('action', UserActivityLog::ACTION_UPDATED)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->meta['subject_id'] ?? null)->toBe($currency->id)
        ->and($log->meta['subject'] ?? null)->toBe('#'.$currency->id)
        ->and($log->subjectLabel())->toBe('#'.$currency->id);
});

it('logs successful delete activity with subject id from the model', function () {
    $currency = Currency::query()->create([
        'code' => 'DEL',
        'name' => 'Delete Currency',
        'symbol' => 'D',
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('currency.destroy', $currency))
        ->assertRedirect();

    $log = UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->where('action', UserActivityLog::ACTION_DELETED)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->meta['subject_id'] ?? null)->toBe($currency->id)
        ->and($log->meta['subject'] ?? null)->toBe('#'.$currency->id);
});

it('does not log crud activity when validation fails', function () {
    $before = UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->whereIn('action', [
            UserActivityLog::ACTION_CREATED,
            UserActivityLog::ACTION_UPDATED,
            UserActivityLog::ACTION_DELETED,
        ])
        ->count();

    $this->actingAs($this->admin)
        ->from(route('currency.index'))
        ->post(route('currency.store'), [
            'code' => '',
            'name' => '',
        ])
        ->assertRedirect(route('currency.index'))
        ->assertSessionHasErrors(['code', 'name']);

    $after = UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->whereIn('action', [
            UserActivityLog::ACTION_CREATED,
            UserActivityLog::ACTION_UPDATED,
            UserActivityLog::ACTION_DELETED,
        ])
        ->count();

    expect($after)->toBe($before);
});

it('does not log crud activity for notification mutating noise endpoints', function () {
    $before = UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->whereIn('action', [
            UserActivityLog::ACTION_CREATED,
            UserActivityLog::ACTION_UPDATED,
            UserActivityLog::ACTION_DELETED,
        ])
        ->count();

    $this->actingAs($this->admin)
        ->post(route('notifications.mark-all-read'))
        ->assertRedirect();

    $after = UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->whereIn('action', [
            UserActivityLog::ACTION_CREATED,
            UserActivityLog::ACTION_UPDATED,
            UserActivityLog::ACTION_DELETED,
        ])
        ->count();

    expect($after)->toBe($before);
});

it('shows subject id in the activity detail timeline', function () {
    UserActivityLog::query()->create([
        'user_id' => $this->monitoredUser->id,
        'action' => UserActivityLog::ACTION_UPDATED,
        'ip_address' => '203.0.113.50',
        'user_agent' => 'Chrome',
        'meta' => [
            'route' => 'prs.update',
            'path' => '/prs/12',
            'page' => 'Purchase Requisitions',
            'method' => 'PUT',
            'subject' => '#12',
            'subject_type' => 'prs',
            'subject_id' => 12,
        ],
    ]);

    $this->actingAs($this->admin)
        ->get(route('active-sessions.show', $this->monitoredUser))
        ->assertSuccessful()
        ->assertSee('Updated')
        ->assertSee('Purchase Requisitions')
        ->assertSee('#12');
});

it('logs purchase order approval activity with subject id', function () {
    $manager = User::query()->create([
        'name' => 'Purchasing Manager Activity',
        'username' => 'po-approve-manager',
        'email' => 'po-approve-manager@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->purchasingStaff->department_id,
        'role' => 'Manager',
    ]);
    $manager->assignRole('purchasing-manager');

    $currency = Currency::query()->create([
        'code' => 'IDR',
        'name' => 'Indonesian Rupiah',
        'symbol' => 'Rp',
        'created_by' => $manager->id,
    ]);

    $supplier = Supplier::query()->create([
        'code' => 'SUP-ACT-001',
        'name' => 'Activity Supplier',
        'created_by' => $manager->id,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'created_by' => $manager->id,
        'status' => 'PENDING_APPROVAL',
        'po_number' => 'PO-ACT-001',
        'subtotal' => 1000,
        'discount_amount' => 0,
        'ppn_amount' => 0,
        'pph_amount' => 0,
        'fees' => 0,
        'total' => 1000,
    ]);

    $this->actingAs($manager)
        ->post(route('purchase-orders.approve', $purchaseOrder))
        ->assertRedirect();

    $log = UserActivityLog::query()
        ->where('user_id', $manager->id)
        ->where('action', UserActivityLog::ACTION_APPROVED)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->meta['route'] ?? null)->toBe('purchase-orders.approve')
        ->and($log->meta['page'] ?? null)->toBe('PO Approval')
        ->and($log->meta['subject_id'] ?? null)->toBe($purchaseOrder->id)
        ->and($log->meta['subject'] ?? null)->toBe('#'.$purchaseOrder->id)
        ->and($log->label())->toBe('Approved');
});

it('refreshes the active sessions list without a full page reload', function () {
    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('active-sessions.index'));

    $response->assertSuccessful()
        ->assertSee('as-list', false)
        ->assertSee('Monitored User')
        ->assertDontSee('<h3 class="mb-0">Active Users / Sessions</h3>', false)
        ->assertDontSee('id="as-search"', false);
});

it('does not log broadcasting auth as a page visit', function () {
    $this->actingAs($this->admin)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$this->admin->id,
        ]);

    expect(UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->where('action', UserActivityLog::ACTION_ACTIVE)
        ->where(function ($query) {
            $query->where('meta->page', 'Broadcasting')
                ->orWhere('meta->path', 'like', '%broadcasting%');
        })
        ->exists())->toBeFalse();
});

it('logs purchase order list visits including ajax filter navigations', function () {
    $this->actingAs($this->admin)
        ->get(route('purchase-orders.index'))
        ->assertSuccessful();

    expect(UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->where('action', UserActivityLog::ACTION_ACTIVE)
        ->where('meta->route', 'purchase-orders.index')
        ->exists())->toBeTrue();

    UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->where('action', UserActivityLog::ACTION_ACTIVE)
        ->delete();

    $this->actingAs($this->admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('purchase-orders.index', ['status' => 'APPROVED']))
        ->assertSuccessful();

    $log = UserActivityLog::query()
        ->where('user_id', $this->admin->id)
        ->where('action', UserActivityLog::ACTION_ACTIVE)
        ->where('meta->route', 'purchase-orders.index')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->meta['page'] ?? null)->toBe('Purchase Orders');
});

it('allows administrators to reset activity logs and logout everyone', function () {
    insertSession($this->monitoredUser, now()->timestamp);
    insertSession($this->admin, now()->timestamp);

    UserActivityLog::query()->create([
        'user_id' => $this->monitoredUser->id,
        'action' => UserActivityLog::ACTION_ACTIVE,
        'ip_address' => '203.0.113.50',
        'user_agent' => 'Chrome',
        'meta' => ['page' => 'Purchase Orders'],
    ]);

    $this->actingAs($this->admin)
        ->delete(route('active-sessions.reset-activity-logs'), [
            'reset_password' => 'Administrator123',
        ])
        ->assertRedirect(route('login'));

    expect(UserActivityLog::query()->count())->toBe(0)
        ->and(Session::query()->count())->toBe(0)
        ->and(auth()->check())->toBeFalse();

    $this->actingAs($this->admin);

    $fresh = UserActivityLog::query()->create([
        'user_id' => $this->admin->id,
        'action' => UserActivityLog::ACTION_LOGIN,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Chrome',
        'meta' => null,
    ]);

    expect($fresh->id)->toBe(1);
});

it('rejects activity log reset when the password is wrong', function () {
    UserActivityLog::query()->create([
        'user_id' => $this->monitoredUser->id,
        'action' => UserActivityLog::ACTION_ACTIVE,
        'ip_address' => '203.0.113.50',
        'user_agent' => 'Chrome',
        'meta' => ['page' => 'Purchase Orders'],
    ]);

    $this->actingAs($this->admin)
        ->from(route('active-sessions.index'))
        ->delete(route('active-sessions.reset-activity-logs'), [
            'reset_password' => 'wrong-password',
        ])
        ->assertRedirect(route('active-sessions.index'));

    expect(UserActivityLog::query()->count())->toBe(1)
        ->and(auth()->check())->toBeTrue();
});

it('forbids it-staff from resetting activity logs', function () {
    $this->actingAs($this->itStaff)
        ->delete(route('active-sessions.reset-activity-logs'))
        ->assertForbidden();

    expect(UserActivityLog::query()->count())->toBeGreaterThanOrEqual(0);
});

it('shows the reset activity logs control only with reset-activity-logs permission', function () {
    $this->actingAs($this->admin)
        ->get(route('active-sessions.index'))
        ->assertSuccessful()
        ->assertSee('Reset activity logs')
        ->assertSee('as-detail-refresh', false);

    $this->actingAs($this->itStaff)
        ->get(route('active-sessions.index'))
        ->assertSuccessful()
        ->assertDontSee('Reset activity logs');
});
