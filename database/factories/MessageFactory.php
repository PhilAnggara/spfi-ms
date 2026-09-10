<?php

namespace Database\Factories;

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'type' => MessageType::Text,
            'attachment_path' => null,
            'attachment_original_name' => null,
            'attachment_mime' => null,
            'attachment_size' => null,
        ];
    }

    public function image(): static
    {
        return $this->state(fn (): array => [
            'type' => MessageType::Image,
            'body' => null,
            'attachment_path' => 'chat/example/image.jpg',
            'attachment_original_name' => 'image.jpg',
            'attachment_mime' => 'image/jpeg',
            'attachment_size' => 1024,
        ]);
    }

    public function file(): static
    {
        return $this->state(fn (): array => [
            'type' => MessageType::File,
            'body' => null,
            'attachment_path' => 'chat/example/document.pdf',
            'attachment_original_name' => 'document.pdf',
            'attachment_mime' => 'application/pdf',
            'attachment_size' => 2048,
        ]);
    }
}
