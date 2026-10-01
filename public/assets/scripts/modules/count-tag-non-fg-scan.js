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

    let html5QrCode = null;
    let isScanning = false;
    let isLookingUp = false;
    let lastScannedCode = '';
    let lastScanAt = 0;

    const setCameraStatus = (message) => {
        if (cameraStatus) {
            cameraStatus.textContent = message;
        }
    };

    const setResultState = ({ empty = false, loading = false, error = false, card = false } = {}) => {
        resultEmpty?.classList.toggle('d-none', !empty);
        resultLoading?.classList.toggle('d-none', !loading);
        resultError?.classList.toggle('d-none', !error);
        resultCard?.classList.toggle('d-none', !card);
    };

    const showError = (message) => {
        if (resultError) {
            resultError.textContent = message;
        }
        setResultState({ error: true });
    };

    const showItem = (item) => {
        if (resultCode) {
            resultCode.textContent = item.code || '-';
        }
        if (resultName) {
            resultName.textContent = item.name || '-';
        }
        if (resultMeta) {
            const parts = [];
            if (item.unit_name) {
                parts.push(item.unit_name);
            }
            if (item.category_name) {
                parts.push(item.category_name);
            }
            resultMeta.textContent = parts.length ? parts.join(' · ') : 'Product found';
        }
        setResultState({ card: true });
    };

    const lookupCode = async (rawCode, { fromCamera = false } = {}) => {
        const code = String(rawCode || '').trim();
        if (!code || !lookupUrl) {
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

            showItem(payload);
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

    window.addEventListener('beforeunload', () => {
        if (isScanning && html5QrCode) {
            html5QrCode.stop().catch(() => {});
        }
    });

    setResultState({ empty: true });
    syncCameraButtons();
});
