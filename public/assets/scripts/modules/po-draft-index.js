(function () {
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

    const syncCategoryLock = (form) => {
        const activeCategory = getActiveCategory(form);

        form.querySelectorAll('.item-checkbox').forEach((checkbox) => {
            const category = checkbox.dataset.accountingCategory || 'non_capex';
            const shouldDisable = activeCategory !== null && category !== activeCategory;

            checkbox.disabled = shouldDisable;

            if (shouldDisable && checkbox.checked) {
                checkbox.checked = false;
                toggleItemChecked(checkbox, false);
            }
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
            syncCategoryLock(form);
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

                toggleItemChecked(currentCheckbox, currentCheckbox.checked);

                if (form) {
                    syncCategoryLock(form);
                }
            });

            const form = checkbox.closest('form');
            if (form) {
                initializedForms.add(form);
            }
        });

        initializedForms.forEach(syncCategoryLock);

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

                form.querySelectorAll('.item-checkbox').forEach((checkbox) => {
                    const shouldCheck = (checkbox.dataset.accountingCategory || 'non_capex') === targetCategory;
                    checkbox.checked = shouldCheck;
                    toggleItemChecked(checkbox, shouldCheck);
                });

                syncCategoryLock(form);
            });
        });
    }

    window.initPoDraftSelection = initPoDraftSelection;

    document.addEventListener('DOMContentLoaded', function () {
        initPoDraftSelection(document);
    });
})();
