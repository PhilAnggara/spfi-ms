document.addEventListener('DOMContentLoaded', function () {
    const toggleItemChecked = (itemIndex, checked) => {
        const checkedInput = document.querySelector(`input[name="items[${itemIndex}][checked]"]`);
        if (checked) {
            checkedInput.value = '1';
        } else {
            checkedInput.value = '0';
        }
    };

    document.querySelectorAll('.item-checkbox').forEach((checkbox) => {
        const itemIndex = checkbox.dataset.itemIndex;
        toggleItemChecked(itemIndex, checkbox.checked);

        checkbox.addEventListener('change', (event) => {
            const itemIndex = event.target.dataset.itemIndex;
            toggleItemChecked(itemIndex, event.target.checked);
        });
    });

    document.querySelectorAll('.select-all').forEach((button) => {
        button.addEventListener('click', () => {
            const form = button.closest('form');
            form.querySelectorAll('.item-checkbox').forEach((checkbox) => {
                checkbox.checked = true;
                const itemIndex = checkbox.dataset.itemIndex;
                toggleItemChecked(itemIndex, true);
            });
        });
    });
});
