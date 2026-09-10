<?php

namespace Database\Factories;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ConversationType::Direct,
            'direct_key' => null,
        ];
    }

    public function directBetween(User $userA, User $userB): static
    {
        return $this->state(fn (): array => [
            'type' => ConversationType::Direct,
            'direct_key' => Conversation::directKeyFor($userA->id, $userB->id),
        ])->afterCreating(function (Conversation $conversation) use ($userA, $userB): void {
            $conversation->participants()->createMany([
                ['user_id' => $userA->id],
                ['user_id' => $userB->id],
            ]);
        });
    }
}
