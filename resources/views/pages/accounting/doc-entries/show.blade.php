@extends('layouts.app')
@section('title', ' | Doc Entry')

@section('content')
@php
    $docTypeLabels = [
        'RR' => 'Receiving Report',
        'DR' => 'Delivery Receipt',
    ];
    $docTypeLabel = $docTypeLabels[$docType] ?? $docType;
@endphp

<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4 g-3 align-items-center">
            <div class="col-12 col-lg-8">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h3 class="mb-0">{{ $docTypeLabel }} Entry</h3>
                    @if ($isEncoded)
                        <span class="badge rounded-pill bg-light-success text-success">Encoded</span>
                    @else
                        <span class="badge rounded-pill bg-light-warning text-warning">Draft</span>
                    @endif
                </div>
            </div>
            <div class="col-12 col-lg-4 text-lg-end">
                <a href="{{ route('accounting.doc-entries.index') }}" class="btn btn-light-secondary icon icon-left">
                    <i class="fa-light fa-arrow-left"></i>
                    Back to List
                </a>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <section class="section">
        @include('pages.accounting.doc-entries.partials.entry-panel', ['inModal' => false])
    </section>
</div>
@endsection
