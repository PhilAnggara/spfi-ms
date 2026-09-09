<?php

namespace Database\Factories;

use App\Enums\ScreenMessageAudienceType;
use App\Enums\ScreenMessageDisplayMode;
use App\Models\ScreenMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreenMessage>
 */
class ScreenMessageFactory extends Factory
{
    protected $model = ScreenMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'display_mode' => ScreenMessageDisplayMode::UserClosable,
            'duration_seconds' => 30,
            'audience_type' => ScreenMessageAudienceType::Users,
            'allow_reply' => false,
            'is_active' => true,
        ];
    }

    public function permanent(): static
    {
        return $this->state(fn (): array => [
            'display_mode' => ScreenMessageDisplayMode::Permanent,
            'duration_seconds' => null,
        ]);
    }

    public function autoOnly(int $seconds = 15): static
    {
        return $this->state(fn (): array => [
            'display_mode' => ScreenMessageDisplayMode::AutoOnly,
            'duration_seconds' => $seconds,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
