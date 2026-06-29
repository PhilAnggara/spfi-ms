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
    @else
        @foreach ($prsItems as $prsItem)
            <div wire:loading.class="opacity-25" wire:target="removePrsItem({{ $loop->index }})" class="card shadow mt-2" wire:key="prs-item-{{ $prsItem['row_id'] ?? $loop->index }}">
                <div class="card-content">
                    <div class="card-body position-relative">
                        <div class="position-absolute top-0 start-0 translate-middle badge-container">
                            <span class="badge bg-light-secondary">#{{ $loop->index + 1}}</span>
                        </div>
                        @if ($loop->count > 1)
                            <div class="position-absolute top-0 end-0 p-2">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-light d-inline-flex align-items-center"
                                    wire:click="removePrsItem({{ $loop->index }})"
                                    wire:loading.attr="disabled"
                                    wire:target="removePrsItem({{ $loop->index }})"
                                >
                                    <span wire:loading.remove wire:target="removePrsItem({{ $loop->index }})">&times; Remove</span>
                                    <span
                                        wire:loading.class.remove="d-none"
                                        wire:target="removePrsItem({{ $loop->index }})"
                                        class="spinner-border spinner-border-sm d-none"
                                        role="status"
                                        aria-hidden="true">
                                    </span>
                                </button>
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-md-6 col-12">
                                <div class="form-group">
                                    <label for="item-code-{{ $contextId }}-{{ $loop->index }}">Item Code</label>
                                    <select class="choices form-select prs-item-select" id="item-code-{{ $contextId }}-{{ $loop->index }}" data-index="{{ $loop->index }}" data-context-id="{{ $contextId }}" required>
                                        <option value="" @selected(!$prsItem['item_id']) disabled>-- Search Item Code --</option>
                                        @foreach ($this->getAvailableItems($loop->index) as $item)
                                            <option value="{{ $item->id }}" @selected($prsItem['item_id'] == $item->id)>{{ $item->code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 col-12">
                                <div class="form-group">
                                    <label for="item-name-{{ $contextId }}-{{ $loop->index }}">Item Name</label>
                                    <select class="choices form-select prs-item-select" id="item-name-{{ $contextId }}-{{ $loop->index }}" data-index="{{ $loop->index }}" data-context-id="{{ $contextId }}" required>
                                        <option value="" @selected(!$prsItem['item_id']) disabled>-- Search Item Name --</option>
                                        @foreach ($this->getAvailableItems($loop->index) as $item)
                                            <option value="{{ $item->id }}" @selected($prsItem['item_id'] == $item->id)>{{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 col-12">
                                <div class="form-group">
                                    <label for="stock-on-hand-{{ $contextId }}-{{ $loop->index }}">Stock on Hand</label>
                                    <div class="input-group">
                                        <input type="number" id="stock-on-hand-{{ $contextId }}-{{ $loop->index }}" class="form-control" placeholder="Stock on Hand" min="0" wire:model.debounce.500ms="prsItems.{{ $loop->index }}.stock_on_hand" readonly required>
                                        <span class="input-group-text">{{ $prsItem['unit'] ?? 'PCS' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-12">
                                <div class="form-group">
                                    <label for="quantity-{{ $contextId }}-{{ $loop->index }}">Quantity</label>
                                    <div class="input-group">
                                        <input type="number" id="quantity-{{ $contextId }}-{{ $loop->index }}" class="form-control" name="prsItems[{{ $loop->index }}][quantity]" placeholder="Quantity" min="1" wire:model.debounce.500ms="prsItems.{{ $loop->index }}.quantity" required>
                                        <span class="input-group-text">{{ $prsItem['unit'] ?? 'PCS' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <input type="hidden" name="prsItems[{{ $loop->index }}][item_id]" value="{{ is_array($prsItem['item_id'] ?? '') ? '' : ($prsItem['item_id'] ?? '') }}">
        @endforeach

        <div wire:loading.class.remove="d-none" wire:target="addPrsItem" class="card shadow mt-2 w-100 d-none" style="min-height: 165.75px">
            <div class="card-content">
                <div class="card-body d-flex justify-content-center align-items-center" style="min-height: 165.75px;">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Adding...</span>
                    </div>
                    <span class="ms-3 fs-5 fst-italic">Adding new item...</span>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-center">
            <button wire:loading.attr="disabled" wire:target="addPrsItem" type="button" class="btn icon icon-left btn-outline-secondary btn-sm" wire:click="addPrsItem">
                <i class="fa-duotone fa-solid fa-layer-plus"></i>
                Add Item
            </button>
        </div>
    @endif
</div>

@if ($mode === 'form')
<script>
    if (!window.__prsChoicesInit) {
        window.__prsChoicesInit = true;

        const destroyChoicesIn = (root) => {
            root.querySelectorAll('.prs-item-select').forEach((el) => {
                if (el.Choices) {
                    el.Choices.destroy();
                    delete el.Choices;
                }
                delete el.dataset.choicesInitialized;
            });
        };

        const initChoicesIn = (root) => {
            root.querySelectorAll('.prs-item-select').forEach((el) => {
                if (el.dataset.choicesInitialized) {
                    return;
                }

                const choices = new Choices(el, {
                    allowHTML: true,
                    searchEnabled: true,
                });

                el.Choices = choices;
                el.dataset.choicesInitialized = 'true';

                el.addEventListener('change', function () {
                    const index = this.getAttribute('data-index');
                    const itemId = this.value;

                    if (itemId && index !== null) {
                        const livewireElement = this.closest('[wire\\:id]');
                        if (livewireElement) {
                            const componentId = livewireElement.getAttribute('wire:id');
                            const component = Livewire.find(componentId);
                            if (component) {
                                component.call('updateItemSelect', parseInt(index), parseInt(itemId));
                            }
                        }
                    }
                });
            });
        };

        window.addEventListener('choices:refresh', (event) => {
            const contextId = event.detail?.contextId;
            const modal = contextId
                ? document.querySelector(`#edit-modal-${contextId}`)
                : null;

            if (modal) {
                destroyChoicesIn(modal);
                setTimeout(() => initChoicesIn(modal), 100);
            }
        });

        document.addEventListener('shown.bs.modal', (event) => {
            if (event.target?.id?.startsWith('edit-modal-')) {
                setTimeout(() => initChoicesIn(event.target), 150);
            }
        });

        document.addEventListener('hidden.bs.modal', (event) => {
            if (event.target?.id?.startsWith('edit-modal-')) {
                destroyChoicesIn(event.target);
            }
        });
    }
</script>
@endif
