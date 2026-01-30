<div>
    @foreach ($prsItems as $prsItem)
        <div wire:loading.class="opacity-50" wire:target="removePrsItem({{ $loop->index }})" class="card shadow mt-2" wire:key="prs-item-{{ $prsItem['row_id'] ?? $loop->index }}">
            <div class="card-content">
                <div class="card-body position-relative">
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
                                <label for="item-code-{{ $loop->index }}">Item Code</label>
                                <select class="choices form-select prs-item-select" id="item-code-{{ $loop->index }}" data-index="{{ $loop->index }}" required>
                                    <option value="" @selected(!$prsItem['item_id']) disabled>-- Search Item Code --</option>
                                    @foreach ($this->getAvailableItems($loop->index) as $item)
                                        <option value="{{ $item->id }}" @selected($prsItem['item_id'] == $item->id)>{{ $item->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="item-name-{{ $loop->index }}">Item Name</label>
                                <select class="choices form-select prs-item-select" id="item-name-{{ $loop->index }}" data-index="{{ $loop->index }}" required>
                                    <option value="" @selected(!$prsItem['item_id']) disabled>-- Search Item Name --</option>
                                    @foreach ($this->getAvailableItems($loop->index) as $item)
                                        <option value="{{ $item->id }}" @selected($prsItem['item_id'] == $item->id)>{{ $item->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="stock-on-hand-{{ $loop->index }}">Stock on Hand</label>
                                <div class="input-group">
                                    <input type="number" id="stock-on-hand-{{ $loop->index }}" class="form-control" placeholder="Stock on Hand" min="0" wire:model.debounce.500ms="prsItems.{{ $loop->index }}.stock_on_hand" readonly required>
                                    <span class="input-group-text" id="basic-addon2">{{ $prsItem['unit'] ?? 'PCS' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="quantity-{{ $loop->index }}">Quantity</label>
                                <div class="input-group">
                                    <input type="number" id="quantity-{{ $loop->index }}" class="form-control" name="prsItems[{{ $loop->index }}][quantity]" placeholder="Quantity" min="1" wire:model.debounce.500ms="prsItems.{{ $loop->index }}.quantity" required>
                                    <span class="input-group-text" id="basic-addon2">{{ $prsItem['unit'] ?? 'PCS' }}</span>
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
        <button wire:loading.attr="disabled" wire:target="addPrsItem" type="button" class="btn icon icon-left btn-outline-secondary btn-sm" wire:click="addPrsItem">
            <i class="fa-duotone fa-solid fa-layer-plus"></i>
            Add Item
        </button>
    </div>
</div>

<script>
    if (!window.__prsChoicesInit) {
        window.__prsChoicesInit = true;

        const initChoices = () => {
            document.querySelectorAll('.prs-item-select').forEach((el) => {
                // Skip if already initialized with event listener
                if (el.dataset.choicesInitialized) {
                    return;
                }

                if (el.Choices) {
                    el.Choices.destroy();
                }

                const choices = new Choices(el, {
                    allowHTML: true,
                    searchEnabled: true
                });

                el.Choices = choices;

                // Mark as initialized
                el.dataset.choicesInitialized = 'true';

                // Listen for item selection
                el.addEventListener('change', function(e) {
                    const index = this.getAttribute('data-index');
                    const itemId = e.target.value;

                    if (itemId && index !== null) {
                        // Find the closest Livewire component
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

        window.addEventListener('choices:refresh', () => {
            setTimeout(initChoices, 100);
        });

        document.addEventListener('DOMContentLoaded', initChoices);

        // Also init when Livewire finishes updating
        document.addEventListener('livewire:navigated', initChoices);
        Livewire.hook('morph.updated', () => {
            setTimeout(initChoices, 100);
        });

        // Init when modal is shown
        document.addEventListener('shown.bs.modal', () => {
            setTimeout(initChoices, 150);
        });
    }
</script>
