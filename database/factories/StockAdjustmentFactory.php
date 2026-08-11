<?php

namespace Database\Factories;

use App\Models\StockAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    public function definition(): array
    {
        return [
            'sa_number' => 'SA'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'sa_date' => now()->toDateString(),
            'reason' => fake()->sentence(),
            'created_by' => null,
            'updated_by' => null,
            'meta' => null,
        ];
    }
}
