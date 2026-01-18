<?php

namespace App\Livewire;

use App\Models\Item;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\On;

class PrsItem extends Component
{
    public $prsItems = [];

    public function mount()
    {
        // Seed the form with a single empty row
        $this->addPrsItem();
    }

    public function addPrsItem()
    {
        // Each row carries a UUID to keep wire:key stable even after deletions
        $this->prsItems[] = [
            'row_id'        => (string) Str::uuid(),
            'item_id'       => null,
            'item_code'     => '',
            'item_name'     => '',
            'stock_on_hand' => 0,
            'unit'          => 'PCS',
            'quantity'      => 1,
        ];

        // Re-init Choices.js after Livewire DOM changes
        $this->dispatch('choices:refresh');
    }

    public function removePrsItem($index)
    {
        unset($this->prsItems[$index]);
        $this->prsItems = array_values($this->prsItems);

        // Keep dropdowns in sync after a row is removed
        $this->dispatch('choices:refresh');
    }

    public function updated($property)
    {
        // React only when item_id changes on any row
        if (preg_match('/prsItems\.(\d+)\.item_id/', $property, $matches)) {
            $index = (int) $matches[1];
            $itemId = $this->prsItems[$index]['item_id'] ?? null;

            // Guard: Livewire can momentarily send arrays when two selects bind the same model
            if (is_array($itemId)) {
                $itemId = null;
                $this->prsItems[$index]['item_id'] = null;
            }

            if ($itemId && is_numeric($itemId)) {
                $item = Item::query()->find($itemId);
                if ($item) {
                    // Populate the row with the selected item's fields
                    $this->prsItems[$index]['item_code'] = $item->code;
                    $this->prsItems[$index]['item_name'] = $item->name;
                    $this->prsItems[$index]['stock_on_hand'] = $item->stock_on_hand;
                    $this->prsItems[$index]['unit'] = $item->unit;
                }
            }

            // Refresh Choices.js because options may change (due to exclusion)
            $this->dispatch('choices:refresh');
        }
    }

    #[On('updateItemSelect')]
    public function updateItemSelect($index, $itemId)
    {
        // Called from JS when Choices.js selection changes
        if (isset($this->prsItems[$index]) && $itemId && is_numeric($itemId)) {
            $item = Item::query()->find($itemId);
            if ($item) {
                $this->prsItems[$index]['item_id'] = $item->id;
                $this->prsItems[$index]['item_code'] = $item->code;
                $this->prsItems[$index]['item_name'] = $item->name;
                $this->prsItems[$index]['stock_on_hand'] = $item->stock_on_hand;
                $this->prsItems[$index]['unit'] = $item->unit;
            }
        }

        // Trigger UI refresh for other dropdowns
        $this->dispatch('choices:refresh');
    }

    public function getAvailableItems($index)
    {
        $selectedItemIds = [];

        foreach ($this->prsItems as $key => $item) {
            if ($key !== $index && isset($item['item_id']) && $item['item_id']) {
                $selectedItemIds[] = $item['item_id'];
            }
        }

        // Exclude items already chosen in other rows to prevent duplicates
        return Item::query()
            ->whereNotIn('id', $selectedItemIds)
            ->get();
    }

    public function render()
    {
        return view('livewire.prs-item');
    }
}
