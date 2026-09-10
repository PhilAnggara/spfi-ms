<?php

use App\Enums\ScreenMessageAudienceType;
use App\Enums\ScreenMessageDisplayMode;
use App\Models\Department;
use App\Models\ScreenMessageRecipient;
use App\Models\User;
use App\Services\ScreenMessageService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Overlay Dept',
        'code' => 'OVL',
        'alias' => 'OVL',
        'is_active' => true,
    ]);
});

function createOverlayUser(string $username, array $permissions = []): User
{
    $user = User::query()->create([
        'name' => "Overlay {$username}",
        'username' => $username,
        'email' => "{$username}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

it('returns pending messages for recipients and removes them after dismiss', function () {
    $sender = createOverlayUser('ov-sender', [
        'create-all-screen-messages',
        'view-own-screen-messages',
    ]);
    $recipient = createOverlayUser('ov-recipient');

    $message = app(ScreenMessageService::class)->create($sender, [
        'title' => 'Pending notice',
        'body' => 'Please read',
        'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
        'duration_seconds' => 25,
        'audience_type' => ScreenMessageAudienceType::Users->value,
        'target_ids' => [$recipient->id],
        'allow_reply' => false,
    ]);

    $this->actingAs($recipient)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertOk()
        ->assertJsonPath('messages.0.id', $message->id)
        ->assertJsonPath('messages.0.title', 'Pending notice');

    $this->actingAs($recipient)
        ->postJson(route('screen-messages.inbox.seen', $message))
        ->assertOk();

    expect(
        ScreenMessageRecipient::query()
            ->where('screen_message_id', $message->id)
            ->where('user_id', $recipient->id)
            ->value('seen_at')
    )->not->toBeNull();

    $this->actingAs($recipient)
        ->postJson(route('screen-messages.inbox.dismiss', $message))
        ->assertOk();

    $this->actingAs($recipient)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertOk()
        ->assertJsonCount(0, 'messages');
});

it('keeps permanent messages pending after seen until deactivated', function () {
    $sender = createOverlayUser('ov-perm-sender', [
        'create-all-screen-messages',
        'create-permanent-screen-messages',
        'deactivate-own-screen-messages',
        'view-own-screen-messages',
    ]);
    $recipient = createOverlayUser('ov-perm-recipient');

    $message = app(ScreenMessageService::class)->create($sender, [
        'title' => 'Lock screen',
        'body' => 'Stay here',
        'display_mode' => ScreenMessageDisplayMode::Permanent->value,
        'audience_type' => ScreenMessageAudienceType::Users->value,
        'target_ids' => [$recipient->id],
    ]);

    $this->actingAs($recipient)
        ->postJson(route('screen-messages.inbox.seen', $message))
        ->assertOk();

    $this->actingAs($recipient)
        ->postJson(route('screen-messages.inbox.dismiss', $message))
        ->assertStatus(422);

    $this->actingAs($recipient)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertOk()
        ->assertJsonPath('messages.0.id', $message->id);

    $this->actingAs($sender)
        ->post(route('screen-messages.deactivate', $message))
        ->assertRedirect();

    $this->actingAs($recipient)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertOk()
        ->assertJsonCount(0, 'messages');
});

it('removes soft-deleted messages from pending', function () {
    $sender = createOverlayUser('ov-del-sender', [
        'create-all-screen-messages',
        'delete-own-screen-messages',
        'view-own-screen-messages',
    ]);
    $recipient = createOverlayUser('ov-del-recipient');

    $message = app(ScreenMessageService::class)->create($sender, [
        'title' => 'Will delete',
        'body' => 'Bye',
        'display_mode' => ScreenMessageDisplayMode::AutoOnly->value,
        'duration_seconds' => 10,
        'audience_type' => ScreenMessageAudienceType::Users->value,
        'target_ids' => [$recipient->id],
    ]);

    $this->actingAs($recipient)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertJsonCount(1, 'messages');

    $this->actingAs($sender)
        ->delete(route('screen-messages.destroy', $message))
        ->assertRedirect();

    $this->actingAs($recipient)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertJsonCount(0, 'messages');
});

it('rejects replies when allow_reply is false', function () {
    $sender = createOverlayUser('ov-noreply-sender', [
        'create-all-screen-messages',
    ]);
    $recipient = createOverlayUser('ov-noreply-recipient');

    $message = app(ScreenMessageService::class)->create($sender, [
        'title' => 'No reply',
        'body' => 'Read only',
        'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
        'duration_seconds' => 12,
        'audience_type' => ScreenMessageAudienceType::Users->value,
        'target_ids' => [$recipient->id],
        'allow_reply' => false,
    ]);

    $this->actingAs($recipient)
        ->postJson(route('screen-messages.inbox.reply', $message), ['body' => 'Nope'])
        ->assertStatus(422);
});

it('shows read receipts on the sender detail page', function () {
    $sender = createOverlayUser('ov-receipt-sender', [
        'create-all-screen-messages',
        'view-own-screen-messages',
    ]);
    $seenUser = createOverlayUser('ov-seen');
    $unseenUser = createOverlayUser('ov-unseen');

    $message = app(ScreenMessageService::class)->create($sender, [
        'title' => 'Receipts',
        'body' => 'Track me',
        'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
        'duration_seconds' => 20,
        'audience_type' => ScreenMessageAudienceType::Users->value,
        'target_ids' => [$seenUser->id, $unseenUser->id],
    ]);

    $this->actingAs($seenUser)
        ->postJson(route('screen-messages.inbox.seen', $message))
        ->assertOk();

    $this->actingAs($sender)
        ->get(route('screen-messages.show', $message))
        ->assertOk()
        ->assertSee('1 / 2')
        ->assertSee($seenUser->name)
        ->assertSee($unseenUser->name)
        ->assertSee('Not yet');
});

it('stores null duration for user closable without timer', function () {
    $sender = createOverlayUser('ov-nodur-sender', [
        'create-all-screen-messages',
        'view-own-screen-messages',
    ]);
    $recipient = createOverlayUser('ov-nodur-recipient');

    $this->actingAs($sender)
        ->post(route('screen-messages.store'), [
            'title' => 'No timer',
            'body' => 'Close yourself',
            'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
            'duration_seconds' => null,
            'audience_type' => ScreenMessageAudienceType::Users->value,
            'target_ids' => [$recipient->id],
        ])
        ->assertRedirect();

    $message = \App\Models\ScreenMessage::query()->where('title', 'No timer')->first();
    expect($message)->not->toBeNull()
        ->and($message->duration_seconds)->toBeNull();

    $this->actingAs($recipient)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertOk()
        ->assertJsonPath('messages.0.id', $message->id)
        ->assertJsonPath('messages.0.expires_at', null);
});

it('sets overlay_expires_at once and resumes the same expiry on pending', function () {
    $sender = createOverlayUser('ov-exp-sender', [
        'create-all-screen-messages',
    ]);
    $recipient = createOverlayUser('ov-exp-recipient');

    $message = app(ScreenMessageService::class)->create($sender, [
        'title' => 'Timed',
        'body' => 'Countdown',
        'display_mode' => ScreenMessageDisplayMode::AutoOnly->value,
        'duration_seconds' => 60,
        'audience_type' => ScreenMessageAudienceType::Users->value,
        'target_ids' => [$recipient->id],
    ]);

    $first = $this->actingAs($recipient)
        ->postJson(route('screen-messages.inbox.seen', $message))
        ->assertOk()
        ->json('expires_at');

    expect($first)->not->toBeNull();

    $this->travel(5)->seconds();

    $second = $this->actingAs($recipient)
        ->postJson(route('screen-messages.inbox.seen', $message))
        ->assertOk()
        ->json('expires_at');

    expect($second)->toBe($first);

    $this->actingAs($recipient)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertOk()
        ->assertJsonPath('messages.0.expires_at', $first);
});

it('includes my_reply in pending after the recipient replies', function () {
    $sender = createOverlayUser('ov-myreply-sender', [
        'create-all-screen-messages',
        'view-own-screen-messages',
        'view-own-screen-message-replies',
    ]);
    $recipient = createOverlayUser('ov-myreply-recipient');

    $message = app(ScreenMessageService::class)->create($sender, [
        'title' => 'Reply me',
        'body' => 'Please answer',
        'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
        'audience_type' => ScreenMessageAudienceType::Users->value,
        'target_ids' => [$recipient->id],
        'allow_reply' => true,
    ]);

    $this->actingAs($recipient)
        ->postJson(route('screen-messages.inbox.reply', $message), ['body' => 'Here is my answer'])
        ->assertOk();

    $this->actingAs($recipient)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertOk()
        ->assertJsonPath('messages.0.my_reply.body', 'Here is my answer');
});

it('returns live recipient and reply data for authorized viewers', function () {
    $sender = createOverlayUser('ov-live-sender', [
        'create-all-screen-messages',
        'view-own-screen-messages',
        'view-own-screen-message-replies',
    ]);
    $outsider = createOverlayUser('ov-live-outsider', [
        'view-own-screen-messages',
    ]);
    $recipient = createOverlayUser('ov-live-recipient');

    $message = app(ScreenMessageService::class)->create($sender, [
        'title' => 'Live feed',
        'body' => 'Watch me',
        'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
        'audience_type' => ScreenMessageAudienceType::Users->value,
        'target_ids' => [$recipient->id],
        'allow_reply' => true,
    ]);

    $this->actingAs($recipient)
        ->postJson(route('screen-messages.inbox.seen', $message))
        ->assertOk();
    $this->actingAs($recipient)
        ->postJson(route('screen-messages.inbox.reply', $message), ['body' => 'Live reply'])
        ->assertOk();

    $this->actingAs($outsider)
        ->getJson(route('screen-messages.live', $message))
        ->assertForbidden();

    $this->actingAs($sender)
        ->getJson(route('screen-messages.live', $message))
        ->assertOk()
        ->assertJsonPath('seen_count', 1)
        ->assertJsonPath('recipient_count', 1)
        ->assertJsonPath('recipients.0.name', $recipient->name)
        ->assertJsonPath('replies.0.body', 'Live reply');
});

it('includes theme in pending overlay payload', function () {
    $sender = createOverlayUser('ov-theme-sender', [
        'create-all-screen-messages',
        'view-own-screen-messages',
    ]);
    $recipient = createOverlayUser('ov-theme-recipient');

    $message = app(ScreenMessageService::class)->create($sender, [
        'title' => 'Warning notice',
        'body' => 'Please check stock',
        'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
        'audience_type' => ScreenMessageAudienceType::Users->value,
        'target_ids' => [$recipient->id],
        'theme' => \App\Enums\ScreenMessageTheme::Warning->value,
        'allow_reply' => false,
    ]);

    expect($message->theme)->toBe(\App\Enums\ScreenMessageTheme::Warning);

    $this->actingAs($recipient)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertOk()
        ->assertJsonPath('messages.0.id', $message->id)
        ->assertJsonPath('messages.0.theme', 'warning')
        ->assertJsonPath('messages.0.theme_label', 'Warning');
});

it('removes deactivated messages from pending for recipients', function () {
    $sender = createOverlayUser('ov-deact-sender', [
        'create-all-screen-messages',
        'view-own-screen-messages',
        'deactivate-own-screen-messages',
    ]);
    $recipient = createOverlayUser('ov-deact-recipient');

    $message = app(ScreenMessageService::class)->create($sender, [
        'title' => 'Will deactivate',
        'body' => 'Close me remotely',
        'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
        'audience_type' => ScreenMessageAudienceType::Users->value,
        'target_ids' => [$recipient->id],
    ]);

    $this->actingAs($recipient)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertJsonCount(1, 'messages');

    $this->actingAs($sender)
        ->post(route('screen-messages.deactivate', $message))
        ->assertRedirect();

    $this->actingAs($recipient)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertOk()
        ->assertJsonCount(0, 'messages');
});
