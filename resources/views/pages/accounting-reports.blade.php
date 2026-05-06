@extends('layouts.app')
@section('title', ' | Accounting Reports')

@section('content')
@php
    $today = now();
    $defaultMonth = $today->copy()->subMonth()->format('Y-m');
    $defaultDateTo = $today->toDateString();
    $defaultDateFrom = $today->copy()->subDays(30)->toDateString();
    $monthMin = $today->copy()->subMonths(24)->format('Y-m');
    $monthMax = $today->format('Y-m');

    $categories = [
        'OFFICE SUPPLIES',
        'SPARE PARTS',
        'FACTORY SUPPLIES',
        'CHEMICAL',
        'FUEL',
        'LABEL',
        'CARTON',
        'CAN',
        'RAW MATERIALS',
        'SPICES AND INGREDIENTS',
        'COAL',
        'SLUDGE OIL',
        'LABELING SUPPLIES',
        // 'MATERIAL IN TRANSIT',
        // 'FINISHED GOODS',
        // 'FISH',
    ];
@endphp
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Accounting Reports</h3>
                <p class="text-muted mb-0">Export accounting reports in PDF or Excel.</p>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="row g-4">
            <!-- Stock Card Report -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Stock Card</h5>
                        <form method="post" action="{{ route('accounting.reports.stock-card') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="stock-card-month">Month</label>
                                <input type="month" id="stock-card-month" name="month" class="form-control" value="{{ $defaultMonth }}" min="{{ $monthMin }}" max="{{ $monthMax }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="stock-card-category">Category</label>
                                <select id="stock-card-category" name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button type="submit" name="format" value="pdf" formtarget="_blank" class="btn btn-sm icon icon-left btn-outline-secondary">
                                    <i class="fa-thin fa-file-pdf"></i>
                                    Export PDF
                                </button>
                                <button type="submit" name="format" value="excel" class="btn btn-sm icon icon-left btn-success">
                                    <i class="fa-thin fa-file-spreadsheet"></i>
                                    Export Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Transaction Report -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Transaction</h5>
                        <form method="post" action="{{ route('accounting.reports.transaction') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="transaction-from">Date From</label>
                                <input type="date" id="transaction-from" name="date_from" class="form-control" value="{{ $defaultDateFrom }}" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="transaction-to">Date To</label>
                                <input type="date" id="transaction-to" name="date_to" class="form-control" value="{{ $defaultDateTo }}" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="transaction-category">Category</label>
                                <select id="transaction-category" name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button type="submit" name="format" value="pdf" formtarget="_blank" class="btn btn-sm icon icon-left btn-outline-secondary">
                                    <i class="fa-thin fa-file-pdf"></i>
                                    Export PDF
                                </button>
                                <button type="submit" name="format" value="excel" class="btn btn-sm icon icon-left btn-success">
                                    <i class="fa-thin fa-file-spreadsheet"></i>
                                    Export Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Restatement Report -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Restatement Report</h5>
                        <form method="post" action="{{ route('accounting.reports.restatement') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="restatement-month">Month</label>
                                <input type="month" id="restatement-month" name="month" class="form-control" value="{{ $defaultMonth }}" min="{{ $monthMin }}" max="{{ $monthMax }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="restatement-category">Category</label>
                                <select id="restatement-category" name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button type="submit" name="format" value="pdf" formtarget="_blank" class="btn btn-sm icon icon-left btn-outline-secondary">
                                    <i class="fa-thin fa-file-pdf"></i>
                                    Export PDF
                                </button>
                                <button type="submit" name="format" value="excel" class="btn btn-sm icon icon-left btn-success">
                                    <i class="fa-thin fa-file-spreadsheet"></i>
                                    Export Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Stock Card per Count -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Stock Card per Count</h5>
                        <form method="post" action="{{ route('accounting.reports.stock-card-count') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="stock-card-count-month">Month</label>
                                <input type="month" id="stock-card-count-month" name="month" class="form-control" value="{{ $defaultMonth }}" min="{{ $monthMin }}" max="{{ $monthMax }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="stock-card-count-category">Category</label>
                                <select id="stock-card-count-category" name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button type="submit" name="format" value="pdf" formtarget="_blank" class="btn btn-sm icon icon-left btn-outline-secondary">
                                    <i class="fa-thin fa-file-pdf"></i>
                                    Export PDF
                                </button>
                                <button type="submit" name="format" value="excel" class="btn btn-sm icon icon-left btn-success">
                                    <i class="fa-thin fa-file-spreadsheet"></i>
                                    Export Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Document Summary per Doc -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Document Summary per Doc</h5>
                        <form method="post" action="{{ route('accounting.reports.document-summary') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="doc-summary-from">Date From</label>
                                <input type="date" id="doc-summary-from" name="date_from" class="form-control" value="{{ $defaultDateFrom }}" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="doc-summary-to">Date To</label>
                                <input type="date" id="doc-summary-to" name="date_to" class="form-control" value="{{ $defaultDateTo }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="doc-summary-category">Category</label>
                                <select id="doc-summary-category" name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button type="submit" name="format" value="pdf" formtarget="_blank" class="btn btn-sm icon icon-left btn-outline-secondary">
                                    <i class="fa-thin fa-file-pdf"></i>
                                    Export PDF
                                </button>
                                <button type="submit" name="format" value="excel" class="btn btn-sm icon icon-left btn-success">
                                    <i class="fa-thin fa-file-spreadsheet"></i>
                                    Export Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Purchase Report -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Purchase</h5>
                        <form method="post" action="{{ route('accounting.reports.purchase') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="purchase-from">Date From</label>
                                <input type="date" id="purchase-from" name="date_from" class="form-control" value="{{ $defaultDateFrom }}" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="purchase-to">Date To</label>
                                <input type="date" id="purchase-to" name="date_to" class="form-control" value="{{ $defaultDateTo }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="purchase-category">Category</label>
                                <select id="purchase-category" name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button type="submit" name="format" value="pdf" formtarget="_blank" class="btn btn-sm icon icon-left btn-outline-secondary">
                                    <i class="fa-thin fa-file-pdf"></i>
                                    Export PDF
                                </button>
                                <button type="submit" name="format" value="excel" class="btn btn-sm icon icon-left btn-success">
                                    <i class="fa-thin fa-file-spreadsheet"></i>
                                    Export Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
