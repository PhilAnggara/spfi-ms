<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('adds portable unread composite indexes on notifications and messages', function () {
    expect(Schema::hasIndex('notifications', 'notifications_notifiable_read_at_index'))->toBeTrue()
        ->and(Schema::hasIndex('messages', 'messages_conversation_user_created_index'))->toBeTrue()
        ->and(Schema::hasIndex('messages', 'messages_conversation_persona_created_index'))->toBeTrue();
});

it('keeps notification unread count query working after unread indexes', function () {
    $user = User::factory()->create();

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\ProcessNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['title' => 'Index test', 'message' => 'ok']),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->unreadNotifications()->count())->toBe(1);
});
