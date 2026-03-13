@extends('layouts.app')
@section('title', ' | Master - User')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row align-items-center mb-4">
            <div class="col">
                <h3 class="mb-0">Manage Users</h3>
                <small class="text-muted">{{ $users->count() }} user{{ $users->count() != 1 ? 's' : '' }} registered</small>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-primary icon icon-left" data-bs-toggle="modal" data-bs-target="#create-modal">
                    <i class="fa-duotone fa-solid fa-plus"></i>
                    Add User
                </button>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="uc-toolbar mb-4" data-aos="fade-down">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-lg-6">
                    <div class="uc-search-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="user-search-input" class="form-control" placeholder="Search name or username (fuzzy)..." autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <select id="user-filter-department" class="form-select">
                        <option value="">All Departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }} ({{ $department->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <select id="user-filter-role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="General Manager">General Manager</option>
                        <option value="Manager">Manager</option>
                        <option value="Supervisor">Supervisor</option>
                        <option value="Staff">Staff</option>
                        <option value="Programmer">Programmer</option>
                    </select>
                </div>
                <div class="col-12 col-lg-1 d-grid">
                    <button type="button" id="user-filter-reset" class="btn btn-light border">Reset</button>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <small class="text-muted" id="user-search-hint">Type to search by similar name/username, not only exact text.</small>
                <small class="text-muted"><span id="user-visible-count">{{ $users->count() }}</span> visible</small>
            </div>
        </div>

        <div id="user-grid" class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4">
            @foreach ($users as $user)
                @php
                    $isMe = auth()->check() && auth()->user()->id === $user->id;

                    // Role-based gradient: warna mencerminkan jabatan
                    $roleStyle = match(true) {
                        $user->role === 'General Manager' => ['grad' => '7c2d12,c2410c',  'avatar' => 'c2410c', 'badgeCls' => 'uc-badge-gm'],
                        $user->role === 'Manager'         => ['grad' => '1e1b4b,4338ca',  'avatar' => '4338ca', 'badgeCls' => 'uc-badge-mgr'],
                        $user->role === 'Supervisor'      => ['grad' => '164e63,0e7490',  'avatar' => '0e7490', 'badgeCls' => 'uc-badge-sup'],
                        $user->role === 'Programmer'      => ['grad' => '0c4a6e,0284c7',  'avatar' => '0284c7', 'badgeCls' => 'uc-badge-prog'],
                        default                           => ['grad' => '0f172a,334155',  'avatar' => '475569', 'badgeCls' => 'uc-badge-staff'],
                    };
                @endphp
                 <div class="col mb-4"
                     data-aos="fade-up"
                     data-aos-delay="{{ $loop->iteration <= 8 ? $loop->iteration * 60 : 0 }}"
                     data-user-card="true"
                     data-user-name="{{ $user->name }}"
                     data-user-username="{{ $user->username }}"
                     data-user-department-id="{{ $user->department_id }}"
                     data-user-role="{{ $user->role }}"
                     data-order="{{ $loop->index }}">
                    <div class="card h-100 border-0 uc-card position-relative overflow-hidden">

                        {{-- Gradient banner --}}
                        <div class="uc-banner" style="background: linear-gradient(135deg, #{{ $roleStyle['grad'] }});"></div>

                        {{-- "You" badge --}}
                        @if ($isMe)
                            <span class="uc-you-badge position-absolute top-0 start-0 mt-3 ms-3">You</span>
                        @endif

                        {{-- Actions dropdown --}}
                        @if (!$isMe && $user->role !== 'General Manager')
                            <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index:10;">
                                <div class="dropdown">
                                    <button class="uc-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end uc-dropdown">
                                        <li>
                                            <button class="dropdown-item text-danger" onclick="hapusData({{ $user->id }}, 'Delete User', 'Are you sure you want to delete {{ $user->name }}?')">
                                                <i class="fa-solid fa-trash-can me-2"></i>Delete User
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        @endif

                        <div class="card-body text-center uc-body">
                            {{-- Avatar --}}
                            <div class="uc-avatar mx-auto">
                                <img src="https://ui-avatars.com/api/?background={{ $roleStyle['avatar'] }}&color=fff&bold=true&size=128&name={{ urlencode($user->name) }}"
                                     alt="{{ $user->name }}">
                            </div>

                            {{-- Name --}}
                            <h6 class="uc-name mt-3 mb-0">
                                {{ $user->name }}
                                @if ($user->hasRole('administrator'))
                                    <span data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Administrator">
                                        <i class="fa-solid fa-shield-check fa-sm ms-1 uc-admin-icon"></i>
                                    </span>
                                @endif
                            </h6>
                            <div class="uc-username">&#64;{{ $user->username }}</div>

                            {{-- Role badge --}}
                            <span class="uc-badge {{ $roleStyle['badgeCls'] }} mt-2">{{ $user->role }}</span>

                            {{-- Info --}}
                            <div class="uc-info mt-3">
                                <div class="uc-info-row">
                                    <i class="fal fa-envelope fa-fw"></i>
                                    <span class="text-truncate">{{ $user->email }}</span>
                                </div>
                                <div class="uc-info-row mt-1">
                                    <i class="fal fa-building-user fa-fw"></i>
                                    <span class="text-truncate">{{ $user->department->name }}</span>
                                    <span class="uc-dept-code ms-auto flex-shrink-0">{{ $user->department->code }}</span>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-sm uc-edit-btn w-100" data-bs-toggle="modal" data-bs-target="#edit-modal-{{ $user->id }}">
                                    <i class="fa-solid fa-user-pen me-2"></i>
                                    Edit {{ $isMe ? 'Profile' : 'User' }}
                                </button>
                            </div>
                        </div>

                        @if (!$isMe && $user->role !== 'General Manager')
                            <form action="{{ route('user.destroy', $user->id) }}" id="hapus-{{ $user->id }}" method="POST" class="d-none">
                                @method('delete')
                                @csrf
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach

            {{-- Add user card --}}
            <div id="user-add-card" class="col mb-4" data-aos="fade-up">
                <div class="uc-add-card position-relative">
                    <div class="uc-add-inner">
                        <div class="uc-add-icon">
                            <i class="fal fa-user-plus"></i>
                        </div>
                        <span class="uc-add-label">Add New User</span>
                    </div>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#create-modal" class="stretched-link"></a>
                </div>
            </div>

            <div class="col-12 d-none" id="user-empty-state">
                <div class="uc-empty-state text-center py-5">
                    <i class="fa-light fa-users-slash fa-2x mb-2"></i>
                    <h6 class="mb-1">No users found</h6>
                    <p class="mb-0 text-muted small">Try changing keyword, department, or role filter.</p>
                </div>
            </div>
        </div>
    </section>
</div>
@include('includes.modals.user-modal')
@endsection

@push('prepend-style')
@endpush
@push('addon-style')
<style>
    .uc-toolbar {
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: .8rem;
    }
    .uc-search-wrap {
        position: relative;
    }
    .uc-search-wrap i {
        position: absolute;
        left: .75rem;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        pointer-events: none;
        font-size: .9rem;
    }
    .uc-search-wrap .form-control {
        padding-left: 2.1rem;
        border-color: #cbd5e1;
    }
    .uc-search-wrap .form-control:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 .2rem rgba(2,132,199,.12);
    }

    /* ── Card shell ─────────────────────────────── */
    .uc-card {
        border-radius: 16px !important;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 12px rgba(0,0,0,.06);
        transition: transform .22s cubic-bezier(.4,0,.2,1), box-shadow .22s cubic-bezier(.4,0,.2,1);
    }
    .uc-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(0,0,0,.13);
    }

    /* ── Gradient banner ────────────────────────── */
    .uc-banner {
        height: 72px;
        border-radius: 16px 16px 0 0;
    }

    /* ── "You" pill ─────────────────────────────── */
    .uc-you-badge {
        display: inline-block;
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #fff;
        background: rgba(255,255,255,.25);
        backdrop-filter: blur(6px);
        border: 1px solid rgba(255,255,255,.35);
        padding: 2px 10px;
        border-radius: 999px;
        z-index: 10;
    }

    /* ── Action button ──────────────────────────── */
    .uc-action-btn {
        width: 30px;
        height: 30px;
        padding: 0;
        border: none;
        border-radius: 50%;
        background: rgba(255,255,255,.22);
        backdrop-filter: blur(6px);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background .15s;
    }
    .uc-action-btn:hover { background: rgba(255,255,255,.38); }

    /* ── Dropdown ───────────────────────────────── */
    .uc-dropdown {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,.12);
        padding: .4rem;
        min-width: 170px;
    }
    .uc-dropdown .dropdown-item {
        border-radius: 8px;
        font-size: .875rem;
        padding: .45rem .75rem;
    }
    .uc-dropdown .dropdown-item:hover { background: #f1f5f9; }

    .uc-edit-btn {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
        font-weight: 600;
        border-radius: 10px;
        padding: .4rem .65rem;
        transition: all .16s ease;
    }
    .uc-edit-btn:hover {
        border-color: #4338ca;
        color: #4338ca;
        background: #eef2ff;
    }

    /* ── Card body ──────────────────────────────── */
    .uc-body { padding: 0 1.25rem 1.25rem; }

    /* ── Avatar ─────────────────────────────────── */
    .uc-avatar {
        width: 76px;
        height: 76px;
        margin-top: -38px;
        border-radius: 50%;
        border: 3px solid #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,.15);
        overflow: hidden;
        background: #e2e8f0;
    }
    .uc-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    /* ── Name & username ────────────────────────── */
    .uc-name {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.3;
    }
    .uc-admin-icon { color: #0f766e; }
    .uc-username {
        font-size: .8rem;
        color: #94a3b8;
        margin-top: 1px;
    }

    /* ── Role badges ────────────────────────────── */
    .uc-badge {
        display: inline-block;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .04em;
        padding: 3px 12px;
        border-radius: 999px;
    }
    .uc-badge-gm   { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .uc-badge-mgr  { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
    .uc-badge-sup  { background: #ecfeff; color: #0e7490; border: 1px solid #a5f3fc; }
    .uc-badge-prog { background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; }
    .uc-badge-staff{ background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

    /* ── Info section ───────────────────────────── */
    .uc-info {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: .6rem .75rem;
        text-align: left;
    }
    .uc-info-row {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .8rem;
        color: #64748b;
        min-width: 0;
    }
    .uc-info-row i { flex-shrink: 0; color: #94a3b8; }
    .uc-info-row span { min-width: 0; }
    .uc-dept-code {
        font-size: .7rem;
        font-weight: 600;
        background: #e2e8f0;
        color: #475569;
        padding: 1px 8px;
        border-radius: 6px;
    }

    /* ── Add user card ──────────────────────────── */
    .uc-add-card {
        height: 100%;
        min-height: 220px;
        border-radius: 16px;
        border: 2px dashed #94a3b8;
        background: #f8fafc;
        cursor: pointer;
        transition: border-color .2s, background .2s, transform .2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .uc-add-card:hover {
        border-color: #4338ca;
        background: #eef2ff;
        transform: translateY(-4px);
    }
    .uc-add-inner {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .5rem;
        pointer-events: none;
    }
    .uc-add-icon {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: #94a3b8;
        transition: background .2s, color .2s;
    }
    .uc-add-card:hover .uc-add-icon {
        background: #c7d2fe;
        color: #4338ca;
    }
    .uc-add-label {
        font-size: .875rem;
        font-weight: 600;
        color: #94a3b8;
        transition: color .2s;
    }
    .uc-add-card:hover .uc-add-label { color: #4338ca; }

    .uc-empty-state {
        border: 1px dashed #cbd5e1;
        border-radius: 14px;
        color: #64748b;
        background: #f8fafc;
    }
</style>
@endpush
@push('addon-script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if ($errors->any())
            @if (session('editing_user_id'))
                // Show edit modal if we were editing a user
                const editModal = new bootstrap.Modal(document.getElementById('edit-modal-{{ session("editing_user_id") }}'));
                editModal.show();
            @else
                // Show create modal if we were creating a user
                const createModal = new bootstrap.Modal(document.getElementById('create-modal'));
                createModal.show();
            @endif
        @endif

        const searchInput = document.getElementById('user-search-input');
        const departmentFilter = document.getElementById('user-filter-department');
        const roleFilter = document.getElementById('user-filter-role');
        const resetButton = document.getElementById('user-filter-reset');
        const userGrid = document.getElementById('user-grid');
        const emptyState = document.getElementById('user-empty-state');
        const visibleCount = document.getElementById('user-visible-count');
        const addCard = document.getElementById('user-add-card');
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

            const tokenScores = tokens.map(function(token) {
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

            userCards.forEach(function(card) {
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

            shownCards.sort(function(a, b) {
                if (!hasQuery) return a.order - b.order;
                if (Math.abs(b.score - a.score) < 0.0001) return a.order - b.order;
                return b.score - a.score;
            });

            shownCards.forEach(function(item) {
                userGrid.insertBefore(item.node, addCard);
            });

            visibleCount.textContent = shownCards.length;
            emptyState.classList.toggle('d-none', shownCards.length !== 0);
        }

        let searchTimer;
        searchInput.addEventListener('input', function() {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(applyFilters, 90);
        });

        departmentFilter.addEventListener('change', applyFilters);
        roleFilter.addEventListener('change', applyFilters);
        resetButton.addEventListener('click', function() {
            searchInput.value = '';
            departmentFilter.value = '';
            roleFilter.value = '';
            applyFilters();
            searchInput.focus();
        });

        applyFilters();
    });
</script>
@endpush
