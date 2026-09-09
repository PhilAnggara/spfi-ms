<?php

namespace Database\Factories;

use App\Models\ScreenMessage;
use App\Models\ScreenMessageReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreenMessageReply>
 */
class ScreenMessageReplyFactory extends Factory
{
    protected $model = ScreenMessageReply::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'screen_message_id' => ScreenMessage::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
