@extends('layouts.app')
@section('title', ' | Screen Message')

@section('content')
<div class="page-heading po-page sc-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-start">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">{{ $message->title }}</h3>
                    <p class="text-muted mb-0">
                        Sent by {{ $message->user?->name ?? '—' }}
                        · {{ $message->created_at?->format('d M Y H:i') }}
                    </p>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="prs-create-actions justify-content-lg-end">
                    <a href="{{ route('screen-messages.index') }}" class="btn btn-light-secondary icon icon-left">
                        <i class="fa-light fa-arrow-left"></i>
                        Back
                    </a>
                    @if ($canDeactivate && $message->is_active)
                        <form method="POST" action="{{ route('screen-messages.deactivate', $message) }}"
                              onsubmit="return confirm('Deactivate this message for all pending recipients?')">
                            @csrf
                            <button type="submit" class="btn btn-warning icon icon-left">
                                <i class="fa-regular fa-ban"></i>
                                Deactivate
                            </button>
                        </form>
                    @endif
                    @if ($canDelete)
                        <form method="POST" action="{{ route('screen-messages.destroy', $message) }}"
                              onsubmit="return confirm('Delete this message? Recipients who have not seen it will no longer receive it.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger icon icon-left">
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
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent">
                        <span class="sc-section-title"><i class="fa-regular fa-message-lines"></i> Message</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 p-3 rounded bg-light" style="white-space: pre-wrap;">{{ $message->body }}</div>
                        <dl class="row mb-0">
                            <dt class="col-5">Mode</dt>
                            <dd class="col-7">{{ $message->display_mode->label() }}</dd>
                            <dt class="col-5">Duration</dt>
                            <dd class="col-7">{{ $message->duration_seconds ? $message->duration_seconds.'s' : '—' }}</dd>
                            <dt class="col-5">Audience</dt>
                            <dd class="col-7">{{ $message->audience_type->label() }}</dd>
                            <dt class="col-5">Allow reply</dt>
                            <dd class="col-7">{{ $message->allow_reply ? 'Yes' : 'No' }}</dd>
                            <dt class="col-5">Status</dt>
                            <dd class="col-7">
                                @if ($message->is_active)
                                    <span class="badge bg-light-success text-success">Active</span>
                                @else
                                    <span class="badge bg-light-warning text-warning">Inactive</span>
                                    @if ($message->deactivated_at)
                                        <div class="small text-muted mt-1">
                                            {{ $message->deactivated_at->format('d M Y H:i') }}
                                            @if ($message->deactivatedBy)
                                                by {{ $message->deactivatedBy->name }}
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            </dd>
                            <dt class="col-5">Seen</dt>
                            <dd class="col-7">{{ $seenCount }} / {{ $recipientCount }}</dd>
                        </dl>
                    </div>
                </div>

                @if ($canViewReplies)
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-transparent">
                            <span class="sc-section-title"><i class="fa-regular fa-comments"></i> Replies</span>
                        </div>
                        <div class="card-body">
                            @forelse ($message->replies as $reply)
                                <div class="border rounded p-3 mb-2">
                                    <div class="fw-semibold">{{ $reply->user?->name ?? '—' }}</div>
                                    <div class="small text-muted mb-1">{{ $reply->created_at?->format('d M Y H:i') }}</div>
                                    <div style="white-space: pre-wrap;">{{ $reply->body }}</div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No replies yet.</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="sc-section-title mb-0"><i class="fa-regular fa-users"></i> Recipients</span>
                        <span class="badge bg-light-primary">{{ $seenCount }} seen · {{ $recipientCount - $seenCount }} pending</span>
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
                                <tbody>
                                    @foreach ($message->recipients->sortBy(fn ($r) => $r->user?->name) as $recipient)
                                        <tr>
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
