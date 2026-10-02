<?php

use App\Models\Department;
use App\Models\User;
use App\Notifications\ProcessNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Notifications Perf Dept',
        'code' => '7043-NPRF',
        'alias' => 'NPRF',
    ]);

    $this->user = User::query()->create([
        'name' => 'Notification Perf User',
        'username' => 'notif-perf-user',
        'email' => 'notif-perf-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');
});

it('returns unread count with a sql aggregate instead of loading all unread rows', function () {
    for ($i = 1; $i <= 12; $i++) {
        $this->user->notify(new ProcessNotification([
            'title' => 'Unread '.$i,
            'message' => 'Message '.$i,
        ]));
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains(strtolower($query->sql), 'notifications')) {
            $queries[] = strtolower($query->sql);
        }
    });

    $this->actingAs($this->user)
        ->getJson(route('notifications.unread-count'))
        ->assertOk()
        ->assertJson(['count' => 12]);

    expect($queries)->not->toBeEmpty();

    $usesAggregate = collect($queries)->contains(
        fn (string $sql): bool => str_contains($sql, 'count(')
    );
    $selectsAllUnreadRows = collect($queries)->contains(
        fn (string $sql): bool => str_contains($sql, 'select *')
            && str_contains($sql, 'read_at')
            && ! str_contains($sql, 'count(')
    );

    expect($usesAggregate)->toBeTrue()
        ->and($selectsAllUnreadRows)->toBeFalse();
});

it('marks all unread notifications as read with a bulk update', function () {
    for ($i = 1; $i <= 5; $i++) {
        $this->user->notify(new ProcessNotification([
            'title' => 'Bulk '.$i,
            'message' => 'Message '.$i,
        ]));
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains(strtolower($query->sql), 'notifications')) {
            $queries[] = strtolower($query->sql);
        }
    });

    $this->actingAs($this->user)
        ->postJson(route('notifications.mark-all-read'))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($this->user->fresh()->unreadNotifications()->count())->toBe(0);

    $usesBulkUpdate = collect($queries)->contains(
        fn (string $sql): bool => str_contains($sql, 'update')
            && str_contains($sql, 'read_at')
    );

    expect($usesBulkUpdate)->toBeTrue();
});

it('renders navbar notifications with a limited query instead of loading the full collection', function () {
    for ($i = 1; $i <= 15; $i++) {
        $this->user->notify(new ProcessNotification([
            'title' => 'Navbar Notif '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'message' => 'Navbar message '.$i,
        ]));
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains(strtolower($query->sql), 'notifications')) {
            $queries[] = strtolower($query->sql);
        }
    });

    $this->actingAs($this->user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Navbar Notif 15')
        ->assertSee('id="notificationBadge"', false);

    $loadsFullNotificationsCollection = collect($queries)->contains(
        fn (string $sql): bool => str_contains($sql, 'select *')
            && str_contains($sql, 'notifications')
            && ! str_contains($sql, 'count(')
            && ! str_contains($sql, 'top ')
            && ! str_contains($sql, 'limit')
            && ! preg_match('/\bin\s*\(/', $sql)
    );

    $limitsRecent = collect($queries)->contains(
        fn (string $sql): bool => (str_contains($sql, 'limit') || str_contains($sql, 'top '))
            && str_contains($sql, 'select')
            && ! str_contains($sql, 'count(')
    );

    $countsUnread = collect($queries)->contains(
        fn (string $sql): bool => str_contains($sql, 'count(')
            && str_contains($sql, 'read_at')
    );

    expect($limitsRecent)->toBeTrue()
        ->and($countsUnread)->toBeTrue()
        ->and($loadsFullNotificationsCollection)->toBeFalse();
});
