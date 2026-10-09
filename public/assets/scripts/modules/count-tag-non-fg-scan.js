document.addEventListener('DOMContentLoaded', function () {
    const page = document.getElementById('count-tag-scan-page');
    if (!page) {
        return;
    }

    const lookupUrl = page.dataset.lookupUrl || '';
    const storeUrl = page.dataset.storeUrl || '';
    const locationsUrl = page.dataset.locationsUrl || '';
    const today = page.dataset.today || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const startButton = document.getElementById('count-tag-start-camera');
    const stopButton = document.getElementById('count-tag-stop-camera');
    const cameraStatus = document.getElementById('count-tag-camera-status');
    const cameraPill = document.getElementById('count-tag-camera-pill');
    const manualInput = document.getElementById('count-tag-manual-code');
    const manualLookupButton = document.getElementById('count-tag-manual-lookup');

    const resultEmpty = document.getElementById('count-tag-result-empty');
    const resultLoading = document.getElementById('count-tag-result-loading');
    const resultError = document.getElementById('count-tag-result-error');
    const resultCard = document.getElementById('count-tag-result-card');
    const resultCode = document.getElementById('count-tag-result-code');
    const resultName = document.getElementById('count-tag-result-name');
    const resultMeta = document.getElementById('count-tag-result-meta');

    const entryModalEl = document.getElementById('count-tag-entry-modal');
    const entryForm = document.getElementById('count-tag-entry-form');
    const formError = document.getElementById('count-tag-form-error');
    const saveButton = document.getElementById('count-tag-save-btn');
    const modalCode = document.getElementById('count-tag-modal-code');
    const modalName = document.getElementById('count-tag-modal-name');
    const modalMeta = document.getElementById('count-tag-modal-meta');
    const itemIdInput = document.getElementById('count-tag-item-id');
    const locationSelect = document.getElementById('count-tag-location');
    const sectionSelect = document.getElementById('count-tag-section');
    const sectionHint = document.getElementById('count-tag-section-hint');
    const rowInput = document.getElementById('count-tag-row');
    const colInput = document.getElementById('count-tag-col');
    const levelInput = document.getElementById('count-tag-level');
    const qtyInput = document.getElementById('count-tag-qty');
    const conditionSelect = document.getElementById('count-tag-condition');
    const sizeInput = document.getElementById('count-tag-size');
    const countTagDateInput = document.getElementById('count-tag-date');
    const tranDateInput = document.getElementById('count-tag-tran-date');

    const entryModal = entryModalEl && window.bootstrap?.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(entryModalEl)
        : null;

    let html5QrCode = null;
    let audioContext = null;
    let isScanning = false;
    let isLookingUp = false;
    let isSaving = false;
    let isEntryModalOpen = false;
    let lastScannedCode = '';
    let lastScanAt = 0;
    let lastItem = null;
    let locationsLoaded = false;
    let sectionsByLocation = {};
    let selectedSection = null;

    const setCameraPill = (state) => {
        if (!cameraPill) {
            return;
        }

        cameraPill.classList.remove('is-off', 'is-ready', 'is-paused');
        cameraPill.classList.add(`is-${state}`);
        cameraPill.textContent = state === 'ready' ? 'Ready' : (state === 'paused' ? 'Paused' : 'Off');
    };

    const setCameraStatus = (message, pillState = null) => {
        if (cameraStatus) {
            cameraStatus.textContent = message;
        }
        if (pillState) {
            setCameraPill(pillState);
        }
    };

    const pauseCameraForModal = () => {
        if (!html5QrCode || !isScanning || typeof html5QrCode.pause !== 'function') {
            return;
        }

        try {
            html5QrCode.pause(true);
            setCameraStatus('Camera paused while count tag entry is open.', 'paused');
        } catch (_) {
            // Ignore pause failures; lookup guard still blocks updates.
        }
    };

    const resumeCameraAfterModal = () => {
        if (!html5QrCode || !isScanning || typeof html5QrCode.resume !== 'function') {
            return;
        }

        try {
            html5QrCode.resume();
            setCameraStatus('Camera ready. Align the QR code inside the frame.', 'ready');
        } catch (_) {
            setCameraStatus('Camera paused. Close the modal, then restart the camera if needed.', 'paused');
        }
    };

    const setResultState = ({ empty = false, loading = false, error = false, card = false } = {}) => {
        resultEmpty?.classList.toggle('d-none', !empty);
        resultLoading?.classList.toggle('d-none', !loading);
        resultError?.classList.toggle('d-none', !error);
        resultCard?.classList.toggle('d-none', !card);

        if (resultCard) {
            resultCard.setAttribute('aria-disabled', card ? 'false' : 'true');
            resultCard.classList.toggle('is-active', card);
        }
    };

    const formatItemMeta = (item) => {
        const parts = [];
        if (item.unit_name) {
            parts.push(item.unit_name);
        }
        if (item.category_name) {
            parts.push(item.category_name);
        }
        return parts.length ? parts.join(' · ') : 'Product found';
    };

    const ensureAudioContext = () => {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) {
            return null;
        }

        if (!audioContext) {
            audioContext = new AudioCtx();
        }

        if (audioContext.state === 'suspended') {
            audioContext.resume().catch(() => {});
        }

        return audioContext;
    };

    const playSuccessSound = () => {
        const ctx = ensureAudioContext();
        if (!ctx) {
            return;
        }

        const now = ctx.currentTime;

        const playTone = (frequency, startAt, duration) => {
            const oscillator = ctx.createOscillator();
            const gain = ctx.createGain();

            oscillator.type = 'triangle';
            oscillator.frequency.setValueAtTime(frequency, startAt);

            gain.gain.setValueAtTime(0.0001, startAt);
            gain.gain.exponentialRampToValueAtTime(0.12, startAt + 0.015);
            gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

            oscillator.connect(gain);
            gain.connect(ctx.destination);
            oscillator.start(startAt);
            oscillator.stop(startAt + duration + 0.02);
        };

        playTone(880, now, 0.14);
        playTone(1318.51, now + 0.11, 0.22);
    };

    const clearFieldErrors = () => {
        entryForm?.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        entryForm?.querySelectorAll('[data-error-for]').forEach((el) => {
            el.textContent = '';
        });
        formError?.classList.add('d-none');
        if (formError) {
            formError.textContent = '';
        }
    };

    const showFieldErrors = (errors = {}) => {
        Object.entries(errors).forEach(([field, messages]) => {
            const input = entryForm?.querySelector(`[name="${field}"]`);
            const feedback = entryForm?.querySelector(`[data-error-for="${field}"]`);
            const message = Array.isArray(messages) ? messages[0] : String(messages || '');

            if (input) {
                input.classList.add('is-invalid');
            }
            if (feedback) {
                feedback.textContent = message;
            }
        });
    };

    const setSaving = (active) => {
        isSaving = active;
        if (!saveButton) {
            return;
        }

        saveButton.disabled = active;
        saveButton.querySelector('.count-tag-save-label')?.classList.toggle('d-none', active);
        saveButton.querySelector('.count-tag-save-spinner')?.classList.toggle('d-none', !active);
    };

    const toastSuccess = (message) => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: message,
                showConfirmButton: false,
                timer: 2500,
            });
            return;
        }

        window.alert(message);
    };

    const fillLocationOptions = (locations) => {
        if (!locationSelect) {
            return;
        }

        const previous = locationSelect.value;
        locationSelect.innerHTML = '<option value="">Select location</option>';
        locations.forEach((location) => {
            const option = document.createElement('option');
            option.value = String(location.id);
            option.textContent = location.name;
            locationSelect.appendChild(option);
        });

        if (previous && locations.some((location) => String(location.id) === previous)) {
            locationSelect.value = previous;
        }
    };

    const fillSectionOptions = (sections) => {
        if (!sectionSelect) {
            return;
        }

        sectionSelect.innerHTML = '<option value="">Select section</option>';
        sections.forEach((section) => {
            const option = document.createElement('option');
            option.value = String(section.id);
            option.textContent = section.code;
            option.dataset.maxRow = String(section.max_row ?? 0);
            option.dataset.maxColumn = String(section.max_column ?? 0);
            sectionSelect.appendChild(option);
        });

        sectionSelect.disabled = sections.length === 0;
        selectedSection = null;
        if (sectionHint) {
            sectionHint.textContent = sections.length
                ? 'Choose a section to see row/column limits.'
                : 'No sections for this location.';
        }
    };

    const updateSectionHint = () => {
        if (!sectionSelect || !sectionHint) {
            return;
        }

        const option = sectionSelect.selectedOptions[0];
        if (!option || !option.value) {
            selectedSection = null;
            sectionHint.textContent = 'Choose a section to see row/column limits.';
            return;
        }

        selectedSection = {
            id: option.value,
            max_row: Number(option.dataset.maxRow || 0),
            max_column: Number(option.dataset.maxColumn || 0),
        };

        const parts = [];
        if (selectedSection.max_row > 0) {
            parts.push(`max row ${selectedSection.max_row}`);
        }
        if (selectedSection.max_column > 0) {
            parts.push(`max col ${selectedSection.max_column}`);
        }

        sectionHint.textContent = parts.length
            ? `Section limits: ${parts.join(', ')}.`
            : 'No row/column limits configured for this section.';

        if (rowInput && selectedSection.max_row > 0) {
            rowInput.max = String(selectedSection.max_row);
        } else if (rowInput) {
            rowInput.removeAttribute('max');
        }

        if (colInput && selectedSection.max_column > 0) {
            colInput.max = String(selectedSection.max_column);
        } else if (colInput) {
            colInput.removeAttribute('max');
        }
    };

    const loadLocations = async () => {
        if (!locationsUrl || locationsLoaded) {
            return;
        }

        try {
            const response = await fetch(locationsUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Unable to load locations.');
            }

            const payload = await response.json();
            fillLocationOptions(payload.data || []);
            locationsLoaded = true;
        } catch (_) {
            if (formError) {
                formError.textContent = 'Unable to load locations. Close and try again.';
                formError.classList.remove('d-none');
            }
        }
    };

    const loadSections = async (locationId) => {
        if (!locationId) {
            fillSectionOptions([]);
            return;
        }

        if (sectionsByLocation[locationId]) {
            fillSectionOptions(sectionsByLocation[locationId]);
            return;
        }

        const url = `${locationsUrl.replace(/\/$/, '')}/${encodeURIComponent(locationId)}/sections`;

        try {
            sectionSelect.disabled = true;
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Unable to load sections.');
            }

            const payload = await response.json();
            const sections = payload.data || [];
            sectionsByLocation[locationId] = sections;
            fillSectionOptions(sections);
        } catch (_) {
            fillSectionOptions([]);
            if (formError) {
                formError.textContent = 'Unable to load sections for this location.';
                formError.classList.remove('d-none');
            }
        }
    };

    const resetFormFields = () => {
        clearFieldErrors();
        if (itemIdInput) {
            itemIdInput.value = '';
        }
        if (locationSelect) {
            locationSelect.value = '';
        }
        fillSectionOptions([]);
        if (rowInput) {
            rowInput.value = '';
        }
        if (colInput) {
            colInput.value = '';
        }
        if (levelInput) {
            levelInput.value = '';
        }
        if (qtyInput) {
            qtyInput.value = '';
        }
        if (conditionSelect) {
            conditionSelect.value = '';
        }
        if (sizeInput) {
            sizeInput.value = '';
        }
        if (countTagDateInput) {
            countTagDateInput.value = today;
        }
        if (tranDateInput) {
            tranDateInput.value = today;
        }
    };

    const fillModal = (item) => {
        if (modalCode) {
            modalCode.textContent = item.code || '-';
        }
        if (modalName) {
            modalName.textContent = item.name || '-';
        }
        if (modalMeta) {
            modalMeta.textContent = formatItemMeta(item);
        }
        if (itemIdInput) {
            itemIdInput.value = String(item.id || '');
        }
    };

    const openEntryModal = async (item) => {
        if (!item) {
            return;
        }

        resetFormFields();
        fillModal(item);
        await loadLocations();
        entryModal?.show();
        window.setTimeout(() => qtyInput?.focus(), 250);
    };

    const showError = (message) => {
        if (resultError) {
            resultError.textContent = message;
        }
        setResultState({ error: true });
    };

    const showItem = (item, { fromCamera = false } = {}) => {
        lastItem = item;

        if (resultCode) {
            resultCode.textContent = item.code || '-';
        }
        if (resultName) {
            resultName.textContent = item.name || '-';
        }
        if (resultMeta) {
            resultMeta.textContent = formatItemMeta(item);
        }

        setResultState({ card: true });

        if (fromCamera) {
            playSuccessSound();
        }

        openEntryModal(item);
    };

    const lookupCode = async (rawCode, { fromCamera = false } = {}) => {
        const code = String(rawCode || '').trim();
        if (!code || !lookupUrl) {
            return;
        }

        if (isEntryModalOpen) {
            return;
        }

        const now = Date.now();
        if (fromCamera && code === lastScannedCode && now - lastScanAt < 2500) {
            return;
        }

        if (isLookingUp) {
            return;
        }

        isLookingUp = true;
        lastScannedCode = code;
        lastScanAt = now;
        setResultState({ loading: true });

        try {
            const url = new URL(lookupUrl, window.location.origin);
            url.searchParams.set('code', code);

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message = payload.message
                    || (payload.errors?.code ? payload.errors.code[0] : null)
                    || 'Product lookup failed.';
                showError(message);
                return;
            }

            showItem(payload, { fromCamera });
            if (manualInput && !fromCamera) {
                manualInput.value = payload.code || code;
            }
        } catch (_) {
            showError('Unable to reach the server. Check your connection and try again.');
        } finally {
            isLookingUp = false;
        }
    };

    const optionalNumber = (value) => {
        const trimmed = String(value || '').trim();
        return trimmed === '' ? null : Number(trimmed);
    };

    const saveCountTag = async () => {
        if (!storeUrl || isSaving) {
            return;
        }

        clearFieldErrors();
        setSaving(true);

        const payload = {
            item_id: Number(itemIdInput?.value || 0) || null,
            location_id: Number(locationSelect?.value || 0) || null,
            section_id: Number(sectionSelect?.value || 0) || null,
            qty: optionalNumber(qtyInput?.value),
            row: optionalNumber(rowInput?.value),
            col: optionalNumber(colInput?.value),
            level: optionalNumber(levelInput?.value),
            condition: conditionSelect?.value || null,
            size: sizeInput?.value?.trim() || null,
            count_tag_date: countTagDateInput?.value || null,
            tran_date: tranDateInput?.value || null,
        };

        try {
            const response = await fetch(storeUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json().catch(() => ({}));

            if (response.status === 422) {
                showFieldErrors(data.errors || {});
                if (formError && data.message) {
                    formError.textContent = data.message;
                    formError.classList.remove('d-none');
                }
                return;
            }

            if (!response.ok) {
                if (formError) {
                    formError.textContent = data.message || 'Unable to save count tag.';
                    formError.classList.remove('d-none');
                }
                return;
            }

            const number = data.data?.count_tag_number || '';
            toastSuccess(number ? `Saved ${number}` : 'Count tag saved.');
            entryModal?.hide();
        } catch (_) {
            if (formError) {
                formError.textContent = 'Unable to reach the server. Check your connection and try again.';
                formError.classList.remove('d-none');
            }
        } finally {
            setSaving(false);
        }
    };

    const syncCameraButtons = () => {
        if (startButton) {
            startButton.disabled = isScanning;
        }
        if (stopButton) {
            stopButton.disabled = !isScanning;
        }
    };

    const stopCamera = async () => {
        if (!html5QrCode || !isScanning) {
            syncCameraButtons();
            return;
        }

        try {
            await html5QrCode.stop();
            await html5QrCode.clear();
        } catch (_) {
            // Camera may already be stopped.
        }

        isScanning = false;
        syncCameraButtons();
        setCameraStatus('Camera stopped.', 'off');
    };

    const startCamera = async () => {
        if (typeof Html5Qrcode === 'undefined') {
            setCameraStatus('QR scanner library failed to load. Use manual lookup instead.');
            return;
        }

        if (isScanning) {
            return;
        }

        ensureAudioContext();

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode('count-tag-qr-reader');
        }

        setCameraStatus('Starting camera...', 'off');

        try {
            await html5QrCode.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: function (viewfinderWidth, viewfinderHeight) {
                        const edge = Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.72);
                        return { width: edge, height: edge };
                    },
                    aspectRatio: 1.0,
                },
                (decodedText) => {
                    lookupCode(decodedText, { fromCamera: true });
                },
                () => {}
            );

            isScanning = true;
            syncCameraButtons();
            setCameraStatus('Camera ready. Align the QR code inside the frame.', 'ready');
        } catch (error) {
            isScanning = false;
            syncCameraButtons();
            const message = error && error.message
                ? error.message
                : 'Unable to access the camera. Allow camera permission or use manual lookup.';
            setCameraStatus(message, 'off');
        }
    };

    startButton?.addEventListener('click', () => {
        startCamera();
    });

    stopButton?.addEventListener('click', () => {
        stopCamera();
    });

    manualLookupButton?.addEventListener('click', () => {
        lookupCode(manualInput?.value || '');
    });

    manualInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            lookupCode(manualInput.value || '');
        }
    });

    resultCard?.addEventListener('click', () => {
        if (!lastItem) {
            return;
        }
        openEntryModal(lastItem);
    });

    resultCard?.addEventListener('keydown', (event) => {
        if (!lastItem) {
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openEntryModal(lastItem);
        }
    });

    locationSelect?.addEventListener('change', () => {
        clearFieldErrors();
        loadSections(locationSelect.value);
    });

    sectionSelect?.addEventListener('change', () => {
        clearFieldErrors();
        updateSectionHint();
    });

    entryForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        saveCountTag();
    });

    entryModalEl?.addEventListener('show.bs.modal', () => {
        isEntryModalOpen = true;
        pauseCameraForModal();
    });

    entryModalEl?.addEventListener('hidden.bs.modal', () => {
        isEntryModalOpen = false;
        setSaving(false);
        clearFieldErrors();
        resumeCameraAfterModal();
    });

    window.addEventListener('beforeunload', () => {
        if (isScanning && html5QrCode) {
            html5QrCode.stop().catch(() => {});
        }
    });

    setResultState({ empty: true });
    syncCameraButtons();
    setCameraPill('off');
});
