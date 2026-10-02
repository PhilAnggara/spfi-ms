document.addEventListener('DOMContentLoaded', function () {
    const page = document.getElementById('count-tag-scan-page');
    if (!page) {
        return;
    }

    const lookupUrl = page.dataset.lookupUrl || '';
    const startButton = document.getElementById('count-tag-start-camera');
    const stopButton = document.getElementById('count-tag-stop-camera');
    const cameraStatus = document.getElementById('count-tag-camera-status');
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
    const modalCode = document.getElementById('count-tag-modal-code');
    const modalName = document.getElementById('count-tag-modal-name');
    const modalMeta = document.getElementById('count-tag-modal-meta');
    const entryModal = entryModalEl && window.bootstrap?.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(entryModalEl)
        : null;

    let html5QrCode = null;
    let audioContext = null;
    let isScanning = false;
    let isLookingUp = false;
    let isEntryModalOpen = false;
    let lastScannedCode = '';
    let lastScanAt = 0;
    let lastItem = null;

    const setCameraStatus = (message) => {
        if (cameraStatus) {
            cameraStatus.textContent = message;
        }
    };

    const pauseCameraForModal = () => {
        if (!html5QrCode || !isScanning || typeof html5QrCode.pause !== 'function') {
            return;
        }

        try {
            html5QrCode.pause(true);
            setCameraStatus('Camera paused while count tag entry is open.');
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
            setCameraStatus('Camera ready. Align the QR code inside the frame.');
        } catch (_) {
            setCameraStatus('Camera paused. Close the modal, then restart the camera if needed.');
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

        // Soft two-note confirmation chime (A5 → E6)
        playTone(880, now, 0.14);
        playTone(1318.51, now + 0.11, 0.22);
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
    };

    const openEntryModal = (item) => {
        if (!item) {
            return;
        }

        fillModal(item);
        entryModal?.show();
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
        setCameraStatus('Camera stopped.');
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

        setCameraStatus('Starting camera...');

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
            setCameraStatus('Camera ready. Align the QR code inside the frame.');
        } catch (error) {
            isScanning = false;
            syncCameraButtons();
            const message = error && error.message
                ? error.message
                : 'Unable to access the camera. Allow camera permission or use manual lookup.';
            setCameraStatus(message);
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

    entryModalEl?.addEventListener('show.bs.modal', () => {
        isEntryModalOpen = true;
        pauseCameraForModal();
    });

    entryModalEl?.addEventListener('hidden.bs.modal', () => {
        isEntryModalOpen = false;
        resumeCameraAfterModal();
    });

    window.addEventListener('beforeunload', () => {
        if (isScanning && html5QrCode) {
            html5QrCode.stop().catch(() => {});
        }
    });

    setResultState({ empty: true });
    syncCameraButtons();
});
