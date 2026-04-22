@extends('layouts.app')
@section('title', ' | Dashboard')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Dashboard</h3>
            </div>
        </div>
    </div>
</div>
<div class="page-content">
    <section class="row">
        <div class="col-12 col-lg-9">
            <div class="row">
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card shadow-sm" data-aos="fade-up" data-aos-delay="0">
                        <div class="card-body px-4 py-4-5">
                            <div class="row">
                                <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                    <div class="stats-icon purple mb-2">
                                        <i class="fa-regular fa-users"></i>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                    <h6 class="text-muted font-semibold">User Accounts</h6>
                                    <h6 class="font-extrabold mb-0">{{ number_format($metrics['user_accounts']) }}</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card shadow-sm" data-aos="fade-up" data-aos-delay="100">
                        <div class="card-body px-4 py-4-5">
                            <div class="row">
                                <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                    <div class="stats-icon blue mb-2">
                                        <i class="fa-regular fa-cart-shopping"></i>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                    <h6 class="text-muted font-semibold">PRS ({{ $dashboardMonthLabel }})</h6>
                                    <h6 class="font-extrabold mb-0">{{ number_format($metrics['prs_this_month']) }}</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card shadow-sm" data-aos="fade-up" data-aos-delay="200">
                        <div class="card-body px-4 py-4-5">
                            <div class="row">
                                <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                    <div class="stats-icon green mb-2">
                                        <i class="fa-regular fa-conveyor-belt-arm"></i>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                    <h6 class="text-muted font-semibold">Approved PO Value</h6>
                                    <h6 class="font-extrabold mb-0">Rp {{ number_format($metrics['po_approved_value_this_month'], 0, ',', '.') }}</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card shadow-sm" data-aos="fade-up" data-aos-delay="300">
                        <div class="card-body px-4 py-4-5">
                            <div class="row">
                                <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                    <div class="stats-icon red mb-2">
                                        <i class="fa-regular fa-ship"></i>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                    <h6 class="text-muted font-semibold">Receiving Reports ({{ $dashboardMonthLabel }})</h6>
                                    <h6 class="font-extrabold mb-0">{{ number_format($metrics['rr_this_month']) }}</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm" data-aos="zoom-in" data-aos-delay="500">
                        <div class="card-header">
                            <h4>Monthly PRS Trend (Last 12 Months)</h4>
                        </div>
                        <div class="card-body">
                            <div id="chart-profile-visit"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-3">
            <div class="card shadow-sm" data-aos="fade-left" data-aos-delay="700">
                <div class="card-header">
                    <h4>PRS Status Distribution</h4>
                </div>
                <div class="card-body">
                    <div id="chart-visitors-profile"></div>
                </div>
            </div>
        </div>
    </section>

    <section class="row mt-2">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm" data-aos="fade-up" data-aos-delay="300">
                <div class="card-header">
                    <h4>Top Suppliers by PO Value</h4>
                </div>
                <div class="card-body">
                    <div id="chart-top-suppliers"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm" data-aos="fade-up" data-aos-delay="400">
                <div class="card-header">
                    <h4>PO Status Distribution</h4>
                </div>
                <div class="card-body">
                    <div id="chart-po-status"></div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('prepend-style')
@endpush
@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/compiled/css/iconly.css') }}">
@endpush
@push('addon-script')
    <script>
        window.dashboardData = @json($dashboardData);
    </script>
        <script src="{{ url('assets/extensions/apexcharts/apexcharts.min.js') }}"></script>
        <script src="{{ url('assets/scripts/modules/dashboard-index.js') }}"></script>
@endpush
