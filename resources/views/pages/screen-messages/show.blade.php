@extends('layouts.app')
@section('title', ' | Screen Message')

@section('content')
<div
    class="page-heading po-page sc-page sm-page"
    id="screen-message-show-page"
    data-live-url="{{ route('screen-messages.live', $message) }}"
    data-can-view-replies="{{ $canViewReplies ? '1' : '0' }}"
>
    <div class="page-title mb-4">
        <div class="row g-3 align-items-start">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">{{ $message->title }}</h3>
                    <p class="text-muted mb-0">
                        Sent by {{ $message->user?->name ?? '—' }}
                        · {{ $message->created_at?->format('d M Y H:i') }}
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="badge {{ $message->theme->badgeClass() }}">{{ $message->theme->label() }}</span>
                        <span class="badge bg-light-secondary text-secondary">{{ $message->display_mode->label() }}</span>
                        <span class="badge bg-light-info text-info">{{ $message->audience_type->label() }}</span>
                        @if ($message->is_active)
                            <span class="badge bg-light-success text-success">Active</span>
                        @else
                            <span class="badge bg-light-warning text-warning">Inactive</span>
                        @endif
                        <span class="badge bg-light-primary" id="sm-seen-badge">{{ $seenCount }} / {{ $recipientCount }} seen</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="prs-create-actions sm-page-actions justify-content-lg-end">
                    <a href="{{ route('screen-messages.index') }}" class="btn btn-light-secondary icon icon-left">
                        <i class="fa-light fa-arrow-left"></i>
                        Back
                    </a>
                    @if ($canDeactivate && $message->is_active)
                        <form method="POST" action="{{ route('screen-messages.deactivate', $message) }}" id="sm-deactivate-form" class="sm-action-form">
                            @csrf
                            <button type="button" class="btn btn-warning icon icon-left" id="sm-deactivate-btn">
                                <i class="fa-regular fa-ban"></i>
                                Deactivate
                            </button>
                        </form>
                    @endif
                    @if ($canDelete)
                        <form method="POST" action="{{ route('screen-messages.destroy', $message) }}" id="sm-delete-form" class="sm-action-form">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-outline-danger icon icon-left" id="sm-delete-btn">
                                <i class="fa-regular fa-trash"></i>
                                Delete
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success shadow-sm border-0">{{ session('success') }}</div>
    @endif

    <section class="section">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 mb-4 sm-detail-card">
                    <div class="card-header bg-transparent">
                        <span class="sc-section-title"><i class="fa-regular fa-message-lines"></i> Message</span>
                    </div>
                    <div class="card-body">
                        <div class="sm-message-body">{!! \App\Support\ScreenMessageHtml::forDisplay($message->body) !!}</div>
                        <div class="sm-message-meta">
                            <div class="sm-message-meta__item">
                                <span class="sm-message-meta__label">Duration</span>
                                <span class="sm-message-meta__value">{{ $message->duration_seconds ? $message->duration_seconds.'s' : 'Manual close only' }}</span>
                            </div>
                            <div class="sm-message-meta__item">
                                <span class="sm-message-meta__label">Allow reply</span>
                                <span class="sm-message-meta__value">{{ $message->allow_reply ? 'Yes' : 'No' }}</span>
                            </div>
                            @if (! $message->is_active && $message->deactivated_at)
                                <div class="sm-message-meta__item sm-message-meta__item--wide">
                                    <span class="sm-message-meta__label">Deactivated</span>
                                    <span class="sm-message-meta__value">
                                        {{ $message->deactivated_at->format('d M Y H:i') }}
                                        @if ($message->deactivatedBy)
                                            by {{ $message->deactivatedBy->name }}
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($canViewReplies)
                    <div class="card shadow-sm border-0 sm-detail-card">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <span class="sc-section-title mb-0"><i class="fa-regular fa-comments"></i> Replies</span>
                            <span class="badge bg-light-primary" id="sm-replies-count">{{ $message->replies->count() }}</span>
                        </div>
                        <div class="card-body sm-replies-list" id="sm-replies-list">
                            @forelse ($message->replies as $reply)
                                @php
                                    $replyName = $reply->user?->name ?? '—';
                                    $initials = collect(preg_split('/\s+/', trim($replyName)))
                                        ->filter()
                                        ->take(2)
                                        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                                        ->implode('');
                                @endphp
                                <article class="sm-reply-item">
                                    <div class="sm-reply-item__avatar" aria-hidden="true">{{ $initials !== '' ? $initials : '?' }}</div>
                                    <div class="sm-reply-item__content">
                                        <div class="sm-reply-item__head">
                                            <span class="sm-reply-item__name">{{ $replyName }}</span>
                                            <span class="sm-reply-item__time">{{ $reply->created_at?->format('d M Y H:i') }}</span>
                                        </div>
                                        <div class="sm-reply-item__body">{{ $reply->body }}</div>
                                    </div>
                                </article>
                            @empty
                                <div class="sm-replies-empty">
                                    <i class="fa-light fa-comment-dots"></i>
                                    <p class="mb-0">No replies yet.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent">
                        <div class="sm-recipients-toolbar mb-3">
                            <span class="sc-section-title mb-0"><i class="fa-regular fa-users"></i> Recipients</span>
                            <div class="sm-recipients-toolbar__actions">
                                <span class="badge bg-light-primary" id="sm-recipient-summary">{{ $seenCount }} seen · {{ $recipientCount - $seenCount }} pending</span>
                                <button type="button" class="btn btn-sm btn-outline-primary icon icon-left" id="sm-refresh-btn">
                                    <i class="fa-regular fa-rotate"></i>
                                    <span class="sm-refresh-label">Refresh</span>
                                </button>
                            </div>
                        </div>
                        <div class="row g-2 sm-recipients-filters">
                            <div class="col-12 col-sm-7">
                                <input type="search" id="sm-recipient-search" class="form-control form-control-sm" placeholder="Search by name…">
                            </div>
                            <div class="col-12 col-sm-5">
                                <select id="sm-recipient-status" class="form-select form-select-sm">
                                    <option value="all">All statuses</option>
                                    <option value="seen">Seen</option>
                                    <option value="unseen">Not yet seen</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Department</th>
                                        <th>Seen</th>
                                        <th>Dismissed</th>
                                    </tr>
                                </thead>
                                <tbody id="sm-recipients-body">
                                    @foreach ($message->recipients->sortBy(fn ($r) => $r->user?->name) as $recipient)
                                        <tr
                                            data-name="{{ strtolower($recipient->user?->name ?? '') }}"
                                            data-seen="{{ $recipient->seen_at ? '1' : '0' }}"
                                        >
                                            <td>{{ $recipient->user?->name ?? '—' }}</td>
                                            <td>{{ $recipient->user?->department?->name ?? '—' }}</td>
                                            <td>
                                                @if ($recipient->seen_at)
                                                    <span class="text-success">{{ $recipient->seen_at->format('d M Y H:i') }}</span>
                                                @else
                                                    <span class="text-muted">Not yet</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($recipient->dismissed_at)
                                                    {{ $recipient->dismissed_at->format('d M Y H:i') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted small mb-0 mt-2 d-none" id="sm-recipients-empty-filter">No recipients match this filter.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/prs-modern.css') }}">
@endpush

@push('addon-script')
<script>
(function () {
    const page = document.getElementById('screen-message-show-page');
    if (!page) {
        return;
    }

    const liveUrl = page.dataset.liveUrl;
    const canViewReplies = page.dataset.canViewReplies === '1';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const searchInput = document.getElementById('sm-recipient-search');
    const statusSelect = document.getElementById('sm-recipient-status');
    const recipientsBody = document.getElementById('sm-recipients-body');
    const emptyFilter = document.getElementById('sm-recipients-empty-filter');
    const refreshBtn = document.getElementById('sm-refresh-btn');
    const seenBadge = document.getElementById('sm-seen-badge');
    const recipientSummary = document.getElementById('sm-recipient-summary');
    const repliesList = document.getElementById('sm-replies-list');
    const repliesCount = document.getElementById('sm-replies-count');

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function applyRecipientFilter() {
        const q = (searchInput.value || '').trim().toLowerCase();
        const status = statusSelect.value;
        let visible = 0;

        recipientsBody.querySelectorAll('tr').forEach((row) => {
            const name = row.dataset.name || '';
            const seen = row.dataset.seen === '1';
            const matchesSearch = !q || name.includes(q);
            const matchesStatus = status === 'all'
                || (status === 'seen' && seen)
                || (status === 'unseen' && !seen);
            const show = matchesSearch && matchesStatus;
            row.classList.toggle('d-none', !show);
            if (show) {
                visible += 1;
            }
        });

        emptyFilter.classList.toggle('d-none', visible > 0);
    }

    function renderRecipients(recipients) {
        recipientsBody.innerHTML = recipients.map((recipient) => {
            const seenLabel = recipient.seen_at_label
                ? `<span class="text-success">${escapeHtml(recipient.seen_at_label)}</span>`
                : '<span class="text-muted">Not yet</span>';
            const dismissedLabel = recipient.dismissed_at_label
                ? escapeHtml(recipient.dismissed_at_label)
                : '—';

            return `<tr data-name="${escapeHtml((recipient.name || '').toLowerCase())}" data-seen="${recipient.is_seen ? '1' : '0'}">
                <td>${escapeHtml(recipient.name)}</td>
                <td>${escapeHtml(recipient.department)}</td>
                <td>${seenLabel}</td>
                <td>${dismissedLabel}</td>
            </tr>`;
        }).join('');

        applyRecipientFilter();
    }

    function initialsFromName(name) {
        return String(name || '')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || '?';
    }

    function renderReplies(replies) {
        if (!canViewReplies || !repliesList) {
            return;
        }

        if (repliesCount) {
            repliesCount.textContent = String(replies.length);
        }

        if (!replies.length) {
            repliesList.innerHTML = `
                <div class="sm-replies-empty">
                    <i class="fa-light fa-comment-dots"></i>
                    <p class="mb-0">No replies yet.</p>
                </div>`;
            return;
        }

        repliesList.innerHTML = replies.map((reply) => `
            <article class="sm-reply-item">
                <div class="sm-reply-item__avatar" aria-hidden="true">${escapeHtml(initialsFromName(reply.user_name))}</div>
                <div class="sm-reply-item__content">
                    <div class="sm-reply-item__head">
                        <span class="sm-reply-item__name">${escapeHtml(reply.user_name)}</span>
                        <span class="sm-reply-item__time">${escapeHtml(reply.created_at_label || '')}</span>
                    </div>
                    <div class="sm-reply-item__body">${escapeHtml(reply.body).replaceAll('\n', '<br>')}</div>
                </div>
            </article>
        `).join('');
    }

    async function refreshLive() {
        refreshBtn.disabled = true;
        refreshBtn.classList.add('disabled');
        try {
            const response = await fetch(liveUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error('Failed to refresh');
            }
            const data = await response.json();
            renderRecipients(data.recipients || []);
            if (canViewReplies) {
                renderReplies(Array.isArray(data.replies) ? data.replies : []);
            }
            if (seenBadge) {
                seenBadge.textContent = `${data.seen_count} / ${data.recipient_count} seen`;
            }
            if (recipientSummary) {
                recipientSummary.textContent = `${data.seen_count} seen · ${data.recipient_count - data.seen_count} pending`;
            }
        } catch (e) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Refresh failed', text: e.message || '' });
            }
        } finally {
            refreshBtn.disabled = false;
            refreshBtn.classList.remove('disabled');
        }
    }

    searchInput.addEventListener('input', applyRecipientFilter);
    statusSelect.addEventListener('change', applyRecipientFilter);
    refreshBtn.addEventListener('click', refreshLive);

    const deactivateBtn = document.getElementById('sm-deactivate-btn');
    const deactivateForm = document.getElementById('sm-deactivate-form');
    if (deactivateBtn && deactivateForm) {
        deactivateBtn.addEventListener('click', () => {
            Swal.fire({
                title: 'Deactivate message?',
                text: 'Recipients who have not finished this overlay will no longer see it.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                confirmButtonText: 'Yes, deactivate',
                cancelButtonText: 'Cancel',
            }).then((result) => {
                if (result.isConfirmed) {
                    deactivateForm.submit();
                }
            });
        });
    }

    const deleteBtn = document.getElementById('sm-delete-btn');
    const deleteForm = document.getElementById('sm-delete-form');
    if (deleteBtn && deleteForm) {
        deleteBtn.addEventListener('click', () => {
            Swal.fire({
                title: 'Delete message?',
                text: 'This soft-deletes the message and removes it from pending recipients.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel',
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteForm.submit();
                }
            });
        });
    }
})();
</script>
@endpush
