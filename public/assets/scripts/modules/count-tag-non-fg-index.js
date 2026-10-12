(function () {
    let isLoading = false;
    let pendingReplaceRequest = null;

    function initPageTooltips(scope = document) {
        const tooltipElements = scope.querySelectorAll('[data-bstooltip-toggle="tooltip"]');

        tooltipElements.forEach((el) => {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                if (window.bootstrap.Tooltip.getInstance(el)) {
                    return;
                }

                new window.bootstrap.Tooltip(el);
            }
        });
    }

    function disposePageTooltips(scope) {
        if (!window.bootstrap?.Tooltip || !scope) {
            return;
        }

        scope.querySelectorAll('[data-bstooltip-toggle="tooltip"]').forEach((el) => {
            window.bootstrap.Tooltip.getInstance(el)?.dispose();
        });
    }

    function setLoading(active) {
        const loadingEl = document.getElementById('ct-page-loading');
        if (!loadingEl) {
            return;
        }

        loadingEl.classList.toggle('d-none', !active);
        loadingEl.classList.toggle('d-flex', active);
    }

    function showPaginationError() {
        setLoading(false);
        window.alert('Could not load this page. Please try again.');
    }

    async function replacePageContent(url, pushState = true) {
        const normalizedUrl = new URL(url, window.location.origin).toString();

        if (isLoading) {
            pendingReplaceRequest = {
                url: normalizedUrl,
                pushState,
            };
            return;
        }

        isLoading = true;
        setLoading(true);

        try {
            const response = await fetch(normalizedUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                showPaginationError();
                return;
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newResults = doc.querySelector('#ct-page-results');
            const currentResults = document.querySelector('#ct-page-results');

            const hasNewerPendingRequest = pendingReplaceRequest && pendingReplaceRequest.url !== normalizedUrl;
            if (hasNewerPendingRequest) {
                return;
            }

            if (!newResults || !currentResults) {
                showPaginationError();
                return;
            }

            disposePageTooltips(currentResults);
            currentResults.replaceWith(newResults);

            if (pushState) {
                window.history.pushState({}, '', normalizedUrl);
            }

            if (typeof window.initCountTagNonFgPage === 'function') {
                window.initCountTagNonFgPage();
            }
        } catch (_) {
            showPaginationError();
        } finally {
            isLoading = false;
            setLoading(false);

            if (pendingReplaceRequest) {
                const nextRequest = pendingReplaceRequest;
                pendingReplaceRequest = null;
                replacePageContent(nextRequest.url, nextRequest.pushState);
            }
        }
    }

    window.ctReplacePageContent = replacePageContent;

    function initCountTagNonFgFilters() {
        const filterForm = document.getElementById('ct-filter-form');
        if (!filterForm || filterForm.dataset.filterInitialized === '1') {
            return;
        }
        filterForm.dataset.filterInitialized = '1';

        const filterElements = {
            keyword: document.getElementById('filter-ct-keyword'),
            dateStart: document.getElementById('filter-ct-date-start'),
            dateEnd: document.getElementById('filter-ct-date-end'),
            location: document.getElementById('filter-ct-location'),
            category: document.getElementById('filter-ct-category'),
            reset: document.getElementById('reset-ct-filter'),
        };

        const setQueryParam = (searchParams, key, value) => {
            const normalizedValue = String(value || '').trim();
            if (normalizedValue === '') {
                searchParams.delete(key);
                return;
            }

            searchParams.set(key, normalizedValue);
        };

        const buildFilterUrl = () => {
            const url = new URL(window.location.href);

            setQueryParam(url.searchParams, 'keyword', filterElements.keyword?.value);
            setQueryParam(url.searchParams, 'date_start', filterElements.dateStart?.value);
            setQueryParam(url.searchParams, 'date_end', filterElements.dateEnd?.value);
            setQueryParam(url.searchParams, 'location_id', filterElements.location?.value);
            setQueryParam(url.searchParams, 'category_id', filterElements.category?.value);
            url.searchParams.delete('page');

            return url.toString();
        };

        let debounceTimer = null;
        const applyServerFilter = (useDebounce = false) => {
            const doRequest = () => {
                const url = buildFilterUrl();

                if (typeof window.ctReplacePageContent === 'function') {
                    window.ctReplacePageContent(url, true);
                    return;
                }

                window.location.href = url;
            };

            if (!useDebounce) {
                doRequest();
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(doRequest, 350);
        };

        if (filterElements.keyword) {
            filterElements.keyword.addEventListener('input', () => applyServerFilter(true));
        }

        if (filterElements.dateStart) {
            filterElements.dateStart.addEventListener('change', () => applyServerFilter(false));
        }

        if (filterElements.dateEnd) {
            filterElements.dateEnd.addEventListener('change', () => applyServerFilter(false));
        }

        if (filterElements.location) {
            filterElements.location.addEventListener('change', () => applyServerFilter(false));
        }

        if (filterElements.category) {
            filterElements.category.addEventListener('change', () => applyServerFilter(false));
        }

        if (filterElements.reset) {
            filterElements.reset.addEventListener('click', function () {
                if (filterElements.keyword) {
                    filterElements.keyword.value = '';
                }
                if (filterElements.dateStart) {
                    filterElements.dateStart.value = '';
                }
                if (filterElements.dateEnd) {
                    filterElements.dateEnd.value = '';
                }
                if (filterElements.location) {
                    filterElements.location.value = '';
                }
                if (filterElements.category) {
                    filterElements.category.value = '';
                }

                applyServerFilter(false);
            });
        }
    }

    function displayValue(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        return String(value);
    }

    function setField(root, name, value, { html = false } = {}) {
        if (!root) {
            return;
        }

        root.querySelectorAll(`[data-field="${name}"]`).forEach((el) => {
            if (html) {
                el.innerHTML = value;
                return;
            }

            el.textContent = value;
        });
    }

    function positionLine(data) {
        const parts = [];

        if (data.section_code) {
            parts.push(`Sec ${data.section_code}`);
        }

        const row = displayValue(data.row);
        const col = displayValue(data.col);
        const level = displayValue(data.level);
        if (row !== '—' || col !== '—' || level !== '—') {
            parts.push(`R${row}/C${col}/L${level}`);
        }

        return parts.length ? parts.join(' · ') : '—';
    }

    function conditionBadgeHtml(condition) {
        if (!condition) {
            return '—';
        }

        const map = {
            Good: 'is-good',
            Damaged: 'is-damaged',
            Expired: 'is-expired',
            Quarantine: 'is-quarantine',
        };
        const cls = map[condition] || 'is-good';

        return `<span class="ct-condition-badge ${cls}">${condition}</span>`;
    }

    async function openDetailModal(url) {
        const modalEl = document.getElementById('ct-detail-modal');
        if (!modalEl || !window.bootstrap) {
            return;
        }

        const loadingEl = document.getElementById('ct-detail-loading');
        const errorEl = document.getElementById('ct-detail-error');
        const contentEl = document.getElementById('ct-detail-content');
        const titleEl = document.getElementById('ct-detail-modal-title');

        loadingEl?.classList.remove('d-none');
        errorEl?.classList.add('d-none');
        contentEl?.classList.add('d-none');

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Unable to load count tag detail.');
            }

            const data = await response.json();
            const qtyText = Number(data.qty ?? 0).toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 5,
            });
            const uom = data.uom_code || data.uom_name || '';

            if (titleEl) {
                titleEl.textContent = data.count_tag_number
                    ? data.count_tag_number
                    : 'Count Tag Detail';
            }

            setField(modalEl, 'count_tag_number', displayValue(data.count_tag_number));
            setField(modalEl, 'count_tag_month', displayValue(data.count_tag_month || (data.count_tag_date ? String(data.count_tag_date).slice(0, 7) : '')));
            setField(modalEl, 'tran_date', displayValue(data.tran_date));
            setField(modalEl, 'created_by_name', displayValue(data.created_by_name));
            setField(modalEl, 'item_code', displayValue(data.item_code));
            setField(modalEl, 'item_name', displayValue(data.item_name));
            setField(modalEl, 'category_name', displayValue(data.category_name));
            setField(modalEl, 'uom', displayValue(data.uom_name || data.uom_code));
            setField(modalEl, 'qty_display', uom ? `${qtyText} ${uom}` : qtyText);
            setField(modalEl, 'location_name', displayValue(data.location_name));
            setField(modalEl, 'position_line', positionLine(data));
            setField(modalEl, 'section_code', displayValue(data.section_code));
            setField(modalEl, 'row', displayValue(data.row));
            setField(modalEl, 'col', displayValue(data.col));
            setField(modalEl, 'level', displayValue(data.level));
            setField(modalEl, 'size', displayValue(data.size));
            setField(modalEl, 'condition_html', conditionBadgeHtml(data.condition), { html: true });

            loadingEl?.classList.add('d-none');
            contentEl?.classList.remove('d-none');
        } catch (error) {
            loadingEl?.classList.add('d-none');
            if (errorEl) {
                errorEl.textContent = error.message || 'Unable to load count tag detail.';
                errorEl.classList.remove('d-none');
            }
        }
    }

    function initCountTagNonFgDetailButtons() {
        document.querySelectorAll('.ct-view-btn').forEach(function (button) {
            if (button.dataset.bound === '1') {
                return;
            }
            button.dataset.bound = '1';

            button.addEventListener('click', function () {
                const url = button.dataset.detailUrl;
                if (!url) {
                    return;
                }

                openDetailModal(url);
            });
        });
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function toastSuccess(message) {
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
    }

    function optionalNumber(value) {
        const trimmed = String(value || '').trim();
        return trimmed === '' ? null : Number(trimmed);
    }

    const editState = {
        updateUrl: null,
        locationsLoaded: false,
        sectionsByLocation: {},
        selectedSection: null,
        isSaving: false,
    };

    function editElements() {
        return {
            form: document.getElementById('ct-edit-form'),
            modalEl: document.getElementById('ct-edit-modal'),
            formError: document.getElementById('ct-edit-form-error'),
            saveButton: document.getElementById('ct-edit-save-btn'),
            itemCode: document.getElementById('ct-edit-item-code'),
            itemName: document.getElementById('ct-edit-item-name'),
            itemMeta: document.getElementById('ct-edit-item-meta'),
            locationSelect: document.getElementById('ct-edit-location'),
            sectionSelect: document.getElementById('ct-edit-section'),
            sectionHint: document.getElementById('ct-edit-section-hint'),
            rowInput: document.getElementById('ct-edit-row'),
            colInput: document.getElementById('ct-edit-col'),
            levelInput: document.getElementById('ct-edit-level'),
            qtyInput: document.getElementById('ct-edit-qty'),
            conditionSelect: document.getElementById('ct-edit-condition'),
            sizeInput: document.getElementById('ct-edit-size'),
            periodInput: document.getElementById('ct-edit-period'),
            subtitle: document.getElementById('ct-edit-modal-subtitle'),
        };
    }

    function clearEditErrors() {
        const els = editElements();
        els.form?.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        els.form?.querySelectorAll('[data-error-for]').forEach((el) => {
            el.textContent = '';
        });
        els.formError?.classList.add('d-none');
        if (els.formError) {
            els.formError.textContent = '';
        }
    }

    function showEditErrors(errors = {}) {
        const els = editElements();
        Object.entries(errors).forEach(([field, messages]) => {
            const input = els.form?.querySelector(`[name="${field}"]`);
            const feedback = els.form?.querySelector(`[data-error-for="${field}"]`);
            const message = Array.isArray(messages) ? messages[0] : String(messages || '');

            if (input) {
                input.classList.add('is-invalid');
            }
            if (feedback) {
                feedback.textContent = message;
            }
        });
    }

    function setEditSaving(active) {
        const els = editElements();
        editState.isSaving = active;
        if (!els.saveButton) {
            return;
        }

        els.saveButton.disabled = active;
        els.saveButton.querySelector('.ct-edit-save-label')?.classList.toggle('d-none', active);
        els.saveButton.querySelector('.ct-edit-save-spinner')?.classList.toggle('d-none', !active);
    }

    function fillEditLocationOptions(locations) {
        const els = editElements();
        if (!els.locationSelect) {
            return;
        }

        const previous = els.locationSelect.value;
        els.locationSelect.innerHTML = '<option value="">Select location</option>';
        locations.forEach((location) => {
            const option = document.createElement('option');
            option.value = String(location.id);
            option.textContent = location.name;
            els.locationSelect.appendChild(option);
        });

        if (previous && locations.some((location) => String(location.id) === previous)) {
            els.locationSelect.value = previous;
        }
    }

    function rebuildEditPositionOptions(select, max, selected = '') {
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
    }

    function fillEditSectionOptions(sections) {
        const els = editElements();
        if (!els.sectionSelect) {
            return;
        }

        els.sectionSelect.innerHTML = '<option value="">Select section</option>';
        sections.forEach((section) => {
            const option = document.createElement('option');
            option.value = String(section.id);
            option.textContent = section.code;
            option.dataset.maxRow = String(section.max_row ?? 0);
            option.dataset.maxColumn = String(section.max_column ?? 0);
            els.sectionSelect.appendChild(option);
        });

        els.sectionSelect.disabled = sections.length === 0;
        editState.selectedSection = null;
        rebuildEditPositionOptions(els.rowInput, 0);
        rebuildEditPositionOptions(els.colInput, 0);
        if (els.sectionHint) {
            els.sectionHint.textContent = sections.length
                ? 'Choose a section to see row/column options.'
                : 'No sections for this location.';
        }
    }

    function updateEditSectionHint(selectedRow = null, selectedCol = null) {
        const els = editElements();
        if (!els.sectionSelect || !els.sectionHint) {
            return;
        }

        const option = els.sectionSelect.selectedOptions[0];
        if (!option || !option.value) {
            editState.selectedSection = null;
            rebuildEditPositionOptions(els.rowInput, 0);
            rebuildEditPositionOptions(els.colInput, 0);
            els.sectionHint.textContent = 'Choose a section to see row/column options.';
            return;
        }

        editState.selectedSection = {
            id: option.value,
            max_row: Number(option.dataset.maxRow || 0),
            max_column: Number(option.dataset.maxColumn || 0),
        };

        rebuildEditPositionOptions(els.rowInput, editState.selectedSection.max_row, selectedRow);
        rebuildEditPositionOptions(els.colInput, editState.selectedSection.max_column, selectedCol);

        const parts = [];
        if (editState.selectedSection.max_row > 0) {
            parts.push(`rows 1–${editState.selectedSection.max_row}`);
        }
        if (editState.selectedSection.max_column > 0) {
            parts.push(`cols 1–${editState.selectedSection.max_column}`);
        }

        els.sectionHint.textContent = parts.length
            ? `Section options: ${parts.join(', ')}.`
            : 'No row/column options configured for this section.';
    }

    async function loadEditLocations() {
        const page = document.getElementById('count-tag-non-fg-page-container');
        const locationsUrl = page?.dataset.locationsUrl || '';
        if (!locationsUrl || editState.locationsLoaded) {
            return;
        }

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
        fillEditLocationOptions(payload.data || []);
        editState.locationsLoaded = true;
    }

    async function loadEditSections(locationId) {
        const page = document.getElementById('count-tag-non-fg-page-container');
        const locationsUrl = page?.dataset.locationsUrl || '';
        if (!locationId) {
            fillEditSectionOptions([]);
            return;
        }

        if (editState.sectionsByLocation[locationId]) {
            fillEditSectionOptions(editState.sectionsByLocation[locationId]);
            return;
        }

        const url = `${locationsUrl.replace(/\/$/, '')}/${encodeURIComponent(locationId)}/sections`;
        const els = editElements();
        els.sectionSelect.disabled = true;

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            fillEditSectionOptions([]);
            throw new Error('Unable to load sections.');
        }

        const payload = await response.json();
        const sections = payload.data || [];
        editState.sectionsByLocation[locationId] = sections;
        fillEditSectionOptions(sections);
    }

    async function openEditModal(detailUrl) {
        const els = editElements();
        if (!els.modalEl || !window.bootstrap || !detailUrl) {
            return;
        }

        clearEditErrors();
        editState.updateUrl = null;

        const modal = bootstrap.Modal.getOrCreateInstance(els.modalEl);
        modal.show();

        try {
            await loadEditLocations();

            const response = await fetch(detailUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Unable to load count tag for editing.');
            }

            const data = await response.json();
            editState.updateUrl = data.update_url || null;

            if (els.itemCode) {
                els.itemCode.textContent = data.item_code || '-';
            }
            if (els.itemName) {
                els.itemName.textContent = data.item_name || '-';
            }
            if (els.itemMeta) {
                els.itemMeta.textContent = data.count_tag_number || 'Unassigned number';
            }
            if (els.subtitle) {
                els.subtitle.textContent = data.tran_date
                    ? `Input ${data.tran_date}`
                    : 'Update location, quantity, and period.';
            }

            if (els.locationSelect && data.location_id) {
                els.locationSelect.value = String(data.location_id);
                await loadEditSections(String(data.location_id));
            } else {
                fillEditSectionOptions([]);
            }

            if (els.sectionSelect && data.section_id) {
                els.sectionSelect.value = String(data.section_id);
                updateEditSectionHint(data.row ?? '', data.col ?? '');
            } else {
                rebuildEditPositionOptions(els.rowInput, 0);
                rebuildEditPositionOptions(els.colInput, 0);
            }

            if (els.levelInput) {
                els.levelInput.value = data.level ?? '';
            }
            if (els.qtyInput) {
                els.qtyInput.value = data.qty ?? '';
            }
            if (els.conditionSelect) {
                els.conditionSelect.value = data.condition || '';
            }
            if (els.sizeInput) {
                els.sizeInput.value = data.size || '';
            }
            if (els.periodInput) {
                els.periodInput.value = data.count_tag_month
                    || (data.count_tag_date ? String(data.count_tag_date).slice(0, 7) : '')
                    || pageCurrentMonth();
            }
        } catch (error) {
            if (els.formError) {
                els.formError.textContent = error.message || 'Unable to open editor.';
                els.formError.classList.remove('d-none');
            }
        }
    }

    function pageToday() {
        return document.getElementById('count-tag-non-fg-page-container')?.dataset.today || '';
    }

    function pageCurrentMonth() {
        return document.getElementById('count-tag-non-fg-page-container')?.dataset.currentMonth || '';
    }

    async function saveEditForm() {
        const els = editElements();
        if (!editState.updateUrl || editState.isSaving) {
            return;
        }

        clearEditErrors();
        setEditSaving(true);

        const payload = {
            location_id: Number(els.locationSelect?.value || 0) || null,
            section_id: Number(els.sectionSelect?.value || 0) || null,
            qty: optionalNumber(els.qtyInput?.value),
            row: optionalNumber(els.rowInput?.value),
            col: optionalNumber(els.colInput?.value),
            level: optionalNumber(els.levelInput?.value),
            condition: els.conditionSelect?.value || null,
            size: els.sizeInput?.value?.trim() || null,
            count_tag_date: els.periodInput?.value || null,
        };

        try {
            const response = await fetch(editState.updateUrl, {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json().catch(() => ({}));

            if (response.status === 422) {
                showEditErrors(data.errors || {});
                if (els.formError && data.message) {
                    els.formError.textContent = data.message;
                    els.formError.classList.remove('d-none');
                }
                return;
            }

            if (!response.ok) {
                if (els.formError) {
                    els.formError.textContent = data.message || 'Unable to update count tag.';
                    els.formError.classList.remove('d-none');
                }
                return;
            }

            toastSuccess(data.message || 'Count tag updated.');
            bootstrap.Modal.getInstance(els.modalEl)?.hide();
            replacePageContent(window.location.href, false);
        } catch (_) {
            if (els.formError) {
                els.formError.textContent = 'Unable to reach the server. Check your connection and try again.';
                els.formError.classList.remove('d-none');
            }
        } finally {
            setEditSaving(false);
        }
    }

    function initCountTagNonFgEdit() {
        const els = editElements();
        if (!els.form || els.form.dataset.bound === '1') {
            return;
        }
        els.form.dataset.bound = '1';

        els.locationSelect?.addEventListener('change', () => {
            clearEditErrors();
            loadEditSections(els.locationSelect.value).catch(() => {});
        });

        els.sectionSelect?.addEventListener('change', () => {
            clearEditErrors();
            updateEditSectionHint();
        });

        els.form.addEventListener('submit', (event) => {
            event.preventDefault();
            saveEditForm();
        });
    }

    function initCountTagNonFgEditButtons() {
        document.querySelectorAll('.ct-edit-btn').forEach(function (button) {
            if (button.dataset.bound === '1') {
                return;
            }
            button.dataset.bound = '1';

            button.addEventListener('click', function () {
                const url = button.dataset.detailUrl;
                if (!url) {
                    return;
                }

                openEditModal(url);
            });
        });
    }

    function initCountTagNonFgDeleteButtons() {
        document.querySelectorAll('.ct-delete-btn').forEach(function (button) {
            if (button.dataset.bound === '1') {
                return;
            }
            button.dataset.bound = '1';

            button.addEventListener('click', async function () {
                const destroyUrl = button.dataset.destroyUrl;
                const label = button.dataset.label || 'this count tag';
                if (!destroyUrl) {
                    return;
                }

                let confirmed = false;
                if (typeof Swal !== 'undefined') {
                    const result = await Swal.fire({
                        title: 'Delete count tag?',
                        text: `Delete ${label}? This cannot be undone.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Delete',
                        confirmButtonColor: '#dc3545',
                    });
                    confirmed = Boolean(result.isConfirmed);
                } else {
                    confirmed = window.confirm(`Delete ${label}?`);
                }

                if (!confirmed) {
                    return;
                }

                try {
                    const response = await fetch(destroyUrl, {
                        method: 'DELETE',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        window.alert(data.message || 'Unable to delete count tag.');
                        return;
                    }

                    toastSuccess(data.message || 'Count tag deleted.');
                    replacePageContent(window.location.href, false);
                } catch (_) {
                    window.alert('Unable to reach the server. Check your connection and try again.');
                }
            });
        });
    }

    function initCountTagNonFgPage() {
        initCountTagNonFgFilters();
        initCountTagNonFgDetailButtons();
        initCountTagNonFgEdit();
        initCountTagNonFgEditButtons();
        initCountTagNonFgDeleteButtons();
        initPageTooltips(document.getElementById('count-tag-non-fg-page-container') || document);
    }

    window.initCountTagNonFgPage = initCountTagNonFgPage;

    document.addEventListener('DOMContentLoaded', function () {
        initCountTagNonFgPage();
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('#count-tag-non-fg-page-container a[href*="page="]');
        if (!link) {
            return;
        }

        event.preventDefault();
        replacePageContent(link.href, true);
    });

    window.addEventListener('popstate', function () {
        replacePageContent(window.location.href, false);
    });
})();
