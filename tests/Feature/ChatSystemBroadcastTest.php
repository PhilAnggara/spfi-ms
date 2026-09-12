<?php

use App\Enums\MessagePersona;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('chat-support-operate', 'web');

    $this->operator = User::factory()->create([
        'name' => 'Ops User',
        'username' => 'opsuser',
    ]);
    $this->operator->givePermissionTo('chat-support-operate');

    $this->department = Department::query()->create([
        'name' => 'Broadcast Dept',
        'code' => 'BCAST',
        'is_active' => true,
    ]);

    $this->alice = User::factory()->create([
        'name' => 'Alice Broadcast',
        'username' => 'alicebroadcast',
        'department_id' => $this->department->id,
    ]);
    $this->bob = User::factory()->create([
        'name' => 'Bob Broadcast',
        'username' => 'bobroadcast',
        'department_id' => $this->department->id,
    ]);
    $this->carol = User::factory()->create([
        'name' => 'Carol Outside',
        'username' => 'caroloutside',
    ]);
});

it('broadcasts SPFI-MS messages to all users', function () {
    Event::fake([MessageSent::class]);

    $this->actingAs($this->operator)
        ->postJson(route('chat.support.broadcast'), [
            'audience' => 'all',
            'body' => 'New feature is live',
        ])
        ->assertCreated()
        ->assertJsonPath('data.sent_count', 4)
        ->assertJsonPath('data.audience', 'all');

    expect(Message::query()->where('persona', MessagePersona::System)->count())->toBe(4)
        ->and(Conversation::query()->where('type', 'support')->count())->toBe(4);

    $aliceThread = Conversation::query()
        ->where('type', 'support')
        ->where('support_user_id', $this->alice->id)
        ->first();

    expect($aliceThread)->not->toBeNull();

    $list = $this->actingAs($this->alice)
        ->getJson(route('chat.conversations.index'))
        ->assertOk()
        ->json('data');

    $support = collect($list)->firstWhere('type', 'support');

    expect($support['peer']['name'])->toBe('SPFI-MS')
        ->and($support['latest_message']['body'])->toBe('New feature is live')
        ->and($support['latest_message']['persona'])->toBe('system');

    Event::assertDispatched(MessageSent::class);
});

it('broadcasts SPFI-MS messages to all users including the operator', function () {
    Event::fake([MessageSent::class]);

    $this->actingAs($this->operator)
        ->postJson(route('chat.support.broadcast'), [
            'audience' => 'users',
            'target_ids' => [$this->operator->id],
            'body' => 'Message to myself as SPFI-MS',
        ])
        ->assertCreated()
        ->assertJsonPath('data.sent_count', 1);

    expect(
        Conversation::query()
            ->where('type', 'support')
            ->where('support_user_id', $this->operator->id)
            ->exists()
    )->toBeTrue()
        ->and(Message::query()->where('persona', MessagePersona::System)->where('body', 'Message to myself as SPFI-MS')->count())->toBe(1);
});

it('broadcasts SPFI-MS messages to selected departments', function () {
    Event::fake([MessageSent::class]);

    $this->actingAs($this->operator)
        ->postJson(route('chat.support.broadcast'), [
            'audience' => 'departments',
            'target_ids' => [$this->department->id],
            'body' => 'Dept only notice',
        ])
        ->assertCreated()
        ->assertJsonPath('data.sent_count', 2);

    expect(
        Conversation::query()
            ->where('type', 'support')
            ->whereIn('support_user_id', [$this->alice->id, $this->bob->id])
            ->count()
    )->toBe(2)
        ->and(
            Conversation::query()
                ->where('type', 'support')
                ->where('support_user_id', $this->carol->id)
                ->exists()
        )->toBeFalse();
});

it('broadcasts SPFI-MS messages to selected users with attachment', function () {
    Event::fake([MessageSent::class]);
    Storage::fake('public');

    $file = UploadedFile::fake()->image('announce.jpg', 120, 120);

    $this->actingAs($this->operator)
        ->post(route('chat.support.broadcast'), [
            'audience' => 'users',
            'target_ids' => [$this->alice->id, $this->carol->id],
            'body' => 'Selected users',
            'attachment' => $file,
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.sent_count', 2);

    $message = Message::query()->where('persona', MessagePersona::System)->first();

    expect($message)->not->toBeNull()
        ->and($message->type->value)->toBe('image')
        ->and($message->attachment_path)->not->toBeNull();

    Storage::disk('public')->assertExists($message->attachment_path);
});

it('forbids broadcast without chat-support-operate permission', function () {
    $this->actingAs($this->alice)
        ->postJson(route('chat.support.broadcast'), [
            'audience' => 'all',
            'body' => 'Nope',
        ])
        ->assertForbidden();
});

it('lists departments for broadcast targeting', function () {
    $this->actingAs($this->operator)
        ->getJson(route('chat.support.departments.index'))
        ->assertOk()
        ->assertJsonFragment([
            'id' => $this->department->id,
            'name' => 'Broadcast Dept',
            'code' => 'BCAST',
        ]);

    $this->actingAs($this->alice)
        ->getJson(route('chat.support.departments.index'))
        ->assertForbidden();
});
