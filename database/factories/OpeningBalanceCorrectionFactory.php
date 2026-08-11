<?php

namespace Database\Factories;

use App\Models\OpeningBalanceCorrection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningBalanceCorrection>
 */
class OpeningBalanceCorrectionFactory extends Factory
{
    protected $model = OpeningBalanceCorrection::class;

    public function definition(): array
    {
        return [
            'obc_number' => 'OBC'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'period_month' => now()->startOfMonth()->toDateString(),
            'reason' => fake()->sentence(),
            'allow_negative_balance' => false,
            'created_by' => null,
            'updated_by' => null,
            'meta' => null,
        ];
    }
}
