<?php

use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

it('stores activity logs with optional actor', function () {
    $user = User::query()->create([
        'name' => 'Activity User',
        'username' => 'activity-user',
        'email' => 'activity-user@example.test',
        'password' => Hash::make('password'),
        'role' => 'Staff',
    ]);

    $actor = User::query()->create([
        'name' => 'Activity Actor',
        'username' => 'activity-actor',
        'email' => 'activity-actor@example.test',
        'password' => Hash::make('password'),
        'role' => 'Staff',
    ]);

    $request = Request::create('/test', 'GET', server: [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'PestAgent/1.0',
    ]);

    $log = app(UserActivityLogger::class)->log(
        $user,
        UserActivityLog::ACTION_FORCE_LOGOUT,
        $request,
        $actor,
        ['reason' => 'admin'],
    );

    expect($log->user_id)->toBe($user->id)
        ->and($log->actor_id)->toBe($actor->id)
        ->and($log->action)->toBe(UserActivityLog::ACTION_FORCE_LOGOUT)
        ->and($log->ip_address)->toBe('127.0.0.1')
        ->and($log->meta)->toBe(['reason' => 'admin']);

    $this->assertDatabaseHas('user_activity_logs', [
        'id' => $log->id,
        'user_id' => $user->id,
        'actor_id' => $actor->id,
        'action' => UserActivityLog::ACTION_FORCE_LOGOUT,
    ]);
});

it('allows activity logs without an actor', function () {
    $user = User::query()->create([
        'name' => 'Login User',
        'username' => 'login-activity-user',
        'email' => 'login-activity-user@example.test',
        'password' => Hash::make('password'),
        'role' => 'Staff',
    ]);

    $request = Request::create('/login', 'POST');

    $log = app(UserActivityLogger::class)->log(
        $user,
        UserActivityLog::ACTION_LOGIN,
        $request,
    );

    expect($log->actor_id)->toBeNull();
});
