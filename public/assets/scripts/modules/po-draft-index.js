(function () {
    const MAX_ITEMS_PER_PO = 11;

    const toggleItemChecked = (checkbox, checked) => {
        const row = checkbox.closest('tr');
        const checkedInput = row?.querySelector('.item-checked');

        if (!checkedInput) {
            return;
        }

        checkedInput.value = checked ? '1' : '0';
    };

    const getActiveCategory = (form) => {
        const checked = form.querySelector('.item-checkbox:checked');

        return checked?.dataset.accountingCategory || null;
    };

    const getCheckedCount = (form) => {
        return form.querySelectorAll('.item-checkbox:checked').length;
    };

    const syncSelectionRules = (form) => {
        const activeCategory = getActiveCategory(form);

        form.querySelectorAll('.item-checkbox').forEach((checkbox) => {
            const category = checkbox.dataset.accountingCategory || 'non_capex';
            const categoryLocked = activeCategory !== null && category !== activeCategory;

            if (categoryLocked && checkbox.checked) {
                checkbox.checked = false;
                toggleItemChecked(checkbox, false);
            }
        });

        const checkedCount = getCheckedCount(form);

        form.querySelectorAll('.item-checkbox').forEach((checkbox) => {
            const category = checkbox.dataset.accountingCategory || 'non_capex';
            const categoryLocked = activeCategory !== null && category !== activeCategory;
            const maxLocked = !checkbox.checked && checkedCount >= MAX_ITEMS_PER_PO;

            checkbox.disabled = categoryLocked || maxLocked;
        });
    };

    const chooseCategoryForSelectAll = (form) => {
        const activeCategory = getActiveCategory(form);
        if (activeCategory) {
            return activeCategory;
        }

        const firstAvailable = form.querySelector('.item-checkbox');

        return firstAvailable?.dataset.accountingCategory || null;
    };

    function initPoDraftSelection(scope = document) {
        const initializedForms = new Set();

        scope.querySelectorAll('.po-supplier-form').forEach((form) => {
            syncSelectionRules(form);
        });

        scope.querySelectorAll('.item-checkbox').forEach((checkbox) => {
            if (checkbox.dataset.poDraftInitialized === '1') {
                return;
            }

            checkbox.dataset.poDraftInitialized = '1';
            toggleItemChecked(checkbox, checkbox.checked);

            checkbox.addEventListener('change', (event) => {
                const currentCheckbox = event.target;
                const form = currentCheckbox.closest('form');

                if (form && currentCheckbox.checked && getCheckedCount(form) > MAX_ITEMS_PER_PO) {
                    currentCheckbox.checked = false;
                    alert(`A purchase order can contain a maximum of ${MAX_ITEMS_PER_PO} items.`);
                }

                toggleItemChecked(currentCheckbox, currentCheckbox.checked);

                if (form) {
                    syncSelectionRules(form);
                }
            });

            const form = checkbox.closest('form');
            if (form) {
                initializedForms.add(form);
            }
        });

        initializedForms.forEach(syncSelectionRules);

        scope.querySelectorAll('.select-all').forEach((button) => {
            if (button.dataset.poDraftInitialized === '1') {
                return;
            }

            button.dataset.poDraftInitialized = '1';
            button.addEventListener('click', () => {
                const form = button.closest('form');
                if (!form) {
                    return;
                }

                const targetCategory = chooseCategoryForSelectAll(form);
                if (!targetCategory) {
                    return;
                }

                let selectedCount = 0;

                form.querySelectorAll('.item-checkbox').forEach((checkbox) => {
                    const shouldCheck = (checkbox.dataset.accountingCategory || 'non_capex') === targetCategory;
                    const canSelect = shouldCheck && selectedCount < MAX_ITEMS_PER_PO;

                    checkbox.checked = canSelect;
                    toggleItemChecked(checkbox, canSelect);

                    if (canSelect) {
                        selectedCount += 1;
                    }
                });

                syncSelectionRules(form);
            });
        });
    }

    window.initPoDraftSelection = initPoDraftSelection;

    document.addEventListener('DOMContentLoaded', function () {
        initPoDraftSelection(document);
    });
})();
