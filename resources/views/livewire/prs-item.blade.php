<div>
    @foreach ($prsItems as $prsItem)
        <div class="card shadow mt-4">
            <div class="card-content">
                <div class="card-body position-relative">
                    <div class="position-absolute top-0 end-0 p-2">
                        <button type="button" class="btn btn-sm btn-outline-light" wire:click="removePrsItem({{ $loop->index }})">&times; Remove</button>
                    </div>
                    <div class="row">
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="item-code-{{ $loop->index }}">Item Code</label>
                                <input type="text" id="item-code-{{ $loop->index }}" class="form-control" placeholder="Item Code" wire:model.debounce.500ms="prsItems.{{ $loop->index }}.item_code">
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="item-name-{{ $loop->index }}">Item Name</label>
                                <input type="text" id="item-name-{{ $loop->index }}" class="form-control" placeholder="Item Name" wire:model.debounce.500ms="prsItems.{{ $loop->index }}.item_name">
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="stock-on-hand-{{ $loop->index }}">Stock on Hand</label>
                                <div class="input-group">
                                    <input type="number" id="stock-on-hand-{{ $loop->index }}" class="form-control" placeholder="Stock on Hand" min="0" wire:model.debounce.500ms="prsItems.{{ $loop->index }}.stock_on_hand">
                                    <span class="input-group-text" id="basic-addon2">PCS</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="quantity-{{ $loop->index }}">Quantity</label>
                                <div class="input-group">
                                    <input type="number" id="quantity-{{ $loop->index }}" class="form-control" name="prsItems[{{ $loop->index }}][quantity]" placeholder="Quantity" min="1" wire:model.debounce.500ms="prsItems.{{ $loop->index }}.quantity">
                                    <span class="input-group-text" id="basic-addon2">PCS</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" name="prsItems[{{ $loop->index }}][item_id]" value="{{ rand(1, 3) }}">
    @endforeach

    <div wire:loading class="card shadow mt-4 w-100">
        <div class="card-content">
            <div class="card-body">
                <div class="d-flex justify-content-center align-items-center">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span class="ms-2">Loading...</span>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-center">
        <button type="button" class="btn icon icon-left btn-outline-secondary btn-sm" wire:click="addPrsItem">
            <i class="fa-duotone fa-solid fa-layer-plus"></i>
            Add Item
        </button>
    </div>
</div>
