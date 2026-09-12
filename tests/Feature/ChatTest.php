<?php

use App\Events\ConversationRead;
use App\Events\MessageDelivered;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->alice = User::factory()->create([
        'name' => 'Alice Chat',
        'username' => 'alicechat',
    ]);
    $this->bob = User::factory()->create([
        'name' => 'Bob Chat',
        'username' => 'bobchat',
    ]);
    $this->carol = User::factory()->create([
        'name' => 'Carol Chat',
        'username' => 'carolchat',
    ]);
});

it('creates a unique direct conversation between two users', function () {
    $first = $this->actingAs($this->alice)
        ->postJson(route('chat.conversations.store'), ['user_id' => $this->bob->id])
        ->assertCreated()
        ->json('data');

    $second = $this->actingAs($this->alice)
        ->postJson(route('chat.conversations.store'), ['user_id' => $this->bob->id])
        ->assertCreated()
        ->json('data');

    expect($first['id'])->toBe($second['id'])
        ->and(Conversation::query()->count())->toBe(1)
        ->and($first['peer']['id'])->toBe($this->bob->id);
});

it('rejects starting a conversation with yourself', function () {
    $this->actingAs($this->alice)
        ->postJson(route('chat.conversations.store'), ['user_id' => $this->alice->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

it('sends a text message and broadcasts MessageSent', function () {
    Event::fake([MessageSent::class]);

    $conversation = Conversation::factory()->directBetween($this->alice, $this->bob)->create();

    $this->actingAs($this->alice)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => 'Hello Bob 👋',
        ])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Hello Bob 👋')
        ->assertJsonPath('data.type', 'text');

    expect(Message::query()->count())->toBe(1);

    Event::assertDispatched(MessageSent::class);
});

it('sends an image attachment', function () {
    Storage::fake('public');

    $conversation = Conversation::factory()->directBetween($this->alice, $this->bob)->create();
    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

    $this->actingAs($this->alice)
        ->post(route('chat.messages.store', $conversation), [
            'body' => 'See this',
            'attachment' => $file,
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'image');

    $message = Message::query()->first();
    expect($message)->not->toBeNull()
        ->and($message->attachment_path)->not->toBeNull();

    Storage::disk('public')->assertExists($message->attachment_path);
});

it('validates empty messages without attachments', function () {
    $conversation = Conversation::factory()->directBetween($this->alice, $this->bob)->create();

    $this->actingAs($this->alice)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => '   ',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

it('forbids non-participants from reading or sending messages', function () {
    $conversation = Conversation::factory()->directBetween($this->alice, $this->bob)->create();

    $this->actingAs($this->carol)
        ->getJson(route('chat.messages.index', $conversation))
        ->assertForbidden();

    $this->actingAs($this->carol)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => 'Intruder',
        ])
        ->assertForbidden();
});

it('increments unread count and clears it after read', function () {
    Event::fake([MessageSent::class, ConversationRead::class, MessageDelivered::class]);

    $conversation = Conversation::factory()->directBetween($this->alice, $this->bob)->create();

    $this->actingAs($this->alice)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => 'Unread for Bob',
        ])
        ->assertCreated();

    $this->actingAs($this->bob)
        ->getJson(route('chat.unread-count'))
        ->assertOk()
        ->assertJsonPath('count', 1);

    $this->actingAs($this->bob)
        ->postJson(route('chat.read', $conversation))
        ->assertOk();

    $this->actingAs($this->bob)
        ->getJson(route('chat.unread-count'))
        ->assertOk()
        ->assertJsonPath('count', 0);

    Event::assertDispatched(ConversationRead::class);
});

it('lists recent unread messages for catch-up toasts', function () {
    Event::fake([MessageSent::class, ConversationRead::class, MessageDelivered::class]);

    $conversation = Conversation::factory()->directBetween($this->alice, $this->bob)->create();

    $this->actingAs($this->alice)
        ->postJson(route('chat.messages.store', $conversation), ['body' => 'One'])
        ->assertCreated();
    $this->actingAs($this->alice)
        ->postJson(route('chat.messages.store', $conversation), ['body' => 'Two'])
        ->assertCreated();
    $this->actingAs($this->bob)
        ->postJson(route('chat.messages.store', $conversation), ['body' => 'Own reply'])
        ->assertCreated();

    $unread = $this->actingAs($this->bob)
        ->getJson(route('chat.unread-messages'))
        ->assertOk()
        ->json('data');

    expect($unread)->toHaveCount(2)
        ->and(collect($unread)->pluck('body')->all())->toEqual(['Two', 'One'])
        ->and(collect($unread)->every(fn ($message) => (int) $message['user_id'] === (int) $this->alice->id))->toBeTrue();

    $this->actingAs($this->bob)
        ->postJson(route('chat.read', $conversation))
        ->assertOk();

    $this->actingAs($this->bob)
        ->getJson(route('chat.unread-messages'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('exposes viewer_last_read_at and updates it after read', function () {
    Event::fake([MessageSent::class, ConversationRead::class, MessageDelivered::class]);

    $conversation = Conversation::factory()->directBetween($this->alice, $this->bob)->create();

    $this->actingAs($this->alice)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => '*hello* _world_',
        ])
        ->assertCreated()
        ->assertJsonPath('data.body', '*hello* _world_');

    $before = collect($this->actingAs($this->bob)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data'))->firstWhere('type', 'direct');

    expect($before['viewer_last_read_at'])->toBeNull()
        ->and($before['unread_count'])->toBe(1);

    $this->actingAs($this->bob)
        ->postJson(route('chat.read', $conversation))
        ->assertOk();

    $after = collect($this->actingAs($this->bob)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data'))->firstWhere('type', 'direct');

    expect($after['viewer_last_read_at'])->not->toBeNull()
        ->and($after['unread_count'])->toBe(0);
});

it('marks delivered and broadcasts MessageDelivered', function () {
    Event::fake([MessageDelivered::class]);

    $conversation = Conversation::factory()->directBetween($this->alice, $this->bob)->create();

    $this->actingAs($this->bob)
        ->postJson(route('chat.delivered', $conversation))
        ->assertOk()
        ->assertJsonStructure(['data' => ['conversation_id', 'last_delivered_at']]);

    Event::assertDispatched(MessageDelivered::class);
});

it('lists conversations for the authenticated user only', function () {
    $mine = Conversation::factory()->directBetween($this->alice, $this->bob)->create();
    Conversation::factory()->directBetween($this->bob, $this->carol)->create();

    Message::factory()->create([
        'conversation_id' => $mine->id,
        'user_id' => $this->bob->id,
        'body' => 'Hi Alice',
    ]);

    $response = $this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data');

    $direct = collect($response)->firstWhere('type', 'direct');
    $support = collect($response)->firstWhere('type', 'support');

    expect($response)->toHaveCount(2)
        ->and($direct['id'])->toBe($mine->id)
        ->and($direct['peer']['id'])->toBe($this->bob->id)
        ->and($direct)->toHaveKey('viewer_last_read_at')
        ->and($support['peer']['name'])->toBe('SPFI-MS')
        ->and($support['peer']['is_official'])->toBeTrue();
});

it('hides empty conversations from the list until a message exists', function () {
    Conversation::factory()->directBetween($this->alice, $this->bob)->create();

    $aliceList = $this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data');

    $bobList = $this->actingAs($this->bob)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data');

    expect($aliceList)->toHaveCount(1)
        ->and($aliceList[0]['type'])->toBe('support')
        ->and($bobList)->toHaveCount(1)
        ->and($bobList[0]['type'])->toBe('support');
});

it('orders personal chat list by latest message activity not by support pin', function () {
    $direct = Conversation::factory()->directBetween($this->alice, $this->bob)->create();

    Message::factory()->create([
        'conversation_id' => $direct->id,
        'user_id' => $this->bob->id,
        'body' => 'Recent personal chat',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $list = $this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data');

    expect($list[0]['type'])->toBe('direct')
        ->and($list[0]['id'])->toBe($direct->id)
        ->and(collect($list)->firstWhere('type', 'support'))->not->toBeNull();
});

it('creates a conversation only when the first direct message is sent', function () {
    Event::fake([MessageSent::class]);

    expect(Conversation::query()->count())->toBe(0);

    $payload = $this->actingAs($this->alice)
        ->postJson(route('chat.direct-messages.store'), [
            'user_id' => $this->bob->id,
            'body' => 'First hello',
        ])
        ->assertCreated()
        ->json('data');

    expect(Conversation::query()->count())->toBe(1)
        ->and($payload['conversation']['peer']['id'])->toBe($this->bob->id)
        ->and($payload['message']['body'])->toBe('First hello');

    $aliceList = $this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data');

    $bobList = $this->actingAs($this->bob)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data');

    expect(collect($aliceList)->where('type', 'direct'))->toHaveCount(1)
        ->and(collect($bobList)->where('type', 'direct'))->toHaveCount(1)
        ->and(collect($aliceList)->where('type', 'support'))->toHaveCount(1)
        ->and(collect($bobList)->where('type', 'support'))->toHaveCount(1);
});

it('searches users and excludes self soft-deleted and existing chat peers', function () {
    $deleted = User::factory()->create([
        'name' => 'Deleted User',
        'username' => 'deleteduser',
    ]);
    $deleted->delete();

    $existing = Conversation::factory()->directBetween($this->alice, $this->bob)->create();
    Message::factory()->create([
        'conversation_id' => $existing->id,
        'user_id' => $this->alice->id,
        'body' => 'Already chatting',
    ]);

    $response = $this->actingAs($this->alice)
        ->getJson(route('chat.users.search', ['q' => 'chat']))
        ->assertOk()
        ->json('data');

    $ids = collect($response)->pluck('id')->all();

    expect($ids)->toContain($this->carol->id)
        ->and($ids)->not->toContain($this->alice->id, $this->bob->id, $deleted->id);
});

it('browses contacts when search query is empty', function () {
    $deleted = User::factory()->create([
        'name' => 'Gone User',
        'username' => 'goneuser',
    ]);
    $deleted->delete();

    $existing = Conversation::factory()->directBetween($this->alice, $this->bob)->create();
    Message::factory()->create([
        'conversation_id' => $existing->id,
        'user_id' => $this->alice->id,
        'body' => 'Already chatting',
    ]);

    $response = $this->actingAs($this->alice)
        ->getJson(route('chat.users.search', ['q' => '']))
        ->assertOk()
        ->json('data');

    $ids = collect($response)->pluck('id')->all();

    expect($ids)->toContain($this->carol->id)
        ->and($ids)->not->toContain($this->alice->id, $this->bob->id, $deleted->id)
        ->and($response[0])->toHaveKeys(['id', 'name', 'username', 'email', 'role', 'department', 'is_online']);
});

it('broadcasts typing to the peer user channel', function () {
    Event::fake([\App\Events\ChatTyping::class]);

    $this->actingAs($this->alice)
        ->postJson(route('chat.typing'), [
            'user_id' => $this->bob->id,
            'typing' => true,
        ])
        ->assertOk();

    Event::assertDispatched(\App\Events\ChatTyping::class, function (\App\Events\ChatTyping $event): bool {
        return $event->from->is($this->alice)
            && $event->toUserId === $this->bob->id
            && $event->typing === true;
    });
});

it('marks delivered and read statuses on message payloads', function () {
    Event::fake([MessageSent::class, MessageDelivered::class, ConversationRead::class]);

    $conversation = Conversation::factory()->directBetween($this->alice, $this->bob)->create();

    $this->actingAs($this->alice)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => 'Status check',
        ])
        ->assertCreated();

    $this->actingAs($this->bob)
        ->postJson(route('chat.delivered', $conversation))
        ->assertOk();

    $delivered = $this->actingAs($this->alice)
        ->getJson(route('chat.messages.index', $conversation))
        ->assertOk()
        ->json('data.0');

    expect($delivered['status'])->toBe('delivered');
    expect($delivered['delivered_at'])->not->toBeNull();

    $this->actingAs($this->bob)
        ->postJson(route('chat.read', $conversation))
        ->assertOk();

    $read = $this->actingAs($this->alice)
        ->getJson(route('chat.messages.index', $conversation))
        ->assertOk()
        ->json('data.0');

    expect($read['status'])->toBe('read')
        ->and($read['read_at'])->not->toBeNull()
        ->and($read['delivered_at'])->not->toBeNull();
});

it('broadcasts MessageSent on conversation and recipient user channels', function () {
    Event::fake([MessageSent::class]);

    $conversation = Conversation::factory()->directBetween($this->alice, $this->bob)->create();

    $this->actingAs($this->alice)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => 'Channel check',
        ])
        ->assertCreated();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($conversation): bool {
        $channelNames = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

        return in_array('private-conversation.'.$conversation->id, $channelNames, true)
            && in_array('private-App.Models.User.'.$this->bob->id, $channelNames, true)
            && ! in_array('private-App.Models.User.'.$this->alice->id, $channelNames, true);
    });
});

it('includes peer profile fields on conversations and search results', function () {
    $department = \App\Models\Department::query()->create([
        'name' => 'Finance Dept',
        'code' => 'FIN-CHAT-TEST',
        'is_active' => true,
    ]);

    $this->bob->forceFill([
        'email' => 'bob.chat@example.com',
        'role' => 'Analyst',
        'department_id' => $department->id,
    ])->save();

    $conversation = Conversation::factory()->directBetween($this->alice, $this->bob)->create();
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->bob->id,
        'body' => 'Profile fields',
    ]);

    $listPeer = collect($this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data'))
        ->firstWhere('type', 'direct')['peer'] ?? null;

    expect($listPeer)
        ->toHaveKeys(['id', 'name', 'username', 'email', 'role', 'department', 'is_online', 'last_seen_at'])
        ->and($listPeer['email'])->toBe('bob.chat@example.com')
        ->and($listPeer['role'])->toBe('Analyst')
        ->and($listPeer['department'])->toBe('Finance Dept');

    $searchPeer = $this->actingAs($this->alice)
        ->getJson(route('chat.users.search', ['q' => 'Carol']))
        ->assertOk()
        ->json('data.0');

    expect($searchPeer)
        ->toHaveKeys(['id', 'name', 'username', 'email', 'role', 'department', 'is_online', 'last_seen_at'])
        ->and($searchPeer['id'])->toBe($this->carol->id);
});

it('includes the chat widget on authenticated app pages', function () {
    $this->actingAs($this->alice)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="chat-widget"', false)
        ->assertSee('chat-widget.js', false)
        ->assertDontSee('id="chat-new-btn"', false)
        ->assertSee('Search or start chat', false)
        ->assertSee('id="chat-user-profile"', false)
        ->assertSee('id="chat-profile-status"', false)
        ->assertSee('id="chat-dropzone"', false)
        ->assertSee('id="chat-toast-host"', false);
});

it('boots laravel echo from config values on authenticated pages', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-echo-reverb-key',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 8081,
        'broadcasting.connections.reverb.options.scheme' => 'http',
    ]);

    $html = $this->actingAs($this->alice)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('__spfiEchoBooted', false)
        ->assertSee('"reverb"', false)
        ->assertSee('test-echo-reverb-key', false)
        ->assertDontSee("env('BROADCAST_CONNECTION'", false)
        ->assertDontSee("env('REVERB_APP_KEY'", false)
        ->getContent();

    expect($html)
        ->toContain('const wsPort = broadcaster === \'reverb\'')
        ->toMatch('/\?\s*8081/')
        ->toMatch('/forceTLS = broadcaster === \'reverb\'[\s\S]*?\?\s*false/');
});

