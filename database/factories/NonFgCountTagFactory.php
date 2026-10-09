<?php

namespace Database\Factories;

use App\Enums\CountTagCondition;
use App\Models\CountTagLocation;
use App\Models\CountTagSection;
use App\Models\NonFgCountTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NonFgCountTag>
 */
class NonFgCountTagFactory extends Factory
{
    protected $model = NonFgCountTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_id' => fake()->unique()->numberBetween(1, 999999),
            'count_tag_number' => fake()->numerify('CT-#####'),
            'count_tag_date' => fake()->date(),
            'item_id' => null,
            'item_code' => strtoupper(fake()->bothify('??-####')),
            'item_category_id' => null,
            'unit_of_measure_id' => null,
            'uom_code' => 'PCS',
            'location_id' => CountTagLocation::factory(),
            'location_name' => 'BONITO',
            'section_id' => CountTagSection::factory(),
            'section_code' => 'A',
            'row' => fake()->numberBetween(1, 10),
            'col' => fake()->numberBetween(1, 10),
            'level' => fake()->numberBetween(1, 5),
            'size' => null,
            'condition' => fake()->optional()->randomElement(CountTagCondition::cases()),
            'qty' => fake()->randomFloat(2, 0, 100),
            'tran_date' => fake()->date(),
            'group_name' => 'STOREKEEPER',
            'created_by' => null,
            'created_by_name' => 'STOREKEEPER',
            'meta' => null,
        ];
    }
}
