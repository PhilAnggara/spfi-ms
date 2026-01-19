<div class="modal fade text-left modal-borderless" id="export-modal" tabindex="-1"
    role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export PRS to PDF</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('prs.export') }}" method="post" target="_blank" class="form">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="start-month">Start Month</label>
                            <input type="month" class="form-control" id="start-month" name="start_month" value="{{ now()->format('Y-m') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="end-month">End Month</label>
                            <input type="month" class="form-control" id="end-month" name="end_month" value="{{ now()->format('Y-m') }}" required>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">The month range is inclusive based on the PRS Date field.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn icon icon-left btn-light-primary" data-bs-dismiss="modal">
                        <i class="fa-thin fa-xmark"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn icon icon-left btn-primary ms-1">
                        <i class="fa-thin fa-file-pdf"></i>
                        Export PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
