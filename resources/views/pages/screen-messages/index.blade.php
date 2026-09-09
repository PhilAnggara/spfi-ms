@extends('layouts.app')
@section('title', ' | Screen Messages')

@section('content')
<div class="page-heading po-page sc-page sm-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Screen Messages</h3>
                    <p class="text-muted mb-0">Send overlay messages to users’ screens and track who has seen them.</p>
                </div>
            </div>
            @if ($canCreate)
                <div class="col-12 col-lg-5">
                    <div class="po-top-actions sm-page-actions text-lg-end">
                        <a href="{{ route('screen-messages.create') }}" class="btn btn-success icon icon-left">
                            <i class="fa-duotone fa-solid fa-paper-plane"></i>
                            New Message
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success shadow-sm border-0">{{ session('success') }}</div>
    @endif

    <section class="section">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="card-title mb-0">Message history</h5>
                    <span class="badge bg-light-primary">{{ number_format($messages->total()) }} records</span>
                </div>

                <div class="table-responsive sm-index-table-wrap d-none d-md-block">
                    <table class="table table-striped align-middle list-table sc-index-table mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Sender</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Seen</th>
                                <th>Replies</th>
                                <th>Sent</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($messages as $message)
                                <tr>
                                    <td class="fw-semibold">{{ $message->title }}</td>
                                    <td>{{ $message->user?->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-light-secondary text-secondary">
                                            {{ $message->display_mode->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($message->is_active)
                                            <span class="badge bg-light-success text-success">Active</span>
                                        @else
                                            <span class="badge bg-light-warning text-warning">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $message->seen_recipients_count }} / {{ $message->recipients_count }}</td>
                                    <td>{{ $message->replies_count }}</td>
                                    <td>{{ $message->created_at?->format('d M Y H:i') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('screen-messages.show', $message) }}" class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No screen messages yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="sm-index-cards d-md-none">
                    @forelse ($messages as $message)
                        <article class="sm-index-card">
                            <div class="sm-index-card__top">
                                <h6 class="sm-index-card__title mb-1">{{ $message->title }}</h6>
                                <div class="sm-index-card__meta text-muted">
                                    {{ $message->user?->name ?? '—' }}
                                    · {{ $message->created_at?->format('d M Y H:i') }}
                                </div>
                            </div>
                            <div class="sm-index-card__badges">
                                <span class="badge bg-light-secondary text-secondary">{{ $message->display_mode->label() }}</span>
                                @if ($message->is_active)
                                    <span class="badge bg-light-success text-success">Active</span>
                                @else
                                    <span class="badge bg-light-warning text-warning">Inactive</span>
                                @endif
                                <span class="badge bg-light-primary">{{ $message->seen_recipients_count }}/{{ $message->recipients_count }} seen</span>
                                <span class="badge bg-light-info text-info">{{ $message->replies_count }} replies</span>
                            </div>
                            <div class="sm-index-card__actions">
                                <a href="{{ route('screen-messages.show', $message) }}" class="btn btn-sm btn-outline-primary w-100">
                                    View
                                </a>
                            </div>
                        </article>
                    @empty
                        <div class="text-center text-muted py-4">No screen messages yet.</div>
                    @endforelse
                </div>

                <div class="mt-3">
                    {{ $messages->links() }}
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
@endpush
