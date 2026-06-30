<?php

namespace App\Livewire;

use App\Models\Item;
use Illuminate\Support\Str;
use Livewire\Component;

class PrsItem extends Component
{
    public $prsItems = [];

    public $mode = 'cart';

    public string $contextId = '';

    public function mount($existingItems = [], $mode = 'cart', string $contextId = '')
    {
        $this->mode = $mode;
        $this->contextId = $contextId;

        if ($existingItems instanceof \Illuminate\Support\Collection) {
            $existingItems = $existingItems->load(['item.unit']);
        }

        if (! empty($existingItems)) {
            foreach ($existingItems as $existing) {
                $itemModel = $existing->item ?? null;

                $this->prsItems[] = [
                    'prs_item_id' => $existing->id ?? null,
                    'row_id' => $existing->id ?? (string) Str::uuid(),
                    'item_id' => $existing->item_id ?? null,
                    'item_code' => $itemModel->code ?? '',
                    'item_name' => $itemModel->name ?? '',
                    'stock_on_hand' => $itemModel->stock_on_hand ?? 0,
                    'unit' => $itemModel?->unit?->name ?? 'PCS',
                    'quantity' => $existing->quantity ?? 1,
                ];
            }
            $this->updateCartCount();
        } else {
            $this->updateCartCount();
        }
    }

    public function addPrsItem()
    {
        $this->prsItems[] = [
            'prs_item_id' => null,
            'row_id' => (string) Str::uuid(),
            'item_id' => null,
            'item_code' => '',
            'item_name' => '',
            'stock_on_hand' => 0,
            'unit' => 'PCS',
            'quantity' => 1,
        ];

        $this->updateCartCount();
    }

    public function removePrsItem($index)
    {
        unset($this->prsItems[$index]);
        $this->prsItems = array_values($this->prsItems);

        $this->updateCartCount();
    }

    public function incrementQuantity($index)
    {
        if (! isset($this->prsItems[$index])) {
            return;
        }

        $currentQty = (int) ($this->prsItems[$index]['quantity'] ?? 1);
        $this->prsItems[$index]['quantity'] = $currentQty + 1;
        $this->updateCartCount();
    }

    public function decrementQuantity($index)
    {
        if (! isset($this->prsItems[$index])) {
            return;
        }

        $currentQty = (int) ($this->prsItems[$index]['quantity'] ?? 1);
        $this->prsItems[$index]['quantity'] = max(1, $currentQty - 1);
        $this->updateCartCount();
    }

    public function addFromCatalog($itemId, $quantity = 1)
    {
        if (! $itemId || ! is_numeric($itemId)) {
            return;
        }

        $quantity = (int) $quantity;
        if ($quantity < 1) {
            $quantity = 1;
        }

        $item = Item::query()->where('is_active', true)->find($itemId);
        if (! $item) {
            return;
        }

        foreach ($this->prsItems as $index => $row) {
            if (($row['item_id'] ?? null) == $item->id) {
                $this->prsItems[$index]['quantity'] = $quantity;
                $this->updateCartCount();

                return;
            }
        }

        $this->prsItems[] = [
            'prs_item_id' => null,
            'row_id' => (string) Str::uuid(),
            'item_id' => $item->id,
            'item_code' => $item->code,
            'item_name' => $item->name,
            'stock_on_hand' => $item->stock_on_hand ?? 0,
            'unit' => $item->unit?->name ?? 'PCS',
            'quantity' => $quantity,
        ];

        $this->updateCartCount();
    }

    public function updated($property)
    {
        if (preg_match('/prsItems\.(\d+)\.quantity/', $property)) {
            $this->updateCartCount();
        }
    }

    public function render()
    {
        return view('livewire.prs-item');
    }

    private function updateCartCount()
    {
        $itemIds = collect($this->prsItems)
            ->pluck('item_id')
            ->filter(fn ($id) => $id)
            ->values()
            ->all();

        $this->dispatch('prs-cart-count', count: count($this->prsItems), itemIds: $itemIds);
    }
}