it('creates distinct SPFI-MS threads for multiple users without colliding on direct_key', function () {
    $first = $this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data');

    $second = $this->actingAs($this->bob)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data');

    $aliceSupport = collect($first)->firstWhere('type', 'support');
    $bobSupport = collect($second)->firstWhere('type', 'support');

    expect($aliceSupport)->not->toBeNull()
        ->and($bobSupport)->not->toBeNull()
        ->and($aliceSupport['id'])->not->toBe($bobSupport['id'])
        ->and(Conversation::query()->where('type', 'support')->count())->toBe(2)
        ->and(Conversation::query()->where('type', 'support')->whereNotNull('direct_key')->count())->toBe(2);
});

it('ensures a unique SPFI-MS support thread per user', function () {
    $first = $this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data');

    $second = $this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data');

    $support = collect($first)->firstWhere('type', 'support');

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(1)
        ->and($support['peer']['name'])->toBe('SPFI-MS')
        ->and($support['peer']['is_official'])->toBeTrue()
        ->and($support['peer']['avatar_url'])->toContain('system_profile.png')
        ->and(\App\Models\Conversation::query()->where('type', 'support')->count())->toBe(1);
});

it('lets end users message SPFI-MS and operators reply as system persona', function () {
    Event::fake([MessageSent::class, ConversationRead::class]);

    \Spatie\Permission\Models\Permission::findOrCreate('chat-support-operate', 'web');
    $this->carol->givePermissionTo('chat-support-operate');

    $thread = collect($this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data'))->firstWhere('type', 'support');

    $conversation = Conversation::query()->findOrFail($thread['id']);

    $this->actingAs($this->alice)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => 'Need help please',
        ])
        ->assertCreated()
        ->assertJsonPath('data.persona', 'user')
        ->assertJsonPath('data.body', 'Need help please')
        ->assertJsonPath('data.status', 'delivered');

    $listAfterSend = collect($this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data'))->firstWhere('type', 'support');

    expect($listAfterSend['latest_message']['status'])->toBe('delivered');

    // Older operator read timestamp must not downgrade a newer user message to "sent".
    $conversation->forceFill([
        'support_last_read_at' => now()->subMinute(),
    ])->save();

    $this->actingAs($this->alice)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => 'Follow up after older read marker',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'delivered');

    $listAfterFollowUp = collect($this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data'))->firstWhere('type', 'support');

    expect($listAfterFollowUp['latest_message']['body'])->toBe('Follow up after older read marker')
        ->and($listAfterFollowUp['latest_message']['status'])->toBe('delivered');

    $this->actingAs($this->carol)
        ->getJson(route('chat.support.conversations.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $conversation->id)
        ->assertJsonPath('data.0.peer.id', $this->alice->id);

    $this->actingAs($this->carol)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => 'We are on it',
        ])
        ->assertCreated()
        ->assertJsonPath('data.persona', 'system')
        ->assertJsonPath('data.user_id', $this->carol->id);

    $this->actingAs($this->bob)
        ->getJson(route('chat.support.conversations.index'))
        ->assertForbidden();

    $this->actingAs($this->bob)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => 'Intruder',
        ])
        ->assertForbidden();

    $this->actingAs($this->carol)
        ->postJson(route('chat.read', $conversation))
        ->assertOk();

    expect($conversation->fresh()->support_last_read_at)->not->toBeNull()
        ->and($conversation->fresh()->assigned_to)->toBe($this->carol->id);

    $listAfterRead = collect($this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data'))->firstWhere('type', 'support');

    // Latest is still the system reply from Carol for Alice; Alice's own earlier message
    // should be readable as "read" when fetched from messages.
    $aliceMessages = $this->actingAs($this->alice)
        ->getJson(route('chat.messages.index', $conversation))
        ->assertOk()
        ->json('data');

    $aliceOwn = collect($aliceMessages)->firstWhere('body', 'Need help please');
    expect($aliceOwn['status'])->toBe('read');
});

