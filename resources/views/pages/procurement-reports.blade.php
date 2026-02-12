@extends('layouts.app')
@section('title', ' | Purchasing Reports')

@section('content')
@php
    $today = now()->toDateString();
@endphp
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Purchasing Reports</h3>
                <p class="text-muted mb-0">Export purchasing reports in PDF or Excel.</p>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">PRS Not Yet PO</h5>
                        <form method="post" action="{{ route('procurement.reports.prs-not-yet-po') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="prs-not-po-date">Date To</label>
                                <input type="date" id="prs-not-po-date" name="date_to" class="form-control" value="{{ $today }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="prs-not-po-canvaser">Canvasser</label>
                                <select id="prs-not-po-canvaser" name="canvaser_id" class="form-select">
                                    <option value="">All</option>
                                    @foreach ($canvasers as $canvaser)
                                        <option value="{{ $canvaser->id }}">{{ $canvaser->name }}</option>
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

            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">PO Not Yet Delivered</h5>
                        <form method="post" action="{{ route('procurement.reports.po-not-yet-delivered') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="po-not-delivered-date">Date To</label>
                                <input type="date" id="po-not-delivered-date" name="date_to" class="form-control" value="{{ $today }}" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="po-not-delivered-canvaser">Canvasser</label>
                                <select id="po-not-delivered-canvaser" name="canvaser_id" class="form-select">
                                    <option value="">All</option>
                                    @foreach ($canvasers as $canvaser)
                                        <option value="{{ $canvaser->id }}">{{ $canvaser->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="po-not-delivered-type">PO Type</label>
                                <select id="po-not-delivered-type" name="po_type" class="form-select" required>
                                    <option value="cash">Cash</option>
                                    <option value="credit">Credit</option>
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

            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">PO Registered Per Period</h5>
                        <form method="post" action="{{ route('procurement.reports.po-registered-period') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="po-period-from">Date From</label>
                                <input type="date" id="po-period-from" name="date_from" class="form-control" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="po-period-to">Date To</label>
                                <input type="date" id="po-period-to" name="date_to" class="form-control" value="{{ $today }}" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="po-period-type">PO Type</label>
                                <select id="po-period-type" name="po_type" class="form-select" required>
                                    <option value="all">All Purchase Order</option>
                                    <option value="confirmatory">Confirmatory Only</option>
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

            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">PO Registered Per Department</h5>
                        <form method="post" action="{{ route('procurement.reports.po-registered-department') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="po-dept-from">Date From</label>
                                <input type="date" id="po-dept-from" name="date_from" class="form-control" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="po-dept-to">Date To</label>
                                <input type="date" id="po-dept-to" name="date_to" class="form-control" value="{{ $today }}" required>
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

            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">PO Registered Per Item</h5>
                        <form method="post" action="{{ route('procurement.reports.po-registered-item') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="po-item-asof">As of</label>
                                <input type="date" id="po-item-asof" name="as_of" class="form-control" value="{{ $today }}" required>
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

            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">PO Registered Per Supplier</h5>
                        <form method="post" action="{{ route('procurement.reports.po-registered-supplier') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="po-supplier-asof">As of</label>
                                <input type="date" id="po-supplier-asof" name="as_of" class="form-control" value="{{ $today }}" required>
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
