@extends('layouts.app')
@section('title', ' | Encode Inventory Transaction')

@section('content')
@php
    $isReadOnly = $transaction->isEncoded() || $transaction->isVoided();
@endphp
<div class="page-heading po-page" id="inv-encode-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-8">
                <div class="po-hero">
                    <h3 class="mb-1">Encode Inventory Transaction</h3>
                    <p class="text-muted mb-0">{{ $displayDocNumber }} · {{ $transaction->doc_type }}</p>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="po-top-actions">
                    <a href="{{ route('accounting.inventory-transactions.index') }}" class="btn btn-light-secondary icon icon-left">
                        <i class="fa-light fa-arrow-left"></i>
                        Back to Queue
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <section class="section">
        @include('pages.accounting.inventory-transactions.partials.encode-panel', [
            'transaction' => $transaction,
            'displayDocNumber' => $displayDocNumber,
            'canEncode' => $canEncode,
            'inModal' => false,
            'queueStats' => $queueStats ?? null,
            'queueFilters' => $queueFilters ?? [],
            'sourceUrl' => $sourceUrl ?? null,
        ])

        @if ($canVoid)
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <h5 class="card-title">Void Transaction</h5>
                    <form method="POST" action="{{ route('accounting.inventory-transactions.void', $transaction) }}" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Reason</label>
                            <textarea name="void_reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Void this encoded transaction?')">Void Transaction</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </section>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/accounting-inventory-encode.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/accounting-inventory-encode.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const page = document.getElementById('inv-encode-page');
            if (page) {
                window.initAccountingInventoryEncodeForm(page);
            }
        });
    </script>
@endpush
