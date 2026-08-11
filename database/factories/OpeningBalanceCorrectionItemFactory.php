<?php

namespace Database\Factories;

use App\Models\OpeningBalanceCorrectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningBalanceCorrectionItem>
 */
class OpeningBalanceCorrectionItemFactory extends Factory
{
    protected $model = OpeningBalanceCorrectionItem::class;

    public function definition(): array
    {
        $previous = fake()->randomFloat(2, 0, 100);
        $new = fake()->randomFloat(2, 0, 100);

        return [
            'opening_balance_correction_id' => 1,
            'item_id' => 1,
            'product_code' => fake()->bothify('ITEM-####'),
            'wh_code' => 'MAIN',
            'previous_beginning' => $previous,
            'new_beginning' => $new,
            'delta_qty' => round($new - $previous, 5),
            'replayed_movements' => 0,
            'created_by' => null,
            'updated_by' => null,
            'meta' => null,
        ];
    }
}
