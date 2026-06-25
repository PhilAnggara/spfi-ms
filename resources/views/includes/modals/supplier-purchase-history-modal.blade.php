<div class="modal fade text-left modal-borderless" id="supplier-purchase-history-modal" tabindex="-1" role="dialog" aria-labelledby="supplierPurchaseHistoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="supplierPurchaseHistoryLabel">Supplier Detail</h5>
                    <p class="text-muted small mb-0" id="supplier-purchase-history-meta"></p>
                </div>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3 align-items-stretch">
                    <div class="col-12 col-xl-6">
                        <div id="supplier-detail-panel" class="supplier-detail-panel h-100">
                            <h6 class="supplier-detail-heading">Supplier Information</h6>
                            <div class="supplier-info-grid">
                                <div class="supplier-info-item supplier-info-item--full">
                                    <div class="supplier-info-label">
                                        <i class="fa-light fa-location-dot"></i>
                                        Address
                                    </div>
                                    <div id="supplier-detail-address" class="supplier-info-value">-</div>
                                </div>
                                <div class="supplier-info-item">
                                    <div class="supplier-info-label">
                                        <i class="fa-light fa-phone"></i>
                                        Phone
                                    </div>
                                    <div id="supplier-detail-phone" class="supplier-info-value">-</div>
                                </div>
                                <div class="supplier-info-item">
                                    <div class="supplier-info-label">
                                        <i class="fa-light fa-fax"></i>
                                        Fax
                                    </div>
                                    <div id="supplier-detail-fax" class="supplier-info-value">-</div>
                                </div>
                                <div class="supplier-info-item supplier-info-item--full">
                                    <div class="supplier-info-label">
                                        <i class="fa-light fa-address-card"></i>
                                        Contact Person
                                    </div>
                                    <div id="supplier-detail-contact" class="supplier-info-value">-</div>
                                </div>
                                <div class="supplier-info-item supplier-info-item--full">
                                    <div class="supplier-info-label">
                                        <i class="fa-light fa-envelope"></i>
                                        Email
                                    </div>
                                    <div id="supplier-detail-email" class="supplier-info-value">-</div>
                                </div>
                                <div class="supplier-info-item supplier-info-item--full">
                                    <div class="supplier-info-label">
                                        <i class="fa-light fa-note-sticky"></i>
                                        Remarks
                                    </div>
                                    <div id="supplier-detail-remarks" class="supplier-info-value">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <div id="supplier-purchase-history-summary" class="supplier-summary-panel h-100">
                            <h6 class="supplier-detail-heading">Purchase Summary</h6>
                            <div id="supplier-purchase-history-summary-body" class="supplier-summary-body"></div>
                            <div class="text-muted small mt-2 mb-0">Grouped by currency. Average is weighted by quantity.</div>
                        </div>
                    </div>
                </div>

                <h6 class="mb-2">Purchase History</h6>
                <table class="table table-striped text-center text-nowrap w-100" id="supplier-purchase-history-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>PO Date</th>
                            <th>Currency</th>
                            <th>Product Code</th>
                            <th>Product Name</th>
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
