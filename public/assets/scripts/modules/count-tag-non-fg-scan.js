document.addEventListener('DOMContentLoaded', function () {
    const page = document.getElementById('count-tag-scan-page');
    if (!page) {
        return;
    }

    const lookupUrl = page.dataset.lookupUrl || '';
    const storeUrl = page.dataset.storeUrl || '';
    const locationsUrl = page.dataset.locationsUrl || '';
    const today = page.dataset.today || '';
    const currentMonth = page.dataset.currentMonth || '';
    const canUpdate = page.dataset.canUpdate === '1';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const startButton = document.getElementById('count-tag-start-camera');
    const stopButton = document.getElementById('count-tag-stop-camera');
    const cameraStatus = document.getElementById('count-tag-camera-status');
    const cameraPill = document.getElementById('count-tag-camera-pill');
    const cameraOverlay = document.getElementById('count-tag-camera-overlay');
    const qrFrame = document.getElementById('count-tag-qr-frame');
    const manualInput = document.getElementById('count-tag-manual-code');
    const manualLookupButton = document.getElementById('count-tag-manual-lookup');

    const resultEmpty = document.getElementById('count-tag-result-empty');
    const resultEmptyTitle = document.getElementById('count-tag-result-empty-title');
    const resultEmptyHint = document.getElementById('count-tag-result-empty-hint');
    const resultLoading = document.getElementById('count-tag-result-loading');
    const resultError = document.getElementById('count-tag-result-error');
    const resultCard = document.getElementById('count-tag-result-card');
    const resultCode = document.getElementById('count-tag-result-code');
    const resultName = document.getElementById('count-tag-result-name');
    const resultMeta = document.getElementById('count-tag-result-meta');
    const periodDisplay = document.getElementById('count-tag-period-display');
    const periodUnlock = document.getElementById('count-tag-period-unlock');
    const periodEditor = document.getElementById('count-tag-period-editor');

    const entryModalEl = document.getElementById('count-tag-entry-modal');
    const entryForm = document.getElementById('count-tag-entry-form');
    const formError = document.getElementById('count-tag-form-error');
    const saveButton = document.getElementById('count-tag-save-btn');
    const saveLabel = saveButton?.querySelector('.count-tag-save-label');
    const modalTitle = document.getElementById('count-tag-entry-modal-title');
    const modalSubtitle = document.getElementById('count-tag-entry-modal-subtitle');
    const modalContent = entryModalEl?.querySelector('.count-tag-entry-modal-content');
    const modeBanner = document.getElementById('count-tag-mode-banner');
    const modeBannerLabel = document.getElementById('count-tag-mode-banner-label');
    const modeBannerMeta = document.getElementById('count-tag-mode-banner-meta');
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
    const periodInput = document.getElementById('count-tag-period');

    const entryModal = entryModalEl && window.bootstrap?.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(entryModalEl)
        : null;

    let html5QrCode = null;
    let audioContext = null;
    let isScanning = false;
    let isLookingUp = false;
    let isSaving = false;
    let isEntryModalOpen = false;
    let isRefreshingPeriod = false;
    let lastScannedCode = '';
    let lastScanAt = 0;
    let lastItem = null;
    let locationsLoaded = false;
    let sectionsByLocation = {};
    let selectedSection = null;
    let mode = 'create';
    let existingTag = null;

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

    const playToneBurst = (tones) => {
        const ctx = ensureAudioContext();
        if (!ctx) {
            return;
        }

        const now = ctx.currentTime;

        tones.forEach(({ frequency, startAt, duration, type = 'triangle', volume = 0.12 }) => {
            const oscillator = ctx.createOscillator();
            const gain = ctx.createGain();

            oscillator.type = type;
            oscillator.frequency.setValueAtTime(frequency, now + startAt);

            gain.gain.setValueAtTime(0.0001, now + startAt);
            gain.gain.exponentialRampToValueAtTime(volume, now + startAt + 0.015);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + startAt + duration);

            oscillator.connect(gain);
            gain.connect(ctx.destination);
            oscillator.start(now + startAt);
            oscillator.stop(now + startAt + duration + 0.02);
        });
    };

    const vibrateBrief = (pattern = 40) => {
        if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
            navigator.vibrate(pattern);
        }
    };

    const playSuccessSound = () => {
        playToneBurst([
            { frequency: 880, startAt: 0, duration: 0.14 },
            { frequency: 1318.51, startAt: 0.11, duration: 0.22 },
        ]);
        vibrateBrief(30);
    };

    const playErrorSound = () => {
        playToneBurst([
            { frequency: 220, startAt: 0, duration: 0.18, type: 'square', volume: 0.08 },
            { frequency: 180, startAt: 0.16, duration: 0.2, type: 'square', volume: 0.07 },
        ]);
        vibrateBrief([40, 40, 40]);
    };

    const setLookupOverlay = (active) => {
        cameraOverlay?.classList.toggle('d-none', !active);
        qrFrame?.classList.toggle('is-looking-up', active);
    };

    const setReadyForNextScan = (saved = false) => {
        if (resultEmptyTitle) {
            resultEmptyTitle.textContent = saved ? 'Saved — ready for next scan' : 'No item scanned yet';
        }
        if (resultEmptyHint) {
            resultEmptyHint.textContent = saved
                ? 'Point the camera at the next label, or enter another code.'
                : 'Scan a label or look up a code to continue.';
        }
        resultEmpty?.classList.toggle('is-ready-next', saved);
        setResultState({ empty: true });
    };

    const syncPeriodDisplay = () => {
        if (periodDisplay) {
            periodDisplay.textContent = periodInput?.value || currentMonth || '—';
        }
    };

    const lockPeriodEditor = () => {
        periodEditor?.classList.add('d-none');
        if (periodUnlock) {
            periodUnlock.textContent = 'Change';
            periodUnlock.setAttribute('aria-expanded', 'false');
        }
    };

    const unlockPeriodEditor = () => {
        periodEditor?.classList.remove('d-none');
        if (periodUnlock) {
            periodUnlock.textContent = 'Hide';
            periodUnlock.setAttribute('aria-expanded', 'true');
        }
        periodInput?.focus();
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

    const focusFirstInvalid = () => {
        const firstInvalid = entryForm?.querySelector('.is-invalid');
        if (formError && !formError.classList.contains('d-none')) {
            formError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (typeof firstInvalid.focus === 'function') {
                firstInvalid.focus({ preventScroll: true });
            }
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

        focusFirstInvalid();
    };

    const setSaving = (active) => {
        isSaving = active;
        if (!saveButton) {
            return;
        }

        saveButton.disabled = active || (mode === 'update' && !canUpdate);
        saveButton.querySelector('.count-tag-save-label')?.classList.toggle('d-none', active);
        saveButton.querySelector('.count-tag-save-spinner')?.classList.toggle('d-none', !active);
    };

    const toastSuccess = (message) => {
        if (typeof Swal !== 'undefined') {
            const isNarrow = window.matchMedia('(max-width: 576px)').matches;

            Swal.fire({
                toast: true,
                position: isNarrow ? 'top' : 'top-end',
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

    const rebuildPositionOptions = (select, max, selected = '') => {
        if (!select) {
            return;
        }

        const previous = selected !== '' && selected != null
            ? String(selected)
            : String(select.value || '');

        select.innerHTML = '<option value="">—</option>';
        const limit = Number(max || 0);
        for (let value = 1; value <= limit; value += 1) {
            const option = document.createElement('option');
            option.value = String(value);
            option.textContent = String(value);
            select.appendChild(option);
        }

        if (previous !== '' && [...select.options].some((option) => option.value === previous)) {
            select.value = previous;
        } else {
            select.value = '';
        }
    };

    const fillSectionOptions = (sections) => {
        if (!sectionSelect) {
            return;
        }

        sectionSelect.innerHTML = '<option value="">—</option>';
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
        rebuildPositionOptions(rowInput, 0);
        rebuildPositionOptions(colInput, 0);
        if (sectionHint) {
            sectionHint.textContent = sections.length
                ? 'Choose a section to see row/column options.'
                : 'No sections for this location.';
        }
    };

    const updateSectionHint = (selectedRow = null, selectedCol = null) => {
        if (!sectionSelect || !sectionHint) {
            return;
        }

        const option = sectionSelect.selectedOptions[0];
        if (!option || !option.value) {
            selectedSection = null;
            rebuildPositionOptions(rowInput, 0);
            rebuildPositionOptions(colInput, 0);
            sectionHint.textContent = 'Choose a section to see row/column options.';
            return;
        }

        selectedSection = {
            id: option.value,
            max_row: Number(option.dataset.maxRow || 0),
            max_column: Number(option.dataset.maxColumn || 0),
        };

        rebuildPositionOptions(rowInput, selectedSection.max_row, selectedRow);
        rebuildPositionOptions(colInput, selectedSection.max_column, selectedCol);

        const parts = [];
        if (selectedSection.max_row > 0) {
            parts.push(`rows 1–${selectedSection.max_row}`);
        }
        if (selectedSection.max_column > 0) {
            parts.push(`cols 1–${selectedSection.max_column}`);
        }

        sectionHint.textContent = parts.length
            ? `Section options: ${parts.join(', ')}.`
            : 'No row/column options configured for this section.';
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

    const formatExistingModeMeta = (existing) => {
        const period = existing?.count_tag_month
            || (existing?.count_tag_date ? String(existing.count_tag_date).slice(0, 7) : '')
            || (periodInput?.value || currentMonth || '');
        const tagNumber = existing?.count_tag_number
            ? String(existing.count_tag_number)
            : 'No tag number yet';
        const parts = [];

        if (period) {
            parts.push(`Period ${period}`);
        }
        parts.push(tagNumber);

        return parts.join(' · ');
    };

    const setMode = (nextMode, existing = null) => {
        mode = nextMode;
        existingTag = existing;
        const isUpdate = mode === 'update';

        modalContent?.classList.toggle('is-update-mode', isUpdate);

        if (modalTitle) {
            modalTitle.textContent = isUpdate ? 'Update Count Tag' : 'Count Tag Entry';
        }
        if (modalSubtitle) {
            modalSubtitle.textContent = isUpdate
                ? 'Saving will update the existing count tag for this period (not create a new one).'
                : 'Confirm location and quantity, then save.';
        }

        if (modeBanner) {
            modeBanner.classList.toggle('d-none', !isUpdate);
            modeBanner.classList.toggle('is-update', isUpdate);
        }
        if (modeBannerLabel) {
            modeBannerLabel.textContent = isUpdate ? 'Already counted this period' : '';
        }
        if (modeBannerMeta) {
            modeBannerMeta.textContent = isUpdate ? formatExistingModeMeta(existing) : '';
        }

        if (saveLabel) {
            saveLabel.textContent = isUpdate ? 'Update existing' : 'Save & scan next';
        }
        if (saveButton) {
            saveButton.classList.toggle('is-update-mode', isUpdate);
            saveButton.classList.toggle('btn-primary', !isUpdate);
            saveButton.classList.toggle('btn-warning', isUpdate);
            saveButton.disabled = isUpdate && !canUpdate;
        }

        if (isUpdate && !canUpdate && formError) {
            formError.textContent = 'You do not have permission to update an existing count tag.';
            formError.classList.remove('d-none');
        }
    };

    const focusPrimaryField = () => {
        if (locationSelect && !locationSelect.value) {
            locationSelect.focus();
            return;
        }

        qtyInput?.focus();
    };

    const applyDefaults = async (defaults, { keepQtyBlank = true } = {}) => {
        if (!defaults) {
            return;
        }

        if (defaults.location_id) {
            if (locationSelect) {
                locationSelect.value = String(defaults.location_id);
            }
            await loadSections(String(defaults.location_id));
        }

        if (defaults.section_id && sectionSelect) {
            sectionSelect.value = String(defaults.section_id);
            updateSectionHint(defaults.row ?? '', defaults.col ?? '');
        } else {
            rebuildPositionOptions(rowInput, 0);
            rebuildPositionOptions(colInput, 0);
        }

        if (levelInput) {
            levelInput.value = defaults.level ?? '';
        }
        if (conditionSelect) {
            conditionSelect.value = defaults.condition || '';
        }
        if (sizeInput) {
            sizeInput.value = defaults.size || '';
        }
        if (periodInput) {
            const month = defaults.count_tag_month
                || (defaults.count_tag_date ? String(defaults.count_tag_date).slice(0, 7) : '');
            if (month) {
                periodInput.value = month;
            }
        }
        if (qtyInput && !keepQtyBlank && defaults.qty != null) {
            qtyInput.value = defaults.qty;
        }
    };

    const resetFormFields = () => {
        clearFieldErrors();
        mode = 'create';
        existingTag = null;
        if (itemIdInput) {
            itemIdInput.value = '';
        }
        if (locationSelect) {
            locationSelect.value = '';
        }
        fillSectionOptions([]);
        rebuildPositionOptions(rowInput, 0);
        rebuildPositionOptions(colInput, 0);
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
        if (periodInput) {
            periodInput.value = currentMonth;
        }
        syncPeriodDisplay();
        lockPeriodEditor();
        setMode('create');
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

        if (item.existing) {
            setMode('update', item.existing);
            await applyDefaults(item.existing, { keepQtyBlank: true });
        } else {
            setMode('create');
            await applyDefaults(item.defaults || null, { keepQtyBlank: true });
        }

        syncPeriodDisplay();
        lockPeriodEditor();
        entryModal?.show();
        window.setTimeout(() => focusPrimaryField(), 280);
    };

    const showError = (message, { fromCamera = false } = {}) => {
        if (resultError) {
            resultError.textContent = message;
        }
        setResultState({ error: true });

        if (fromCamera) {
            playErrorSound();
            setCameraStatus(message, isScanning ? 'ready' : 'off');
        }
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

    const lookupCode = async (rawCode, { fromCamera = false, periodMonth = null } = {}) => {
        const code = String(rawCode || '').trim();
        if (!code || !lookupUrl) {
            return null;
        }

        if (isEntryModalOpen && !isRefreshingPeriod) {
            return null;
        }

        const now = Date.now();
        if (fromCamera && code === lastScannedCode && now - lastScanAt < 2500) {
            setCameraStatus('Already scanned — adjust the label or scan the next item.', isScanning ? 'ready' : 'paused');
            return null;
        }

        if (isLookingUp) {
            if (fromCamera) {
                setCameraStatus('Still looking up the last scan…', isScanning ? 'ready' : 'paused');
            }
            return null;
        }

        isLookingUp = true;
        if (!isRefreshingPeriod) {
            lastScannedCode = code;
            lastScanAt = now;
            setResultState({ loading: true });
            if (fromCamera) {
                setLookupOverlay(true);
            }
        }

        try {
            const url = new URL(lookupUrl, window.location.origin);
            url.searchParams.set('code', code);
            const period = periodMonth || periodInput?.value || currentMonth;
            if (period) {
                url.searchParams.set('count_tag_date', period);
            }

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
                if (!isRefreshingPeriod) {
                    showError(message, { fromCamera });
                }
                return null;
            }

            if (!isRefreshingPeriod) {
                showItem(payload, { fromCamera });
                if (manualInput && !fromCamera) {
                    manualInput.value = payload.code || code;
                }
            }

            return payload;
        } catch (_) {
            if (!isRefreshingPeriod) {
                showError('Unable to reach the server. Check your connection and try again.', { fromCamera });
            }
            return null;
        } finally {
            isLookingUp = false;
            setLookupOverlay(false);
        }
    };

    const refreshForPeriod = async () => {
        if (!lastItem?.code || !periodInput?.value || isRefreshingPeriod) {
            return;
        }

        isRefreshingPeriod = true;
        clearFieldErrors();

        try {
            const payload = await lookupCode(lastItem.code, {
                periodMonth: periodInput.value,
            });

            if (!payload) {
                return;
            }

            lastItem = {
                ...lastItem,
                existing: payload.existing || null,
                defaults: payload.defaults || null,
            };

            const preserveQty = qtyInput?.value || '';

            if (payload.existing) {
                setMode('update', payload.existing);
                await applyDefaults(payload.existing, { keepQtyBlank: true });
            } else {
                setMode('create');
                await applyDefaults(payload.defaults || null, { keepQtyBlank: true });
            }

            if (qtyInput) {
                qtyInput.value = preserveQty;
            }

            syncPeriodDisplay();
        } finally {
            isRefreshingPeriod = false;
        }
    };

    const optionalNumber = (value) => {
        const trimmed = String(value || '').trim();
        return trimmed === '' ? null : Number(trimmed);
    };

    const saveCountTag = async () => {
        if (isSaving) {
            return;
        }

        if (mode === 'update' && !canUpdate) {
            if (formError) {
                formError.textContent = 'You do not have permission to update an existing count tag.';
                formError.classList.remove('d-none');
            }
            return;
        }

        const targetUrl = mode === 'update'
            ? (existingTag?.id ? `${storeUrl.replace(/\/$/, '')}/${existingTag.id}` : null)
            : storeUrl;

        if (!targetUrl) {
            return;
        }

        clearFieldErrors();

        const payload = {
            location_id: Number(locationSelect?.value || 0) || null,
            section_id: Number(sectionSelect?.value || 0) || null,
            qty: optionalNumber(qtyInput?.value),
            row: optionalNumber(rowInput?.value),
            col: optionalNumber(colInput?.value),
            level: optionalNumber(levelInput?.value),
            condition: conditionSelect?.value || null,
            size: sizeInput?.value?.trim() || null,
            count_tag_date: periodInput?.value || null,
        };

        if (mode === 'create') {
            payload.item_id = Number(itemIdInput?.value || 0) || null;
        }

        const clientErrors = {};
        if (mode === 'create' && !payload.item_id) {
            clientErrors.item_id = ['Scan or look up a product first.'];
        }
        if (!payload.location_id) {
            clientErrors.location_id = ['Select a location.'];
        }
        if (payload.qty === null || Number(payload.qty) <= 0) {
            clientErrors.qty = ['Enter a quantity greater than zero.'];
        }
        if (!payload.count_tag_date) {
            clientErrors.count_tag_date = ['Select a period month.'];
            unlockPeriodEditor();
        }

        if (Object.keys(clientErrors).length > 0) {
            if (formError) {
                formError.textContent = 'Check the highlighted fields, then try again.';
                formError.classList.remove('d-none');
            }
            showFieldErrors(clientErrors);
            return;
        }

        setSaving(true);

        try {
            const response = await fetch(targetUrl, {
                method: mode === 'update' ? 'PUT' : 'POST',
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
                if (formError) {
                    formError.textContent = data.message || 'Check the highlighted fields, then try again.';
                    formError.classList.remove('d-none');
                }
                if (data.errors?.count_tag_date) {
                    unlockPeriodEditor();
                }
                return;
            }

            if (!response.ok) {
                if (formError) {
                    formError.textContent = data.message || 'Unable to save count tag.';
                    formError.classList.remove('d-none');
                }
                focusFirstInvalid();
                return;
            }

            const successMessage = data.message
                || (mode === 'update' ? 'Count tag updated.' : 'Count tag saved.');
            toastSuccess(successMessage);
            lastItem = null;
            setReadyForNextScan(true);
            setCameraStatus(
                isScanning
                    ? 'Saved. Camera ready for the next label.'
                    : 'Saved. Tap Start or enter the next product code.',
                isScanning ? 'ready' : 'off'
            );
            entryModal?.hide();
        } catch (_) {
            if (formError) {
                formError.textContent = 'Unable to reach the server. Check your connection and try again.';
                formError.classList.remove('d-none');
            }
            focusFirstInvalid();
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

        if (!window.isSecureContext) {
            setCameraStatus(
                'Camera needs HTTPS. Open https://' + window.location.hostname + ' (no :8000), or use manual lookup.',
                'off'
            );
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
                        const ratio = window.matchMedia('(max-width: 576px)').matches ? 0.82 : 0.72;
                        const edge = Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * ratio);
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
            const raw = error && error.message ? String(error.message) : '';
            let message = 'Unable to access the camera. Allow camera permission or use manual lookup below.';
            if (/NotAllowedError|Permission/i.test(raw)) {
                message = 'Camera permission blocked. Allow camera access, or use manual lookup below.';
            } else if (/NotFoundError|DevicesNotFound/i.test(raw)) {
                message = 'No camera found on this device. Use manual lookup below.';
            } else if (/NotReadableError|TrackStartError/i.test(raw)) {
                message = 'Camera is busy in another app. Close it, then tap Start again.';
            }
            setCameraStatus(message, 'off');
            playErrorSound();
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

    periodInput?.addEventListener('change', () => {
        syncPeriodDisplay();
        refreshForPeriod();
    });

    periodUnlock?.addEventListener('click', () => {
        if (periodEditor?.classList.contains('d-none')) {
            unlockPeriodEditor();
        } else {
            lockPeriodEditor();
        }
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
        lockPeriodEditor();
        resumeCameraAfterModal();
    });

    window.addEventListener('beforeunload', () => {
        if (isScanning && html5QrCode) {
            html5QrCode.stop().catch(() => {});
        }
    });

    setReadyForNextScan(false);
    syncPeriodDisplay();
    lockPeriodEditor();
    syncCameraButtons();
    setCameraPill('off');
});
