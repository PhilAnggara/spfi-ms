document.addEventListener('DOMContentLoaded', function () {
    const userPage = document.getElementById('user-page');
    const shouldOpenCreateModal = userPage?.dataset.openCreateModal === '1';
    const editingUserId = userPage?.dataset.editingUserId || '';

    if (shouldOpenCreateModal) {
        if (editingUserId) {
            const editModalEl = document.getElementById('edit-modal-' + editingUserId);
            if (editModalEl && window.bootstrap && window.bootstrap.Modal) {
                const editModal = new window.bootstrap.Modal(editModalEl);
                editModal.show();
            }
        } else {
            const createModalEl = document.getElementById('create-modal');
            if (createModalEl && window.bootstrap && window.bootstrap.Modal) {
                const createModal = new window.bootstrap.Modal(createModalEl);
                createModal.show();
            }
        }
    }

    const searchInput = document.getElementById('user-search-input');
    const departmentFilter = document.getElementById('user-filter-department');
    const roleFilter = document.getElementById('user-filter-role');
    const resetButton = document.getElementById('user-filter-reset');
    const userGrid = document.getElementById('user-grid');
    const emptyState = document.getElementById('user-empty-state');
    const visibleCount = document.getElementById('user-visible-count');
    const addCard = document.getElementById('user-add-card');

    if (!searchInput || !departmentFilter || !roleFilter || !resetButton || !userGrid || !emptyState || !visibleCount || !addCard) {
        return;
    }

    const userCards = Array.from(userGrid.querySelectorAll('[data-user-card="true"]'));

    function normalizeText(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function isSubsequence(needle, haystack) {
        let i = 0;
        let j = 0;
        while (i < needle.length && j < haystack.length) {
            if (needle[i] === haystack[j]) {
                i++;
            }
            j++;
        }
        return i === needle.length;
    }

    function levenshteinDistance(a, b) {
        const rows = a.length + 1;
        const cols = b.length + 1;
        const matrix = Array.from({ length: rows }, () => new Array(cols).fill(0));

        for (let i = 0; i < rows; i++) matrix[i][0] = i;
        for (let j = 0; j < cols; j++) matrix[0][j] = j;

        for (let i = 1; i < rows; i++) {
            for (let j = 1; j < cols; j++) {
                const cost = a[i - 1] === b[j - 1] ? 0 : 1;
                matrix[i][j] = Math.min(
                    matrix[i - 1][j] + 1,
                    matrix[i][j - 1] + 1,
                    matrix[i - 1][j - 1] + cost
                );
            }
        }

        return matrix[a.length][b.length];
    }

    function fuzzyTokenScore(token, text) {
        if (!token || !text) return 0;
        if (text === token) return 1;
        if (text.startsWith(token)) return 0.95;
        if (text.includes(token)) return 0.88;
        if (isSubsequence(token, text)) {
            const compactnessPenalty = Math.max(0, (text.length - token.length) / Math.max(text.length, 1)) * 0.2;
            return 0.75 - compactnessPenalty;
        }

        let best = 0;
        const words = text.split(' ').filter(Boolean);
        for (const word of words) {
            const maxLen = Math.max(token.length, word.length);
            if (!maxLen) continue;
            const dist = levenshteinDistance(token, word);
            const similarity = 1 - (dist / maxLen);
            if (similarity > best) best = similarity;
        }

        if (best >= 0.75) return Math.min(0.82, best);
        if (best >= 0.65) return Math.min(0.72, best);
        return 0;
    }

    function scoreQueryAgainstUser(query, name, username) {
        const q = normalizeText(query);
        if (!q) return 1;

        const tokens = q.split(' ').filter(Boolean);
        if (!tokens.length) return 1;

        const normalizedName = normalizeText(name);
        const normalizedUsername = normalizeText(username);

        const tokenScores = tokens.map(function (token) {
            const nameScore = fuzzyTokenScore(token, normalizedName);
            const usernameScore = fuzzyTokenScore(token, normalizedUsername);
            return Math.max(nameScore, usernameScore);
        });

        const average = tokenScores.reduce((sum, score) => sum + score, 0) / tokenScores.length;
        const minimum = Math.min(...tokenScores);
        return (average * 0.7) + (minimum * 0.3);
    }

    function applyFilters() {
        const query = searchInput.value;
        const selectedDepartment = departmentFilter.value;
        const selectedRole = roleFilter.value;
        const hasQuery = normalizeText(query).length > 0;

        let shownCards = [];

        userCards.forEach(function (card) {
            const name = card.getAttribute('data-user-name') || '';
            const username = card.getAttribute('data-user-username') || '';
            const departmentId = card.getAttribute('data-user-department-id') || '';
            const role = card.getAttribute('data-user-role') || '';

            const score = scoreQueryAgainstUser(query, name, username);
            const passSearch = !hasQuery || score >= 0.56;
            const passDepartment = !selectedDepartment || selectedDepartment === departmentId;
            const passRole = !selectedRole || selectedRole === role;

            const visible = passSearch && passDepartment && passRole;
            card.style.display = visible ? '' : 'none';

            if (visible) {
                shownCards.push({
                    node: card,
                    score: score,
                    order: Number(card.getAttribute('data-order') || 0)
                });
            }
        });

        shownCards.sort(function (a, b) {
            if (!hasQuery) return a.order - b.order;
            if (Math.abs(b.score - a.score) < 0.0001) return a.order - b.order;
            return b.score - a.score;
        });

        shownCards.forEach(function (item) {
            userGrid.insertBefore(item.node, addCard);
        });

        visibleCount.textContent = shownCards.length;
        emptyState.classList.toggle('d-none', shownCards.length !== 0);
    }

    let searchTimer;
    searchInput.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(applyFilters, 90);
    });

    departmentFilter.addEventListener('change', applyFilters);
    roleFilter.addEventListener('change', applyFilters);
    resetButton.addEventListener('click', function () {
        searchInput.value = '';
        departmentFilter.value = '';
        roleFilter.value = '';
        applyFilters();
        searchInput.focus();
    });

    applyFilters();
});
