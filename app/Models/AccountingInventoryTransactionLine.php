<?php

namespace App\Models;

/**
 * In-memory encode line for Accounting Inventory UI (not a DB table).
 */
class AccountingInventoryTransactionLine
{
    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    public ?int $id = null;

    public int $item_id = 0;

    public string $direction = self::DIRECTION_IN;

    public float $quantity = 0.0;

    public ?int $unit_of_measure_id = null;

    public float $unit_cost = 0.0;

    public float $amount = 0.0;

    public ?float $prefill_quantity = null;

    public ?float $prefill_unit_cost = null;

    public ?float $available_qty_snapshot = null;

    public int $sort_order = 0;

    public ?Item $item = null;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function make(array $attributes = []): self
    {
        $line = new self;

        foreach ($attributes as $key => $value) {
            if ($key === 'item') {
                if ($value instanceof Item) {
                    $line->item = $value;
                }

                continue;
            }

            if (property_exists($line, $key)) {
                $line->{$key} = $value;
            }
        }

        return $line;
    }

    public function wasCorrected(): bool
    {
        if ($this->prefill_quantity === null && $this->prefill_unit_cost === null) {
            return false;
        }

        $qtyChanged = $this->prefill_quantity !== null
            && abs((float) $this->quantity - (float) $this->prefill_quantity) >= 0.00001;
        $costChanged = $this->prefill_unit_cost !== null
            && abs((float) $this->unit_cost - (float) $this->prefill_unit_cost) >= 0.0001;

        return $qtyChanged || $costChanged;
    }
}
