<?php

namespace App\Livewire;

use App\Models\Item;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class PrsItem extends Component
{
    public $prsItems = [];

    public $mode = 'form';

    public string $contextId = '';

    public function mount($existingItems = [], $mode = 'form', string $contextId = '')
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
            if ($this->mode === 'form') {
                $this->addPrsItem();
            } else {
                $this->updateCartCount();
            }
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

        $this->dispatch('choices:refresh', contextId: $this->contextId);
        $this->updateCartCount();
    }

    public function removePrsItem($index)
    {
        unset($this->prsItems[$index]);
        $this->prsItems = array_values($this->prsItems);

        $this->dispatch('choices:refresh', contextId: $this->contextId);
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

        if (preg_match('/prsItems\.(\d+)\.item_id/', $property, $matches)) {
            $index = (int) $matches[1];
            $itemId = $this->prsItems[$index]['item_id'] ?? null;

            if (is_array($itemId)) {
                $itemId = null;
                $this->prsItems[$index]['item_id'] = null;
            }

            if ($itemId && is_numeric($itemId)) {
                $item = Item::query()->find($itemId);
                if ($item) {
                    $this->prsItems[$index]['item_code'] = $item->code;
                    $this->prsItems[$index]['item_name'] = $item->name;
                    $this->prsItems[$index]['stock_on_hand'] = $item->stock_on_hand;
                    $this->prsItems[$index]['unit'] = $item->unit?->name ?? 'PCS';
                }
            }

            $this->dispatch('choices:refresh', contextId: $this->contextId);
        }
    }

    #[On('updateItemSelect')]
    public function updateItemSelect($index, $itemId)
    {
        if (isset($this->prsItems[$index]) && $itemId && is_numeric($itemId)) {
            $item = Item::query()->find($itemId);
            if ($item) {
                $this->prsItems[$index]['item_id'] = $item->id;
                $this->prsItems[$index]['item_code'] = $item->code;
                $this->prsItems[$index]['item_name'] = $item->name;
                $this->prsItems[$index]['stock_on_hand'] = $item->stock_on_hand;
                $this->prsItems[$index]['unit'] = $item->unit?->name ?? 'PCS';
            }
        }

        $this->dispatch('choices:refresh', contextId: $this->contextId);
    }

    public function getAvailableItems($index)
    {
        $selectedItemIds = [];

        foreach ($this->prsItems as $key => $item) {
            if ($key !== $index && isset($item['item_id']) && $item['item_id']) {
                $selectedItemIds[] = (int) $item['item_id'];
            }
        }

        $currentItemId = isset($this->prsItems[$index]['item_id']) && $this->prsItems[$index]['item_id']
            ? (int) $this->prsItems[$index]['item_id']
            : null;

        return Item::query()
            ->where('is_active', true)
            ->where(function ($query) use ($selectedItemIds, $currentItemId) {
                $query->whereNotIn('id', $selectedItemIds);

                if ($currentItemId) {
                    $query->orWhere('id', $currentItemId);
                }
            })
            ->orderBy('code')
            ->get();
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
