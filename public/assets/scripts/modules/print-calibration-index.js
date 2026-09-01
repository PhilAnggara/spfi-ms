(function () {
    function initPrintCalibrationIndex() {
        const root = document.querySelector('[data-print-calibration-index]');
        if (!root) {
            return;
        }

        const panelsContainer = root.querySelector('.print-calibration-tab-panels');
        const tabButtons = root.querySelectorAll('.print-calibration-tab');
        const panels = root.querySelectorAll('.print-calibration-tab-panel');
        const addProfileLink = root.querySelector('.print-calibration-add-profile');
        const addProfileBaseUrl = root.getAttribute('data-add-profile-url') || '';

        function setActiveType(type) {
            tabButtons.forEach(function (button) {
                const isActive = button.getAttribute('data-type') === type;
                button.classList.toggle('active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            panels.forEach(function (panel) {
                panel.classList.toggle('is-active', panel.getAttribute('data-type') === type);
            });

            if (addProfileLink && addProfileBaseUrl) {
                const url = new URL(addProfileBaseUrl, window.location.origin);
                url.searchParams.set('type', type);
                addProfileLink.href = url.toString();
            }

            root.setAttribute('data-active-type', type);

            const nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('type', type);
            window.history.replaceState({ printCalibrationType: type }, '', nextUrl.toString());
        }

        tabButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const type = button.getAttribute('data-type');
                if (!type || type === root.getAttribute('data-active-type')) {
                    return;
                }

                if (panelsContainer) {
                    panelsContainer.classList.add('is-switching');
                }

                setActiveType(type);

                window.setTimeout(function () {
                    panelsContainer?.classList.remove('is-switching');
                }, 180);
            });
        });

        window.addEventListener('popstate', function () {
            const urlType = new URL(window.location.href).searchParams.get('type')?.toUpperCase();
            if (urlType && urlType !== root.getAttribute('data-active-type')) {
                setActiveType(urlType);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initPrintCalibrationIndex);
})();
