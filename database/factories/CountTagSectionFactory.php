<?php

namespace Database\Factories;

use App\Models\CountTagLocation;
use App\Models\CountTagSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CountTagSection>
 */
class CountTagSectionFactory extends Factory
{
    protected $model = CountTagSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => CountTagLocation::factory(),
            'code' => strtoupper(fake()->randomLetter()),
            'max_row' => fake()->numberBetween(1, 20),
            'max_column' => fake()->numberBetween(1, 20),
            'legacy_id' => fake()->unique()->numberBetween(1, 99999),
            'meta' => null,
        ];
    }
}
