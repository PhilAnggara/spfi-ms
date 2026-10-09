<?php

namespace Database\Factories;

use App\Models\CountTagLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CountTagLocation>
 */
class CountTagLocationFactory extends Factory
{
    protected $model = CountTagLocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => strtoupper(fake()->unique()->lexify('LOC-????')),
            'legacy_id' => fake()->unique()->numberBetween(1, 9999),
            'meta' => null,
        ];
    }
}