it('lets operators reply as SPFI-MS on their own support thread', function () {
    Event::fake([MessageSent::class, ConversationRead::class]);

    \Spatie\Permission\Models\Permission::findOrCreate('chat-support-operate', 'web');
    $this->carol->givePermissionTo('chat-support-operate');

    $thread = collect($this->actingAs($this->carol)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data'))->firstWhere('type', 'support');

    $conversation = Conversation::query()->findOrFail($thread['id']);

    $this->actingAs($this->carol)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => 'I need help myself',
        ])
        ->assertCreated()
        ->assertJsonPath('data.persona', 'user');

    $this->actingAs($this->carol)
        ->postJson(route('chat.messages.store', $conversation), [
            'body' => 'Replying as the system',
            'as_system' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.persona', 'system')
        ->assertJsonPath('data.user_id', $this->carol->id);

    $messages = $this->actingAs($this->carol)
        ->getJson(route('chat.messages.index', $conversation).'?as_operator=1')
        ->assertOk()
        ->json('data');

    expect(collect($messages)->pluck('persona')->all())->toContain('user', 'system')
        ->and(collect($messages)->firstWhere('persona', 'system')['body'])->toBe('Replying as the system');
});

it('lets operators open a support chat from a contact', function () {
    \Spatie\Permission\Models\Permission::findOrCreate('chat-support-operate', 'web');
    $this->carol->givePermissionTo('chat-support-operate');

    $this->actingAs($this->carol)
        ->postJson(route('chat.support.conversations.store'), [
            'user_id' => $this->alice->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'support')
        ->assertJsonPath('data.peer.id', $this->alice->id)
        ->assertJsonPath('data.support_user_id', $this->alice->id);

    expect(\App\Models\Conversation::query()
        ->where('type', 'support')
        ->where('support_user_id', $this->alice->id)
        ->count())->toBe(1);
});

it('updates support status for operators', function () {
    \Spatie\Permission\Models\Permission::findOrCreate('chat-support-operate', 'web');
    $this->carol->givePermissionTo('chat-support-operate');

    $conversation = Conversation::factory()->supportFor($this->alice)->create();
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->alice->id,
        'persona' => \App\Enums\MessagePersona::User,
        'body' => 'Hello system',
    ]);

    $this->actingAs($this->carol)
        ->patchJson(route('chat.support.status', $conversation), [
            'status' => 'resolved',
        ])
        ->assertOk()
        ->assertJsonPath('data.support_status', 'resolved');

    expect($conversation->fresh()->support_status->value)->toBe('resolved');
});
