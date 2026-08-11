<?php

namespace Database\Factories;

use App\Models\StockAdjustmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustmentItem>
 */
class StockAdjustmentItemFactory extends Factory
{
    protected $model = StockAdjustmentItem::class;

    public function definition(): array
    {
        $previous = fake()->randomFloat(2, 0, 100);
        $new = fake()->randomFloat(2, 0, 100);

        return [
            'stock_adjustment_id' => 1,
            'item_id' => 1,
            'product_code' => fake()->bothify('ITEM-####'),
            'wh_code' => 'MAIN',
            'previous_balance' => $previous,
            'new_balance' => $new,
            'delta_qty' => round($new - $previous, 5),
            'created_by' => null,
            'updated_by' => null,
            'meta' => null,
        ];
    }
}
