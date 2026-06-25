<div class="modal fade text-left modal-borderless" id="product-purchase-history-modal" tabindex="-1" role="dialog" aria-labelledby="productPurchaseHistoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="productPurchaseHistoryLabel">Purchase History</h5>
                    <p class="text-muted small mb-0" id="product-purchase-history-meta"></p>
                </div>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="product-purchase-history-summary" class="alert alert-light border mb-3 d-none">
                    <div class="fw-semibold mb-1">Average Unit Price</div>
                    <div id="product-purchase-history-summary-body" class="d-flex flex-wrap gap-2"></div>
                    <div class="text-muted small mt-1 mb-0">Weighted average by quantity, grouped by currency.</div>
                </div>
                <table class="table table-striped text-center text-nowrap w-100" id="product-purchase-history-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>PO Date</th>
                            <th>Currency</th>
                            <th>Supplier Code</th>
                            <th>Supplier Name</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th>Canvasser</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
