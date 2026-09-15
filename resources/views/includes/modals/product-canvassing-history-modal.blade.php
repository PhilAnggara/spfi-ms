<div class="modal fade text-left modal-borderless" id="product-canvassing-history-modal" tabindex="-1" role="dialog" aria-labelledby="productCanvassingHistoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="productCanvassingHistoryLabel">Canvassing History</h5>
                    <p class="text-muted small mb-0" id="product-canvassing-history-meta"></p>
                </div>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="product-canvassing-history-summary" class="alert alert-light border mb-3 d-none">
                    <div class="fw-semibold mb-1">Canvass Price Summary</div>
                    <div id="product-canvassing-history-summary-body" class="d-flex flex-wrap gap-2"></div>
                    <div class="text-muted small mt-1 mb-0">Simple average across all supplier quotes (including quotes not selected for PO).</div>
                </div>
                <table class="table table-striped text-center text-nowrap w-100" id="product-canvassing-history-table">
                    <thead>
                        <tr>
                            <th>Canvass Date</th>
                            <th>PRS Number</th>
                            <th>Supplier Code</th>
                            <th class="text-start">Supplier Name</th>
                            <th class="text-end">Unit Price</th>
                            <th>Status</th>
                            <th>TOP</th>
                            <th>TOD</th>
                            <th>Canvasser</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="modal-footer d-flex flex-wrap gap-2 justify-content-between">
                <form id="canvassing-history-export-form" method="post" action="" class="d-flex flex-wrap gap-2 mb-0">
                    @csrf
                    <button type="submit" name="format" value="pdf" formtarget="_blank" class="btn btn-sm icon icon-left btn-outline-secondary" disabled>
                        <i class="fa-thin fa-file-pdf"></i>
                        Export PDF
                    </button>
                    <button type="submit" name="format" value="excel" class="btn btn-sm icon icon-left btn-success" disabled>
                        <i class="fa-thin fa-file-spreadsheet"></i>
                        Export Excel
                    </button>
                </form>
                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
