window.initMasterCodeValidation = function initMasterCodeValidation(config) {
    const {
        input,
        checkUrl,
        ignoreId = null,
        rules = {},
        messages = {},
        modal = null,
    } = config;

    if (!input || !checkUrl) {
        return null;
    }

    const defaultMessages = {
        available: 'Code is available.',
        taken: 'This code has already been used.',
        invalidFormat: 'Code may only contain letters and numbers.',
        tooLong: 'Code must be 8 characters or fewer.',
        ...messages,
    };

    let currentIgnoreId = ignoreId;
    let debounceTimer = null;
    let abortController = null;
    let status = 'idle';

    const form = input.closest('form');
    const feedbackContainer = input.parentElement;

    let invalidFeedback = feedbackContainer.querySelector('.js-code-invalid-feedback');
    let validFeedback = feedbackContainer.querySelector('.js-code-valid-feedback');

    if (!invalidFeedback) {
        invalidFeedback = document.createElement('div');
        invalidFeedback.className = 'invalid-feedback js-code-invalid-feedback';
        feedbackContainer.appendChild(invalidFeedback);
    }

    if (!validFeedback) {
        validFeedback = document.createElement('div');
        validFeedback.className = 'valid-feedback js-code-valid-feedback';
        feedbackContainer.appendChild(validFeedback);
    }

    const clearValidation = () => {
        input.classList.remove('is-valid', 'is-invalid');
        invalidFeedback.classList.remove('d-block');
        validFeedback.classList.remove('d-block');
        invalidFeedback.textContent = '';
        validFeedback.textContent = '';
        status = 'idle';
    };

    const setInvalid = (message) => {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        invalidFeedback.classList.remove('d-block');
        invalidFeedback.textContent = message;
        validFeedback.textContent = '';
        validFeedback.classList.remove('d-block');
        status = 'unavailable';
    };

    const setValid = (message) => {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        validFeedback.classList.remove('d-block');
        validFeedback.textContent = message;
        invalidFeedback.textContent = '';
        invalidFeedback.classList.remove('d-block');
        status = 'available';
    };

    const showPending = () => {
        input.classList.remove('is-valid', 'is-invalid');
        const spinnerMarkup = '<div class="spinner-border spinner-border-sm text-primary" role="status" aria-label="Loading"></div>';
        // Bootstrap `.invalid-feedback` biasanya `display: none` saat input tidak `is-invalid`.
        // Saat pending, paksa tampilkan agar spinner terlihat.
        invalidFeedback.classList.add('d-block');
        validFeedback.classList.remove('d-block');
        invalidFeedback.innerHTML = spinnerMarkup;
        validFeedback.textContent = '';
    };

    const validateFormat = (code) => {
        if (rules.maxLength && code.length > rules.maxLength) {
            return { valid: false, message: defaultMessages.tooLong };
        }

        if (rules.alphaNum && !/^[a-zA-Z0-9]+$/.test(code)) {
            return { valid: false, message: defaultMessages.invalidFormat };
        }

        return { valid: true };
    };

    const checkAvailability = async (code) => {
        if (abortController) {
            abortController.abort();
        }

        abortController = new AbortController();
        status = 'pending';
        showPending();

        const params = new URLSearchParams({ code });
        if (currentIgnoreId) {
            params.set('ignore_id', String(currentIgnoreId));
        }

        try {
            const response = await fetch(`${checkUrl}?${params.toString()}`, {
                signal: abortController.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                if (response.status === 422) {
                    const data = await response.json();
                    const firstError = data.errors?.code?.[0] || defaultMessages.invalidFormat;
                    input.classList.remove('is-valid');
                    input.classList.add('is-invalid');
                    invalidFeedback.textContent = firstError;
                    validFeedback.textContent = '';
                    status = 'invalid';

                    return;
                }

                clearValidation();

                return;
            }

            const data = await response.json();
            if (data.available) {
                setValid(data.message || defaultMessages.available);
            } else {
                setInvalid(data.message || defaultMessages.taken);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                clearValidation();
            }
        }
    };

    const scheduleCheck = () => {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(() => {
            const code = input.value.trim();
            if (!code) {
                clearValidation();

                return;
            }

            const formatResult = validateFormat(code);
            if (!formatResult.valid) {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                invalidFeedback.textContent = formatResult.message;
                validFeedback.textContent = '';
                status = 'invalid';

                return;
            }

            checkAvailability(code);
        }, 350);
    };

    input.addEventListener('input', scheduleCheck);

    if (form) {
        form.addEventListener('submit', (event) => {
            if (status === 'pending' || status === 'unavailable' || status === 'invalid') {
                event.preventDefault();
            }
        });
    }

    if (modal) {
        modal.addEventListener('hidden.bs.modal', () => {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
                debounceTimer = null;
            }

            if (abortController) {
                abortController.abort();
                abortController = null;
            }

            clearValidation();
        });
    }

    return {
        setIgnoreId(id) {
            currentIgnoreId = id;
        },
        reset() {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
                debounceTimer = null;
            }

            if (abortController) {
                abortController.abort();
                abortController = null;
            }

            clearValidation();
        },
        recheck() {
            scheduleCheck();
        },
    };
};
