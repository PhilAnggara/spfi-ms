<div>
    @if ($mode === 'cart')
        @if (empty($prsItems))
            <div class="prs-cart-empty text-center">
                <i class="fa-light fa-basket-shopping fa-2x text-muted mb-2"></i>
                <p class="mb-0 text-muted">Cart is empty. Add items from the catalog.</p>
            </div>
        @endif

        @foreach ($prsItems as $prsItem)
            <div wire:loading.class="opacity-25" wire:target="removePrsItem({{ $loop->index }})" class="prs-cart-item" wire:key="prs-item-{{ $prsItem['row_id'] ?? $loop->index }}">
                <div class="prs-cart-item-info">
                    <div class="prs-cart-thumb">
                        <i class="fa-duotone fa-solid fa-box"></i>
                    </div>
                    <div class="prs-cart-text">
                        <div class="fw-semibold">{{ $prsItem['item_name'] ?? '-' }}</div>
                        <small class="text-muted">{{ $prsItem['item_code'] ?? '-' }} · Stock {{ $prsItem['stock_on_hand'] ?? 0 }} {{ $prsItem['unit'] ?? 'PCS' }}</small>
                    </div>
                </div>
                <div class="prs-cart-item-actions">
                    <div class="prs-cart-item-qty">
                        <div class="input-group input-group-sm">
                            <button type="button" class="btn btn-light-secondary" wire:click="decrementQuantity({{ $loop->index }})" wire:loading.attr="disabled">
                                <i class="fa-light fa-minus"></i>
                            </button>
                            <input type="number" min="1" class="form-control" name="prsItems[{{ $loop->index }}][quantity]" wire:model.debounce.300ms="prsItems.{{ $loop->index }}.quantity" required>
                            <button type="button" class="btn btn-light-secondary" wire:click="incrementQuantity({{ $loop->index }})" wire:loading.attr="disabled">
                                <i class="fa-light fa-plus"></i>
                            </button>
                            <span class="input-group-text">{{ $prsItem['unit'] ?? 'PCS' }}</span>
                        </div>
                    </div>
                    <div class="prs-cart-item-remove">
                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removePrsItem({{ $loop->index }})" wire:loading.attr="disabled" wire:target="removePrsItem({{ $loop->index }})">
                            <i class="fa-regular fa-trash"></i>
                            Remove
                        </button>
                    </div>
                </div>
                <input type="hidden" name="prsItems[{{ $loop->index }}][item_id]" value="{{ is_array($prsItem['item_id'] ?? '') ? '' : ($prsItem['item_id'] ?? '') }}">
            </div>
        @endforeach
    @elseif ($mode === 'quantity-only')
        @foreach ($prsItems as $prsItem)
            <div class="card shadow mt-2" wire:key="prs-qty-item-{{ $prsItem['row_id'] ?? $loop->index }}">
                <div class="card-content">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label mb-1">Item Code</label>
                                <div class="form-control bg-light">{{ $prsItem['item_code'] ?? '-' }}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-1">Item Name</label>
                                <div class="form-control bg-light">{{ $prsItem['item_name'] ?? '-' }}</div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1">Stock on Hand</label>
                                <div class="form-control bg-light">{{ $prsItem['stock_on_hand'] ?? 0 }} {{ $prsItem['unit'] ?? 'PCS' }}</div>
                            </div>
                            <div class="col-md-2">
                                <label for="quantity-only-{{ $contextId }}-{{ $loop->index }}" class="form-label mb-1">Quantity</label>
                                <div class="input-group">
                                    <input type="number" id="quantity-only-{{ $contextId }}-{{ $loop->index }}" class="form-control" name="prsItems[{{ $loop->index }}][quantity]" min="1" wire:model.debounce.500ms="prsItems.{{ $loop->index }}.quantity" required>
                                    <span class="input-group-text">{{ $prsItem['unit'] ?? 'PCS' }}</span>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="prsItems[{{ $loop->index }}][prs_item_id]" value="{{ $prsItem['prs_item_id'] ?? '' }}">
                        <input type="hidden" name="prsItems[{{ $loop->index }}][item_id]" value="{{ $prsItem['item_id'] ?? '' }}">
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
