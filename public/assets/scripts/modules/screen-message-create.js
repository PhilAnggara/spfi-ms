(function () {
    const modeInput = document.getElementById('display_mode');
    const audienceInput = document.getElementById('audience_type');
    const themeInput = document.getElementById('theme');
    const titleInput = document.getElementById('title');
    const bodyInput = document.getElementById('body');
    const editorSurface = document.getElementById('sm-rich-editor-surface');
    const previewRoot = document.getElementById('sm-theme-preview');
    const previewTitle = document.getElementById('sm-preview-title');
    const previewBody = document.getElementById('sm-preview-body');
    const durationPanel = document.getElementById('duration-panel');
    const durationField = document.getElementById('duration-field');
    const durationInput = document.getElementById('duration_seconds');
    const optionalToggleWrap = document.getElementById('optional-duration-toggle-wrap');
    const optionalToggle = document.getElementById('enable_optional_duration');
    const help = document.getElementById('display-mode-help');
    const usersBlock = document.getElementById('targets-users');
    const deptsBlock = document.getElementById('targets-departments');
    const allBlock = document.getElementById('targets-all');
    const usersSelect = document.getElementById('target_users');
    const deptsSelect = document.getElementById('target_departments');
    const defaultAutoDuration = '30';
    let lastOptionalDuration = durationInput?.value || defaultAutoDuration;

    const ALLOWED_TAGS = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'UL', 'OL', 'LI', 'DIV']);

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function sanitizeHtml(html) {
        const template = document.createElement('template');
        template.innerHTML = String(html || '');

        const walk = (node) => {
            Array.from(node.childNodes).forEach((child) => {
                if (child.nodeType === Node.TEXT_NODE) {
                    return;
                }
                if (child.nodeType !== Node.ELEMENT_NODE) {
                    child.remove();
                    return;
                }
                if (!ALLOWED_TAGS.has(child.tagName)) {
                    while (child.firstChild) {
                        node.insertBefore(child.firstChild, child);
                    }
                    child.remove();
                    return;
                }
                while (child.attributes.length > 0) {
                    child.removeAttribute(child.attributes[0].name);
                }
                walk(child);
            });
        };

        walk(template.content);
        return template.innerHTML;
    }

    function normalizeEditorHtml(html) {
        let clean = sanitizeHtml(html).trim();
        clean = clean
            .replaceAll(/<\/?div>/gi, (tag) => (tag.toLowerCase().startsWith('</') ? '</p>' : '<p>'))
            .replaceAll(/<p>\s*<\/p>/gi, '')
            .replaceAll(/<p><br\s*\/?><\/p>/gi, '');

        if (!clean || clean === '<br>') {
            return '';
        }

        if (!clean.includes('<')) {
            return `<p>${escapeHtml(clean).replaceAll('\n', '<br>')}</p>`;
        }

        return clean;
    }

    function formatPreviewBody(value) {
        const raw = normalizeEditorHtml(value);
        if (!raw) {
            return 'Your message will appear here.';
        }
        return sanitizeHtml(raw);
    }

    function getBodyHtml() {
        if (!editorSurface) {
            return bodyInput?.value || '';
        }
        return normalizeEditorHtml(editorSurface.innerHTML);
    }

    function syncBodyInput() {
        if (bodyInput) {
            bodyInput.value = getBodyHtml();
        }
    }

    function seedEditorFromTextarea() {
        if (!editorSurface || !bodyInput) {
            return;
        }
        const initial = (bodyInput.value || '').trim();
        if (!initial) {
            editorSurface.innerHTML = '';
            return;
        }
        if (!initial.includes('<')) {
            editorSurface.innerHTML = `<p>${escapeHtml(initial).replaceAll('\n', '<br>')}</p>`;
            return;
        }
        editorSurface.innerHTML = sanitizeHtml(initial);
    }

    function runCommand(command) {
        if (!editorSurface) {
            return;
        }
        editorSurface.focus();
        document.execCommand(command, false, null);
        syncBodyInput();
        syncThemePreview();
    }

    document.querySelectorAll('.sm-rich-editor__btn[data-command]').forEach((btn) => {
        btn.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            runCommand(btn.getAttribute('data-command'));
        });
    });

    if (editorSurface) {
        seedEditorFromTextarea();
        editorSurface.addEventListener('input', () => {
            syncBodyInput();
            syncThemePreview();
        });
        editorSurface.addEventListener('paste', (event) => {
            event.preventDefault();
            const text = (event.clipboardData || window.clipboardData)?.getData('text/plain') || '';
            document.execCommand('insertText', false, text);
            syncBodyInput();
            syncThemePreview();
        });
    }

    function initChoices(select) {
        if (!select || typeof Choices === 'undefined') {
            return null;
        }

        if (select.choicesInstance) {
            return select.choicesInstance;
        }

        const instance = new Choices(select, {
            removeItemButton: true,
            searchEnabled: true,
            searchPlaceholderValue: 'Type to search…',
            placeholder: true,
            placeholderValue: 'Select…',
            shouldSort: false,
            itemSelectText: '',
        });
        select.choicesInstance = instance;
        return instance;
    }

    const userChoices = initChoices(usersSelect);
    const deptChoices = initChoices(deptsSelect);

    function setActiveButtons(selector, attr, value) {
        document.querySelectorAll(selector).forEach((btn) => {
            const active = btn.getAttribute(attr) === value;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function syncMode() {
        const mode = modeInput.value;

        if (mode === 'permanent') {
            durationPanel.style.display = 'none';
            optionalToggleWrap.style.display = 'none';
            durationField.style.display = 'none';
            durationInput.required = false;
            durationInput.disabled = true;
            durationInput.name = '';
            durationInput.value = '';
            help.textContent = '';
        } else if (mode === 'auto_only') {
            durationPanel.style.display = '';
            optionalToggleWrap.style.display = 'none';
            durationField.style.display = '';
            durationInput.disabled = false;
            durationInput.name = 'duration_seconds';
            durationInput.required = true;
            if (!durationInput.value) {
                durationInput.value = defaultAutoDuration;
            }
            help.textContent = 'Required. Overlay auto-closes when the timer ends.';
        } else {
            durationPanel.style.display = '';
            optionalToggleWrap.style.display = '';
            durationInput.required = false;
            syncOptionalDuration();
            help.textContent = optionalToggle.checked
                ? 'Overlay can be closed by the user, and also auto-closes after this timer.'
                : 'No timer. Recipients close the message with the Close button.';
        }

        setActiveButtons('[data-display-mode]', 'data-display-mode', mode);
    }

    function syncOptionalDuration() {
        const enabled = optionalToggle.checked;
        durationField.style.display = enabled ? '' : 'none';
        durationInput.disabled = !enabled;
        durationInput.name = enabled ? 'duration_seconds' : '';
        durationInput.required = enabled;

        if (enabled) {
            if (!durationInput.value) {
                durationInput.value = lastOptionalDuration || defaultAutoDuration;
            }
        } else if (durationInput.value) {
            lastOptionalDuration = durationInput.value;
            durationInput.value = '';
        }

        help.textContent = enabled
            ? 'Overlay can be closed by the user, and also auto-closes after this timer.'
            : 'No timer. Recipients close the message with the Close button.';
    }

    function syncAudience() {
        const type = audienceInput.value;
        usersBlock.style.display = type === 'users' ? '' : 'none';
        deptsBlock.style.display = type === 'departments' ? '' : 'none';
        allBlock.style.display = type === 'all' ? '' : 'none';

        if (type === 'users') {
            usersSelect.disabled = false;
            usersSelect.name = 'target_ids[]';
            deptsSelect.disabled = true;
            deptsSelect.name = '';
            if (deptChoices) {
                deptChoices.disable();
            }
            if (userChoices) {
                userChoices.enable();
            }
        } else if (type === 'departments') {
            deptsSelect.disabled = false;
            deptsSelect.name = 'target_ids[]';
            usersSelect.disabled = true;
            usersSelect.name = '';
            if (userChoices) {
                userChoices.disable();
            }
            if (deptChoices) {
                deptChoices.enable();
            }
        } else {
            usersSelect.disabled = true;
            deptsSelect.disabled = true;
            usersSelect.name = '';
            deptsSelect.name = '';
            if (userChoices) {
                userChoices.disable();
            }
            if (deptChoices) {
                deptChoices.disable();
            }
        }

        setActiveButtons('[data-audience-type]', 'data-audience-type', type);
    }

    function syncThemePreview() {
        const title = (titleInput.value || '').trim() || 'Message title';

        if (previewRoot) {
            previewRoot.setAttribute('data-theme', themeInput.value || 'default');
        }
        if (previewTitle) {
            previewTitle.textContent = title;
        }
        if (previewBody) {
            previewBody.innerHTML = formatPreviewBody(getBodyHtml());
        }
    }

    function syncTheme() {
        document.querySelectorAll('.sm-theme-chip').forEach((btn) => {
            const active = btn.getAttribute('data-theme') === themeInput.value;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        syncThemePreview();
    }

    document.querySelectorAll('[data-display-mode]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (btn.disabled) {
                return;
            }
            modeInput.value = btn.getAttribute('data-display-mode');
            syncMode();
        });
    });

    document.querySelectorAll('[data-audience-type]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (btn.disabled) {
                return;
            }
            audienceInput.value = btn.getAttribute('data-audience-type');
            syncAudience();
        });
    });

    document.querySelectorAll('.sm-theme-chip').forEach((btn) => {
        btn.addEventListener('click', () => {
            themeInput.value = btn.getAttribute('data-theme');
            syncTheme();
        });
    });

    titleInput?.addEventListener('input', syncThemePreview);

    optionalToggle?.addEventListener('change', () => {
        if (modeInput.value === 'user_closable') {
            syncOptionalDuration();
        }
    });

    document.getElementById('screen-message-form')?.addEventListener('submit', () => {
        syncBodyInput();
    });

    syncMode();
    syncAudience();
    syncTheme();
    syncBodyInput();
})();
