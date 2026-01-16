<?php

namespace App\Livewire;

use Livewire\Component;

class PrsItem extends Component
{
    public $prsItems = [];

    public function mount()
    {
        $this->addPrsItem();
    }

    public function addPrsItem()
    {
        $this->prsItems[] = [
            'item_code'     => '',
            'item_name'     => '',
            'stock_on_hand' => 0,
            'quantity'      => 1,
        ];
    }

    public function removePrsItem($index)
    {
        unset($this->prsItems[$index]);
        $this->prsItems = array_values($this->prsItems);
    }

    public function render()
    {
        return view('livewire.prs-item');
    }
}
