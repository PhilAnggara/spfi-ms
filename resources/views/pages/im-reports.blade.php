@extends('layouts.app')
@section('title', ' | IM Reports')

@section('content')
@php
    $today = now();
    $defaultDateTo = $today->toDateString();
    $defaultDateFrom = $today->copy()->subMonth()->toDateString();
@endphp
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>IM Reports</h3>
                <p class="text-muted mb-0">Export inventory management reports in PDF or Excel.</p>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="row g-4">
            <!-- Stock Inventory per Category -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Stock Inventory per Category</h5>
                        <form method="post" action="{{ route('im.reports.stock-inventory') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="stock-inventory-as-of">As Of</label>
                                <input type="date" id="stock-inventory-as-of" name="as_of" class="form-control" value="{{ $defaultDateTo }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="stock-inventory-category">Item Category</label>
                                <select id="stock-inventory-category" name="category" class="form-select" required>
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

            <!-- Transaction Report per Category -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Transaction Report per Category</h5>
                        <form method="post" action="{{ route('im.reports.transaction') }}" class="row g-3">
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
                                <label class="form-label" for="transaction-category">Item Category</label>
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

            <!-- Receiving Report Register -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Receiving Report Register</h5>
                        <form method="post" action="{{ route('im.reports.receiving-register') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="receiving-from">Date From</label>
                                <input type="date" id="receiving-from" name="date_from" class="form-control" value="{{ $defaultDateFrom }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="receiving-to">Date To</label>
                                <input type="date" id="receiving-to" name="date_to" class="form-control" value="{{ $defaultDateTo }}" required>
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

            <!-- Stores Withdrawal Slip Register -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Stores Withdrawal Slip Register</h5>
                        <form method="post" action="{{ route('im.reports.sws-register') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="sws-from">Date From</label>
                                <input type="date" id="sws-from" name="date_from" class="form-control" value="{{ $defaultDateFrom }}" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="sws-to">Date To</label>
                                <input type="date" id="sws-to" name="date_to" class="form-control" value="{{ $defaultDateTo }}" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="sws-department">Department</label>
                                <select id="sws-department" name="department_id" class="form-select">
                                    <option value="">All departments</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->code }} - {{ $department->name }}</option>
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

            <!-- Transfer Slip Register -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Transfer Slip Register</h5>
                        <form method="post" action="{{ route('im.reports.transfer-register') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="transfer-from">Date From</label>
                                <input type="date" id="transfer-from" name="date_from" class="form-control" value="{{ $defaultDateFrom }}" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="transfer-to">Date To</label>
                                <input type="date" id="transfer-to" name="date_to" class="form-control" value="{{ $defaultDateTo }}" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="transfer-ts-type">TS Type</label>
                                <select id="transfer-ts-type" name="ts_type" class="form-select" required>
                                    <option value="">Select TS Type</option>
                                    @foreach ($tsTypes as $tsType)
                                        <option value="{{ $tsType }}">{{ $tsType }}</option>
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

            <!-- Delivery Receipt Register -->
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">Delivery Receipt Register</h5>
                        <form method="post" action="{{ route('im.reports.delivery-register') }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="delivery-from">Date From</label>
                                <input type="date" id="delivery-from" name="date_from" class="form-control" value="{{ $defaultDateFrom }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="delivery-to">Date To</label>
                                <input type="date" id="delivery-to" name="date_to" class="form-control" value="{{ $defaultDateTo }}" required>
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
