<?php

use App\Enums\ScreenMessageAudienceType;
use App\Enums\ScreenMessageDisplayMode;
use App\Events\ScreenMessageDeactivated;
use App\Events\ScreenMessageSent;
use App\Models\Department;
use App\Models\ScreenMessage;
use App\Models\ScreenMessageRecipient;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);

    $this->departmentA = Department::query()->create([
        'name' => 'Screen Dept A',
        'code' => 'SMA',
        'alias' => 'SMA',
        'is_active' => true,
    ]);
    $this->departmentB = Department::query()->create([
        'name' => 'Screen Dept B',
        'code' => 'SMB',
        'alias' => 'SMB',
        'is_active' => true,
    ]);
});

function createScreenUser(string $username, int $departmentId, array $permissions = []): User
{
    $user = User::query()->create([
        'name' => "Screen {$username}",
        'username' => $username,
        'email' => "{$username}@example.test",
        'password' => Hash::make('password'),
        'department_id' => $departmentId,
        'role' => 'Staff',
    ]);

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

it('scopes index to own department and all messages by permission', function () {
    $senderA = createScreenUser('sm-sender-a', $this->departmentA->id, [
        'view-own-screen-messages',
        'create-department-screen-messages',
        'deactivate-own-screen-messages',
    ]);
    $peerA = createScreenUser('sm-peer-a', $this->departmentA->id, [
        'view-department-screen-messages',
    ]);
    $senderB = createScreenUser('sm-sender-b', $this->departmentB->id, [
        'view-own-screen-messages',
        'create-department-screen-messages',
    ]);
    $viewerAll = createScreenUser('sm-viewer-all', $this->departmentA->id, [
        'view-all-screen-messages',
    ]);
    $recipient = createScreenUser('sm-recipient-scope', $this->departmentA->id);

    $messageA = ScreenMessage::factory()->create([
        'user_id' => $senderA->id,
        'audience_type' => ScreenMessageAudienceType::Users,
    ]);
    ScreenMessageRecipient::query()->create([
        'screen_message_id' => $messageA->id,
        'user_id' => $recipient->id,
    ]);

    $messageB = ScreenMessage::factory()->create([
        'user_id' => $senderB->id,
        'audience_type' => ScreenMessageAudienceType::Users,
    ]);
    ScreenMessageRecipient::query()->create([
        'screen_message_id' => $messageB->id,
        'user_id' => $recipient->id,
    ]);

    $this->actingAs($senderA)
        ->get(route('screen-messages.index'))
        ->assertOk()
        ->assertSee($messageA->title)
        ->assertDontSee($messageB->title);

    $this->actingAs($peerA)
        ->get(route('screen-messages.index'))
        ->assertOk()
        ->assertSee($messageA->title)
        ->assertDontSee($messageB->title);

    $this->actingAs($viewerAll)
        ->get(route('screen-messages.index'))
        ->assertOk()
        ->assertSee($messageA->title)
        ->assertSee($messageB->title);
});

it('prevents department creators from messaging outside their department', function () {
    Event::fake([ScreenMessageSent::class]);

    $sender = createScreenUser('sm-dept-sender', $this->departmentA->id, [
        'create-department-screen-messages',
        'view-own-screen-messages',
    ]);
    $sameDept = createScreenUser('sm-same-dept', $this->departmentA->id);
    $otherDept = createScreenUser('sm-other-dept', $this->departmentB->id);

    $this->actingAs($sender)
        ->post(route('screen-messages.store'), [
            'title' => 'Dept only',
            'body' => 'Hello team',
            'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
            'duration_seconds' => 20,
            'audience_type' => ScreenMessageAudienceType::Users->value,
            'target_ids' => [$otherDept->id],
            'allow_reply' => false,
        ])
        ->assertSessionHasErrors('target_ids');

    $this->actingAs($sender)
        ->post(route('screen-messages.store'), [
            'title' => 'Dept ok',
            'body' => 'Hello team',
            'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
            'duration_seconds' => 20,
            'audience_type' => ScreenMessageAudienceType::Users->value,
            'target_ids' => [$sameDept->id],
            'allow_reply' => false,
        ])
        ->assertRedirect();

    expect(ScreenMessage::query()->where('title', 'Dept ok')->exists())->toBeTrue();
    Event::assertDispatched(ScreenMessageSent::class);
});

it('allows create-all to target any user and broadcasts sent events', function () {
    Event::fake([ScreenMessageSent::class]);

    $sender = createScreenUser('sm-all-sender', $this->departmentA->id, [
        'create-all-screen-messages',
        'view-own-screen-messages',
    ]);
    $otherDept = createScreenUser('sm-all-target', $this->departmentB->id);

    $this->actingAs($sender)
        ->post(route('screen-messages.store'), [
            'title' => 'Company wide',
            'body' => 'Important notice',
            'display_mode' => ScreenMessageDisplayMode::AutoOnly->value,
            'duration_seconds' => 15,
            'audience_type' => ScreenMessageAudienceType::Users->value,
            'target_ids' => [$otherDept->id],
        ])
        ->assertRedirect();

    Event::assertDispatched(ScreenMessageSent::class, function (ScreenMessageSent $event) use ($otherDept): bool {
        return $event->recipientUserId === $otherDept->id
            && $event->message->title === 'Company wide';
    });
});

it('creates the message even when broadcasting throws', function () {
    $sender = createScreenUser('sm-broadcast-fail', $this->departmentA->id, [
        'create-all-screen-messages',
        'view-own-screen-messages',
    ]);
    $target = createScreenUser('sm-broadcast-target', $this->departmentA->id);

    $this->partialMock(\Illuminate\Broadcasting\BroadcastManager::class, function ($mock): void {
        $mock->shouldReceive('event')->andThrow(new Illuminate\Broadcasting\BroadcastException('Reverb down'));
        $mock->shouldReceive('queue')->andThrow(new Illuminate\Broadcasting\BroadcastException('Reverb down'));
        $mock->shouldReceive('connection')->andThrow(new Illuminate\Broadcasting\BroadcastException('Reverb down'));
    });

    $this->actingAs($sender)
        ->post(route('screen-messages.store'), [
            'title' => 'Still saved',
            'body' => 'Broadcast may fail',
            'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
            'duration_seconds' => 20,
            'audience_type' => ScreenMessageAudienceType::Users->value,
            'target_ids' => [$target->id],
        ])
        ->assertRedirect();

    expect(ScreenMessage::query()->where('title', 'Still saved')->exists())->toBeTrue();
});

it('rejects permanent mode without dedicated permission', function () {
    $sender = createScreenUser('sm-no-perm', $this->departmentA->id, [
        'create-all-screen-messages',
        'view-own-screen-messages',
    ]);
    $target = createScreenUser('sm-no-perm-target', $this->departmentA->id);

    $this->actingAs($sender)
        ->post(route('screen-messages.store'), [
            'title' => 'Permanent blocked',
            'body' => 'Should fail',
            'display_mode' => ScreenMessageDisplayMode::Permanent->value,
            'audience_type' => ScreenMessageAudienceType::Users->value,
            'target_ids' => [$target->id],
        ])
        ->assertSessionHasErrors('display_mode');
});

it('creates permanent messages when permitted', function () {
    Event::fake([ScreenMessageSent::class]);

    $sender = createScreenUser('sm-perm-sender', $this->departmentA->id, [
        'create-all-screen-messages',
        'create-permanent-screen-messages',
        'view-own-screen-messages',
    ]);
    $target = createScreenUser('sm-perm-target', $this->departmentA->id);

    $this->actingAs($sender)
        ->post(route('screen-messages.store'), [
            'title' => 'Stay forever',
            'body' => 'Until deactivated',
            'display_mode' => ScreenMessageDisplayMode::Permanent->value,
            'audience_type' => ScreenMessageAudienceType::Users->value,
            'target_ids' => [$target->id],
        ])
        ->assertRedirect();

    $message = ScreenMessage::query()->where('title', 'Stay forever')->first();
    expect($message)->not->toBeNull()
        ->and($message->display_mode)->toBe(ScreenMessageDisplayMode::Permanent)
        ->and($message->duration_seconds)->toBeNull();
});

it('allows deactivate and delete only with matching permissions', function () {
    Event::fake([ScreenMessageDeactivated::class]);

    $sender = createScreenUser('sm-owner', $this->departmentA->id, [
        'view-own-screen-messages',
        'deactivate-own-screen-messages',
        'delete-own-screen-messages',
    ]);
    $outsider = createScreenUser('sm-outsider', $this->departmentB->id, [
        'view-own-screen-messages',
        'deactivate-own-screen-messages',
        'delete-own-screen-messages',
    ]);
    $deptManager = createScreenUser('sm-dept-mgr', $this->departmentA->id, [
        'view-department-screen-messages',
        'deactivate-department-screen-messages',
        'delete-department-screen-messages',
    ]);
    $recipient = createScreenUser('sm-del-recipient', $this->departmentA->id);

    $message = ScreenMessage::factory()->create([
        'user_id' => $sender->id,
        'is_active' => true,
    ]);
    ScreenMessageRecipient::query()->create([
        'screen_message_id' => $message->id,
        'user_id' => $recipient->id,
    ]);

    $this->actingAs($outsider)
        ->post(route('screen-messages.deactivate', $message))
        ->assertForbidden();

    $this->actingAs($deptManager)
        ->post(route('screen-messages.deactivate', $message))
        ->assertRedirect();

    expect($message->fresh()->is_active)->toBeFalse();
    Event::assertDispatched(ScreenMessageDeactivated::class);

    $active = ScreenMessage::factory()->create([
        'user_id' => $sender->id,
        'is_active' => true,
    ]);
    ScreenMessageRecipient::query()->create([
        'screen_message_id' => $active->id,
        'user_id' => $recipient->id,
    ]);

    $this->actingAs($sender)
        ->delete(route('screen-messages.destroy', $active))
        ->assertRedirect(route('screen-messages.index'));

    expect(ScreenMessage::withTrashed()->find($active->id)?->trashed())->toBeTrue()
        ->and(ScreenMessage::withTrashed()->find($active->id)?->is_active)->toBeFalse();
});

it('hides replies without reply view permission', function () {
    $sender = createScreenUser('sm-reply-owner', $this->departmentA->id, [
        'view-own-screen-messages',
    ]);
    $recipient = createScreenUser('sm-reply-user', $this->departmentA->id);

    $message = ScreenMessage::factory()->create([
        'user_id' => $sender->id,
        'allow_reply' => true,
    ]);
    ScreenMessageRecipient::query()->create([
        'screen_message_id' => $message->id,
        'user_id' => $recipient->id,
    ]);

    $this->actingAs($recipient)
        ->postJson(route('screen-messages.inbox.reply', $message), ['body' => 'Got it'])
        ->assertOk();

    $this->actingAs($sender)
        ->get(route('screen-messages.show', $message))
        ->assertOk()
        ->assertDontSee('Got it');

    $sender->givePermissionTo('view-own-screen-message-replies');

    $this->actingAs($sender->fresh())
        ->get(route('screen-messages.show', $message))
        ->assertOk()
        ->assertSee('Got it');
});

it('stores the selected theme when creating a screen message', function () {
    $sender = createScreenUser('sm-theme-sender', $this->departmentA->id, [
        'create-all-screen-messages',
        'view-own-screen-messages',
    ]);
    $target = createScreenUser('sm-theme-target', $this->departmentA->id);

    $this->actingAs($sender)
        ->post(route('screen-messages.store'), [
            'title' => 'Danger alert',
            'body' => 'Immediate action needed',
            'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
            'audience_type' => ScreenMessageAudienceType::Users->value,
            'target_ids' => [$target->id],
            'theme' => \App\Enums\ScreenMessageTheme::Danger->value,
        ])
        ->assertRedirect();

    $message = ScreenMessage::query()->where('title', 'Danger alert')->first();

    expect($message)->not->toBeNull()
        ->and($message->theme)->toBe(\App\Enums\ScreenMessageTheme::Danger);

    $this->actingAs($sender)
        ->get(route('screen-messages.show', $message))
        ->assertOk()
        ->assertSee('Danger');
});

it('rejects invalid themes', function () {
    $sender = createScreenUser('sm-bad-theme', $this->departmentA->id, [
        'create-all-screen-messages',
        'view-own-screen-messages',
    ]);
    $target = createScreenUser('sm-bad-theme-target', $this->departmentA->id);

    $this->actingAs($sender)
        ->post(route('screen-messages.store'), [
            'title' => 'Bad theme',
            'body' => 'Should fail',
            'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
            'audience_type' => ScreenMessageAudienceType::Users->value,
            'target_ids' => [$target->id],
            'theme' => 'neon',
        ])
        ->assertSessionHasErrors('theme');
});

it('stores sanitized rich text body and strips xss', function () {
    $sender = createScreenUser('sm-rich-sender', $this->departmentA->id, [
        'create-all-screen-messages',
        'view-own-screen-messages',
    ]);
    $target = createScreenUser('sm-rich-target', $this->departmentA->id);

    $this->actingAs($sender)
        ->post(route('screen-messages.store'), [
            'title' => 'Rich notice',
            'body' => '<p>Please <strong>read</strong></p><ul><li>Item</li></ul><script>alert(1)</script><p onclick="x">Done</p>',
            'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
            'audience_type' => ScreenMessageAudienceType::Users->value,
            'target_ids' => [$target->id],
            'theme' => 'info',
        ])
        ->assertRedirect();

    $message = ScreenMessage::query()->where('title', 'Rich notice')->first();

    expect($message)->not->toBeNull()
        ->and($message->body)->toContain('<strong>read</strong>')
        ->and($message->body)->toContain('<li>Item</li>')
        ->and($message->body)->not->toContain('script')
        ->and($message->body)->not->toContain('onclick');

    $payload = $message->toOverlayPayload($target);

    expect($payload['body'])
        ->toContain('<strong>read</strong>')
        ->not->toContain('script');

    $this->actingAs($target)
        ->getJson(route('screen-messages.inbox.pending'))
        ->assertOk()
        ->assertJsonPath('messages.0.id', $message->id);

    expect($this->actingAs($target)->getJson(route('screen-messages.inbox.pending'))->json('messages.0.body'))
        ->toContain('<strong>read</strong>')
        ->not->toContain('script');
});

it('rejects empty rich text body after sanitizing', function () {
    $sender = createScreenUser('sm-empty-html', $this->departmentA->id, [
        'create-all-screen-messages',
        'view-own-screen-messages',
    ]);
    $target = createScreenUser('sm-empty-html-target', $this->departmentA->id);

    $this->actingAs($sender)
        ->post(route('screen-messages.store'), [
            'title' => 'Empty html',
            'body' => '<p><br></p>',
            'display_mode' => ScreenMessageDisplayMode::UserClosable->value,
            'audience_type' => ScreenMessageAudienceType::Users->value,
            'target_ids' => [$target->id],
        ])
        ->assertSessionHasErrors('body');
});
