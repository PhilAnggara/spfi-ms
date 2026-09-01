(function () {
    const NUDGE_STEP = 0.5;
    const MIN_MEASURED = 0;
    const MAX_MEASURED = 120;
    const previewRequests = new WeakMap();

    function parseNumber(value, fallback = 0) {
        const parsed = parseFloat(String(value ?? '').replace(',', '.'));
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function round1(value) {
        return Math.round(value * 10) / 10;
    }

    function clampMeasured(value) {
        return Math.max(MIN_MEASURED, Math.min(MAX_MEASURED, round1(value)));
    }

    function isAdminPanel(root) {
        return root.hasAttribute('data-print-calibration-admin');
    }

    function getPanelElements(root) {
        return {
            measuredX: root.querySelector('.print-calibration-measured-x'),
            measuredY: root.querySelector('.print-calibration-measured-y'),
            profileSelect: root.querySelector('.print-calibration-profile'),
            previewButton: root.querySelector('.print-calibration-preview'),
            previewFrame: root.querySelector('.print-calibration-preview-frame'),
            previewStatus: root.querySelector('.print-calibration-preview-status'),
        };
    }

    function buildPreviewUrl(root, format) {
        const previewBaseUrl = root.getAttribute('data-preview-base-url');
        if (!previewBaseUrl) {
            return null;
        }

        const { measuredX, measuredY, profileSelect } = getPanelElements(root);
        const url = new URL(previewBaseUrl, window.location.origin);

        const documentType = root.getAttribute('data-document-type');
        if (documentType) {
            url.searchParams.set('document_type', documentType);
        }

        if (format) {
            url.searchParams.set('format', format);
        }

        if (measuredX?.value !== '') {
            url.searchParams.set('measured_anchor_x_mm', measuredX.value);
        }
        if (measuredY?.value !== '') {
            url.searchParams.set('measured_anchor_y_mm', measuredY.value);
        }
        if (profileSelect?.value) {
            url.searchParams.set('calibration_profile_id', profileSelect.value);
        }

        url.searchParams.set('_', String(Date.now()));

        return url.toString();
    }

    function setPreviewStatus(root, message) {
        const { previewStatus } = getPanelElements(root);
        if (previewStatus) {
            previewStatus.textContent = message;
        }
    }

    function openPreview(root) {
        const url = buildPreviewUrl(root, 'pdf');
        if (!url) {
            return;
        }

        window.open(url, '_blank', 'noopener,noreferrer');
    }

    async function refreshLivePreview(root) {
        if (!isAdminPanel(root)) {
            return;
        }

        const { previewFrame } = getPanelElements(root);
        const url = buildPreviewUrl(root);

        if (!previewFrame || !url) {
            return;
        }

        const previousController = previewRequests.get(root);
        if (previousController) {
            previousController.abort();
        }

        const controller = new AbortController();
        previewRequests.set(root, controller);

        previewFrame.classList.remove('d-none');
        setPreviewStatus(root, 'Updating…');

        try {
            const response = await fetch(url, {
                signal: controller.signal,
                headers: {
                    Accept: 'text/html',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Preview request failed (${response.status})`);
            }

            const html = await response.text();

            if (previewRequests.get(root) !== controller) {
                return;
            }

            previewFrame.srcdoc = html;
            setPreviewStatus(root, 'Live preview');
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            setPreviewStatus(root, 'Preview failed. Adjust values and try again.');
        } finally {
            if (previewRequests.get(root) === controller) {
                previewRequests.delete(root);
            }
        }
    }

    function persistSettings(root) {
        const storageKey = root.getAttribute('data-storage-key');
        if (!storageKey) {
            return;
        }

        const { measuredX, measuredY, profileSelect } = getPanelElements(root);

        try {
            localStorage.setItem(storageKey, JSON.stringify({
                measured_anchor_x_mm: measuredX?.value ?? '',
                measured_anchor_y_mm: measuredY?.value ?? '',
                calibration_profile_id: profileSelect?.value ?? '',
            }));
        } catch (error) {
            // Ignore storage failures (private mode, quota, etc.).
        }
    }

    function getSelectedProfileMeasured(root) {
        const { profileSelect } = getPanelElements(root);
        if (!profileSelect || profileSelect.value === '') {
            return null;
        }

        const selected = profileSelect.options[profileSelect.selectedIndex];
        if (!selected || !selected.value) {
            return null;
        }

        return {
            x: parseNumber(selected.getAttribute('data-measured-x')),
            y: parseNumber(selected.getAttribute('data-measured-y')),
        };
    }

    function measuredValuesMatchProfile(root) {
        const { measuredX, measuredY } = getPanelElements(root);
        const profileMeasured = getSelectedProfileMeasured(root);

        if (!profileMeasured || !measuredX || !measuredY) {
            return false;
        }

        return (
            round1(parseNumber(measuredX.value)) === round1(profileMeasured.x)
            && round1(parseNumber(measuredY.value)) === round1(profileMeasured.y)
        );
    }

    function syncProfileSelectionWithMeasuredValues(root, persist = false) {
        const { profileSelect } = getPanelElements(root);
        if (!profileSelect || profileSelect.value === '') {
            return;
        }

        if (measuredValuesMatchProfile(root)) {
            return;
        }

        profileSelect.value = '';

        if (persist) {
            persistSettings(root);
        }
    }

    function restoreSettings(root) {
        const storageKey = root.getAttribute('data-storage-key');
        if (!storageKey) {
            return;
        }

        let saved = null;
        try {
            saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
        } catch (error) {
            saved = null;
        }

        if (!saved || typeof saved !== 'object') {
            return;
        }

        const { measuredX, measuredY, profileSelect } = getPanelElements(root);

        if (saved.measured_anchor_x_mm !== undefined && measuredX) {
            measuredX.value = saved.measured_anchor_x_mm;
        }
        if (saved.measured_anchor_y_mm !== undefined && measuredY) {
            measuredY.value = saved.measured_anchor_y_mm;
        }

        if (saved.calibration_profile_id && profileSelect) {
            profileSelect.value = String(saved.calibration_profile_id);
        }
    }

    function switchToManualMeasurement(root) {
        const { profileSelect } = getPanelElements(root);
        if (!profileSelect || profileSelect.value === '') {
            return;
        }

        profileSelect.value = '';
    }

    function onMeasuredValuesEdited(root) {
        switchToManualMeasurement(root);
        persistSettings(root);
        refreshLivePreview(root);
    }

    function applyProfileSelection(root, persist = true) {
        const { profileSelect, measuredX, measuredY } = getPanelElements(root);
        if (!profileSelect || !measuredX || !measuredY) {
            return;
        }

        const selected = profileSelect.options[profileSelect.selectedIndex];
        if (!selected || !selected.value) {
            if (persist) {
                persistSettings(root);
            }

            refreshLivePreview(root);

            return;
        }

        measuredX.value = selected.getAttribute('data-measured-x') || measuredX.value;
        measuredY.value = selected.getAttribute('data-measured-y') || measuredY.value;

        if (persist) {
            persistSettings(root);
        }

        refreshLivePreview(root);
    }

    function applyNudge(root, axis, delta) {
        const { measuredX, measuredY } = getPanelElements(root);
        const step = parseNumber(delta, NUDGE_STEP);
        let updatedInput = null;

        if (axis === 'y' && measuredY) {
            measuredY.value = String(clampMeasured(parseNumber(measuredY.value) + step));
            updatedInput = measuredY;
        } else if (measuredX) {
            measuredX.value = String(clampMeasured(parseNumber(measuredX.value) + step));
            updatedInput = measuredX;
        }

        if (updatedInput) {
            onMeasuredValuesEdited(root);
        }
    }

    function bindPanelActions(root) {
        root.querySelectorAll('.print-calibration-nudge').forEach((button) => {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                applyNudge(
                    root,
                    button.getAttribute('data-axis'),
                    parseNumber(button.getAttribute('data-delta')),
                );
            });
        });

        const previewButton = root.querySelector('.print-calibration-preview');
        if (previewButton) {
            previewButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openPreview(root);
            });
        }
    }

    function initPanel(root) {
        restoreSettings(root);
        syncProfileSelectionWithMeasuredValues(root, true);
        bindPanelActions(root);

        const collapseEl = root.querySelector('.collapse');
        const toggleButton = root.querySelector('.print-calibration-toggle');
        const toggleLabel = root.querySelector('.print-calibration-toggle-label');

        if (collapseEl && toggleLabel) {
            collapseEl.addEventListener('show.bs.collapse', function () {
                toggleLabel.textContent = 'Hide options';
                if (toggleButton) {
                    toggleButton.setAttribute('aria-expanded', 'true');
                }
            });
            collapseEl.addEventListener('hide.bs.collapse', function () {
                toggleLabel.textContent = 'Show options';
                if (toggleButton) {
                    toggleButton.setAttribute('aria-expanded', 'false');
                }
            });
        }

        root.addEventListener('change', function (event) {
            if (event.target.classList.contains('print-calibration-profile')) {
                applyProfileSelection(root);
            }
        });

        root.addEventListener('input', function (event) {
            if (
                event.target.classList.contains('print-calibration-measured-x')
                || event.target.classList.contains('print-calibration-measured-y')
            ) {
                onMeasuredValuesEdited(root);
            }
        });

        if (isAdminPanel(root)) {
            refreshLivePreview(root);
        }
    }

    function initAdminPage() {
        const adminRoot = document.querySelector('[data-print-calibration-admin]');
        if (!adminRoot) {
            return;
        }

        adminRoot.setAttribute('data-preview-base-url', adminRoot.getAttribute('data-preview-url') || '');
        adminRoot.setAttribute('data-document-type', adminRoot.getAttribute('data-document-type') || 'RR');
        initPanel(adminRoot);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-print-calibration-panel]').forEach(initPanel);
        initAdminPage();
    });
})();
