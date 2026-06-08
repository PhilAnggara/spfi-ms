(function () {
    const toggleItemChecked = (checkbox, checked) => {
        const row = checkbox.closest('tr');
        const checkedInput = row?.querySelector('.item-checked');

        if (!checkedInput) {
            return;
        }

        checkedInput.value = checked ? '1' : '0';
    };

    function initPoDraftSelection(scope = document) {
        scope.querySelectorAll('.item-checkbox').forEach((checkbox) => {
            if (checkbox.dataset.poDraftInitialized === '1') {
                return;
            }

            checkbox.dataset.poDraftInitialized = '1';
            toggleItemChecked(checkbox, checkbox.checked);

            checkbox.addEventListener('change', (event) => {
                toggleItemChecked(event.target, event.target.checked);
            });
        });

        scope.querySelectorAll('.select-all').forEach((button) => {
            if (button.dataset.poDraftInitialized === '1') {
                return;
            }

            button.dataset.poDraftInitialized = '1';
            button.addEventListener('click', () => {
                const form = button.closest('form');

                form?.querySelectorAll('.item-checkbox').forEach((checkbox) => {
                    checkbox.checked = true;
                    toggleItemChecked(checkbox, true);
                });
            });
        });
    }

    window.initPoDraftSelection = initPoDraftSelection;

    document.addEventListener('DOMContentLoaded', function () {
        initPoDraftSelection(document);
    });
})();
