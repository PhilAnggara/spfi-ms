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

    expect($response)->toHaveCount(1)
        ->and($response[0]['id'])->toBe($mine->id)
        ->and($response[0]['peer']['id'])->toBe($this->bob->id);
});

it('searches users and excludes self and soft-deleted users', function () {
    $deleted = User::factory()->create([
        'name' => 'Deleted User',
        'username' => 'deleteduser',
    ]);
    $deleted->delete();

    $response = $this->actingAs($this->alice)
        ->getJson(route('chat.users.search', ['q' => 'chat']))
        ->assertOk()
        ->json('data');

    $ids = collect($response)->pluck('id')->all();

    expect($ids)->toContain($this->bob->id, $this->carol->id)
        ->and($ids)->not->toContain($this->alice->id, $deleted->id);
});

it('includes the chat widget on authenticated app pages', function () {
    $this->actingAs($this->alice)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="chat-widget"', false)
        ->assertSee('chat-widget.js', false);
});
