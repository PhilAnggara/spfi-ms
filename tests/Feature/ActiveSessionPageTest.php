<?php

use App\Models\Department;
use App\Models\Session;
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
