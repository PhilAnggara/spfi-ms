<div class="card shadow-sm border-0">
    <div class="card-body position-relative">
        <div id="inventory-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
            <div class="text-center">
                <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                <div class="mt-2 text-muted">Loading data...</div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">Inventory Queue</h5>
                <span class="badge bg-light-warning text-warning">Pending {{ number_format($summary['pending']) }}</span>
                <span class="badge bg-light-success text-success">Encoded {{ number_format($summary['encoded']) }}</span>
                <span class="badge bg-light-secondary text-secondary">Total {{ number_format($summary['total']) }}</span>
            </div>
            <span class="badge bg-light-primary" id="inventory-filter-result">{{ $documents->total() }} records</span>
        </div>

        <div class="po-status-chip-group mb-3">
            <button type="button" class="po-status-chip {{ ($filters['status'] ?? 'pending') === 'pending' ? 'active' : '' }}" data-status-value="pending">
                <i class="fa-light fa-hourglass-half"></i>
                Pending
            </button>
            <button type="button" class="po-status-chip {{ ($filters['status'] ?? '') === 'encoded' ? 'active' : '' }}" data-status-value="encoded">
                <i class="fa-light fa-circle-check"></i>
                Encoded
            </button>
            <button type="button" class="po-status-chip {{ ($filters['status'] ?? '') === 'all' ? 'active' : '' }}" data-status-value="all">
                <i class="fa-light fa-layer-group"></i>
                All
            </button>
        </div>

        @include('pages.accounting.inventory-transactions.partials.list-panel')
    </div>
</div>
