<?php

use App\Http\Controllers\Accounting\AccountingCodeController;
use App\Http\Controllers\Accounting\AccountingDocEntryController;
use App\Http\Controllers\Accounting\AccountingGroupCodeController;
use App\Http\Controllers\Accounting\BsGroupingController;
use App\Http\Controllers\Accounting\CurrencyExchangeRateController;
use App\Http\Controllers\Accounting\GroupingController;
use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\ActiveSessionController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\CanvassingController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FishController;
use App\Http\Controllers\FishSizeController;
use App\Http\Controllers\FishSupplierController;
use App\Http\Controllers\ImReportController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\MainController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PrsApprovalController;
use App\Http\Controllers\PrsController;
use App\Http\Controllers\PurchaseOrderApprovalController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchasingReportController;
use App\Http\Controllers\ReceivingReportController;
use App\Http\Controllers\StoreWithdrawalController;
use App\Http\Controllers\SupplierComparisonController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransferSlipController;
use App\Http\Controllers\UnitOfMeasureController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VesselController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => redirect()->route('login'));
});

Route::middleware('auth')->group(function () {

    Route::get('/', [MainController::class, 'dashboard'])->name('dashboard');

    Route::middleware('role:administrator|it-staff|purchasing-staff|purchasing-manager|engineering-managersss|im-manager|im-supervisor')->prefix('master')->group(function () {
        Route::get('product', [ProductController::class, 'index'])->name('product.index');
        Route::get('product/datatables', [ProductController::class, 'datatable'])->name('product.datatables');
        Route::get('product/{item}/purchase-history', [ProductController::class, 'purchaseHistory'])->name('product.purchase-history');
    });

    Route::middleware('role:administrator|it-staff|engineering-manager|im-manager|im-supervisor')->prefix('master')->group(function () {
        Route::post('product', [ProductController::class, 'store'])->name('product.store');
    });

    Route::middleware('role:administrator|it-staff|im-manager|im-supervisor')->prefix('master')->group(function () {
        Route::put('product/{product}', [ProductController::class, 'update'])->name('product.update');
        Route::delete('product/{product}', [ProductController::class, 'destroy'])->name('product.destroy');
    });

    Route::middleware('role:administrator|it-staff|purchasing-staff|purchasing-manager')->prefix('master')->group(function () {
        Route::get('supplier', [SupplierController::class, 'index'])->name('supplier.index');
        Route::get('supplier/datatables', [SupplierController::class, 'datatable'])->name('supplier.datatables');
        Route::get('supplier/{supplier}/purchase-history', [SupplierController::class, 'purchaseHistory'])->name('supplier.purchase-history');
        Route::post('supplier', [SupplierController::class, 'store'])->name('supplier.store');
        Route::put('supplier/{supplier}', [SupplierController::class, 'update'])->name('supplier.update');
    });

    Route::middleware('role:administrator|it-staff')->prefix('master')->group(function () {
        Route::resource('user', UserController::class);
        Route::get('active-sessions', [ActiveSessionController::class, 'index'])->name('active-sessions.index');
        Route::delete('active-sessions/activity-logs', [ActiveSessionController::class, 'resetActivityLogs'])->name('active-sessions.reset-activity-logs');
        Route::get('active-sessions/{user}', [ActiveSessionController::class, 'show'])->name('active-sessions.show');
        Route::delete('active-sessions/{user}/sessions', [ActiveSessionController::class, 'destroySessions'])->name('active-sessions.destroy-sessions');
        Route::get('employees/id-cards/print', [EmployeeController::class, 'printIdCards'])->name('employees.id-cards.print');
        Route::resource('employees', EmployeeController::class)->except(['create', 'show', 'edit']);
        Route::delete('supplier/{supplier}', [SupplierController::class, 'destroy'])->name('supplier.destroy');
        Route::resource('product-category', ItemCategoryController::class);
        Route::resource('unit-of-measurement', UnitOfMeasureController::class);
        Route::resource('buyer', BuyerController::class);
        Route::resource('currency', CurrencyController::class);
        Route::resource('batch', BatchController::class);
        Route::resource('fish-supplier', FishSupplierController::class);
        Route::resource('vessel', VesselController::class);
        Route::resource('fish', FishController::class);
        Route::post('fish-size', [FishSizeController::class, 'store'])->name('fish-size.store');
        Route::delete('fish-size/{fishSize}', [FishSizeController::class, 'destroy'])->name('fish-size.destroy');

        Route::prefix('accounting')->name('accounting.')->group(function () {
            Route::resource('groupings', GroupingController::class)->except(['create', 'show', 'edit']);
            Route::resource('group-codes', AccountingGroupCodeController::class)->except(['create', 'show', 'edit']);
            Route::resource('codes', AccountingCodeController::class)->except(['create', 'show', 'edit']);
            Route::get('balance-sheet/datatables', [BsGroupingController::class, 'datatable'])->name('balance-sheet.datatables');
            Route::resource('balance-sheet', BsGroupingController::class)->except(['create', 'show', 'edit']);
        });
    });
    Route::middleware('role:administrator|purchasing-manager|purchasing-staff')->prefix('procurement')->group(function () {
        Route::get('/approval', [PrsApprovalController::class, 'index'])->name('prs.approval.index');
        Route::get('/approval/{prs}', [PrsApprovalController::class, 'show'])->name('prs.approval.show');
        Route::post('/approval/{prs}/approve', [PrsApprovalController::class, 'approve'])->name('prs.approve');
        Route::post('/approval/{prs}/reassign', [PrsApprovalController::class, 'reassign'])->name('prs.reassign');
        Route::post('/approval/{prs}/hold', [PrsApprovalController::class, 'hold'])->name('prs.hold');
        Route::post('/approval/{prs}/reject', [PrsApprovalController::class, 'reject'])->name('prs.reject');

        Route::get('/supplier-comparison', [SupplierComparisonController::class, 'index'])->name('procurement.supplier-comparison.index');
        Route::post('/supplier-comparison/{prsItem}', [SupplierComparisonController::class, 'select'])->name('procurement.supplier-comparison.select');
        Route::post('/supplier-comparison/{prsItem}/reject', [SupplierComparisonController::class, 'reject'])->name('procurement.supplier-comparison.reject');
        Route::get('/supplier-comparison/{prsItem}/report', [SupplierComparisonController::class, 'report'])->name('procurement.supplier-comparison.report');

        Route::get('/reports', [PurchasingReportController::class, 'index'])->name('procurement.reports.index');
        Route::post('/reports/prs-not-yet-po', [PurchasingReportController::class, 'prsNotYetPo'])->name('procurement.reports.prs-not-yet-po');
        Route::post('/reports/po-not-yet-delivered', [PurchasingReportController::class, 'poNotYetDelivered'])->name('procurement.reports.po-not-yet-delivered');
        Route::post('/reports/po-registered-period', [PurchasingReportController::class, 'poRegisteredPerPeriod'])->name('procurement.reports.po-registered-period');
        Route::post('/reports/po-registered-department', [PurchasingReportController::class, 'poRegisteredPerDepartment'])->name('procurement.reports.po-registered-department');
        Route::post('/reports/po-registered-item', [PurchasingReportController::class, 'poRegisteredPerItem'])->name('procurement.reports.po-registered-item');
        Route::post('/reports/po-registered-supplier', [PurchasingReportController::class, 'poRegisteredPerSupplier'])->name('procurement.reports.po-registered-supplier');
        Route::post('/reports/purchasing-lead-time', [PurchasingReportController::class, 'purchasingLeadTime'])->name('procurement.reports.purchasing-lead-time');
    });

    Route::middleware('role:administrator|finance-manager|finance-supervisor|finance-staff|accounting-manager|accounting-supervisor|accounting-staff')->prefix('accounting')->group(function () {
        Route::get('/reports', [AccountingReportController::class, 'index'])->name('accounting.reports.index');
        Route::post('/reports/stock-card', [AccountingReportController::class, 'stockCard'])->name('accounting.reports.stock-card');
        Route::post('/reports/transaction', [AccountingReportController::class, 'transaction'])->name('accounting.reports.transaction');
        Route::post('/reports/restatement', [AccountingReportController::class, 'restatement'])->name('accounting.reports.restatement');
        Route::post('/reports/stock-card-count', [AccountingReportController::class, 'stockCardCount'])->name('accounting.reports.stock-card-count');
        Route::post('/reports/document-summary', [AccountingReportController::class, 'documentSummary'])->name('accounting.reports.document-summary');
        Route::post('/reports/purchase', [AccountingReportController::class, 'purchase'])->name('accounting.reports.purchase');
    });

    Route::middleware('role:administrator|im-manager|im-supervisor|im-staff')->prefix('im')->group(function () {
        Route::get('/reports', [ImReportController::class, 'index'])->name('im.reports.index');
        Route::post('/reports/stock-inventory', [ImReportController::class, 'stockInventory'])->name('im.reports.stock-inventory');
        Route::post('/reports/transaction', [ImReportController::class, 'transaction'])->name('im.reports.transaction');
        Route::post('/reports/receiving-register', [ImReportController::class, 'receivingRegister'])->name('im.reports.receiving-register');
        Route::post('/reports/sws-register', [ImReportController::class, 'swsRegister'])->name('im.reports.sws-register');
        Route::post('/reports/transfer-register', [ImReportController::class, 'transferRegister'])->name('im.reports.transfer-register');
        Route::post('/reports/delivery-register', [ImReportController::class, 'deliveryRegister'])->name('im.reports.delivery-register');
    });

    Route::middleware('role:administrator|finance-manager|finance-supervisor|finance-staff|accounting-manager|accounting-supervisor|accounting-staff')
        ->prefix('accounting')
        ->name('accounting.')
        ->group(function () {
            Route::get('exchange-rates', [CurrencyExchangeRateController::class, 'index'])->name('exchange-rates.index');
            Route::get('doc-entries', [AccountingDocEntryController::class, 'index'])->name('doc-entries.index');
            Route::get('doc-entries/account-lookup', [AccountingDocEntryController::class, 'lookupAccount'])->name('doc-entries.account-lookup');
            Route::get('doc-entries/transaction/{transaction}', [AccountingDocEntryController::class, 'showTransaction'])
                ->name('doc-entries.transaction');
            Route::get('doc-entries/{docType}/{id}', [AccountingDocEntryController::class, 'show'])
                ->where(['docType' => 'rr|dr', 'id' => '[0-9]+'])
                ->name('doc-entries.show');
            Route::put('doc-entries/{transaction}', [AccountingDocEntryController::class, 'update'])->name('doc-entries.update');
        });

    Route::middleware('role:administrator|finance-manager|finance-supervisor|accounting-manager|accounting-supervisor')
        ->prefix('accounting')
        ->name('accounting.')
        ->group(function () {
            Route::post('exchange-rates', [CurrencyExchangeRateController::class, 'store'])->name('exchange-rates.store');
        });

    Route::middleware('role:administrator|purchasing-staff')->group(function () {
        Route::get('/canvassing', [CanvassingController::class, 'index'])->name('canvassing.index');
        Route::get('/canvassing/reports/print', [CanvassingController::class, 'printReports'])->name('canvassing.reports.print');
        Route::get('/canvassing/{prsItem}', [CanvassingController::class, 'show'])->name('canvassing.show');
        Route::get('/canvassing/{prsItem}/report', [CanvassingController::class, 'report'])->name('canvassing.report');
        Route::post('/canvassing/{prsItem}', [CanvassingController::class, 'store'])->name('canvassing.store');
        Route::post('/canvassing/{prsItem}/toggle-direct-purchase', [CanvassingController::class, 'toggleDirectPurchase'])->name('canvassing.toggle-direct-purchase');
        Route::post('/canvassing/{prsItem}/hold', [CanvassingController::class, 'hold'])->name('canvassing.hold');

        Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
            Route::get('/draft', [PurchaseOrderController::class, 'draft'])->name('draft');
            Route::post('/preview', [PurchaseOrderController::class, 'preview'])->name('preview');
            Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
            Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');
            Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('submit');
            Route::post('/{purchaseOrder}/withdraw', [PurchaseOrderController::class, 'withdraw'])->name('withdraw');
            Route::delete('/{purchaseOrder}/items/{purchaseOrderItem}', [PurchaseOrderController::class, 'destroyItem'])
                ->name('items.destroy');
        });
    });

    Route::middleware('role:administrator|purchasing-manager')->group(function () {
        Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
            Route::get('/approval', [PurchaseOrderApprovalController::class, 'index'])->name('approval');
            Route::post('/{purchaseOrder}/approve', [PurchaseOrderApprovalController::class, 'approve'])->name('approve');
            Route::post('/{purchaseOrder}/request-changes', [PurchaseOrderApprovalController::class, 'requestChanges'])->name('request-changes');
        });
    });

    Route::middleware('role:administrator|purchasing-staff|purchasing-manager|general-manager')->group(function () {
        Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
            Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
            Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
                ->whereNumber('purchaseOrder')
                ->name('show');
            Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
                ->whereNumber('purchaseOrder')
                ->name('cancel');
            Route::post('/{purchaseOrder}/number', [PurchaseOrderController::class, 'updateNumber'])
                ->whereNumber('purchaseOrder')
                ->name('number');
            Route::match(['get', 'post'], '/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])
                ->whereNumber('purchaseOrder')
                ->name('print');
        });
    });

    Route::middleware('permission:view-rr')->prefix('receiving-reports')->name('receiving-reports.')->group(function () {
        Route::get('/', [ReceivingReportController::class, 'index'])->name('index');
        Route::match(['get', 'post'], '/{receivingReport}/print', [ReceivingReportController::class, 'print'])->name('print');
    });

    Route::middleware('role:administrator|im-manager|im-supervisor|im-staff')->prefix('receiving-reports')->name('receiving-reports.')->group(function () {
        Route::get('/po-by-number', [ReceivingReportController::class, 'poByNumber'])->name('po-by-number');
        Route::post('/', [ReceivingReportController::class, 'store'])->name('store');
        Route::put('/{receivingReport}', [ReceivingReportController::class, 'update'])->name('update');
        Route::delete('/{receivingReport}', [ReceivingReportController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('stores-withdrawals')->name('stores-withdrawals.')->group(function () {
        Route::get('/', [StoreWithdrawalController::class, 'index'])->name('index');
        Route::get('/create', [StoreWithdrawalController::class, 'create'])->name('create');
        Route::get('/capex-lines', [StoreWithdrawalController::class, 'capexLines'])->name('capex-lines');
        Route::post('/', [StoreWithdrawalController::class, 'store'])->name('store');
        Route::get('/{storeWithdrawal}/print', [StoreWithdrawalController::class, 'print'])->name('print');
        Route::get('/{storeWithdrawal}', [StoreWithdrawalController::class, 'show'])->name('show');
        Route::get('/{storeWithdrawal}/edit', [StoreWithdrawalController::class, 'edit'])->name('edit');
        Route::put('/{storeWithdrawal}', [StoreWithdrawalController::class, 'update'])->name('update');
        Route::delete('/{storeWithdrawal}', [StoreWithdrawalController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('permission:view-transfer')->prefix('transfer-slips')->name('transfer-slips.')->group(function () {
        Route::get('/', [TransferSlipController::class, 'index'])->name('index');
        Route::match(['get', 'post'], '/{transferSlip}/print', [TransferSlipController::class, 'print'])->name('print');
    });

    Route::middleware('permission:create-transfer')->prefix('transfer-slips')->name('transfer-slips.')->group(function () {
        Route::get('/sws-by-number', [TransferSlipController::class, 'swsByNumber'])->name('sws-by-number');
        Route::post('/', [TransferSlipController::class, 'store'])->name('store');
    });

    Route::middleware('permission:update-transfer')->prefix('transfer-slips')->name('transfer-slips.')->group(function () {
        Route::put('/{transferSlip}', [TransferSlipController::class, 'update'])->name('update');
    });

    Route::middleware('permission:delete-transfer')->prefix('transfer-slips')->name('transfer-slips.')->group(function () {
        Route::delete('/{transferSlip}', [TransferSlipController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('permission:view-delivery')->prefix('deliveries')->name('deliveries.')->group(function () {
        Route::get('/', [DeliveryController::class, 'index'])->name('index');
        Route::get('/{delivery}/print', [DeliveryController::class, 'print'])->name('print');
    });

    Route::middleware('permission:create-delivery')->prefix('deliveries')->name('deliveries.')->group(function () {
        Route::get('/create', [DeliveryController::class, 'create'])->name('create');
        Route::post('/', [DeliveryController::class, 'store'])->name('store');
    });

    Route::middleware('permission:delete-delivery')->prefix('deliveries')->name('deliveries.')->group(function () {
        Route::delete('/{delivery}', [DeliveryController::class, 'destroy'])->name('destroy');
    });

    Route::post('/change-password', [UserController::class, 'changePassword'])->name('password.change');
    Route::resource('prs', PrsController::class);
    Route::post('prs/export', [PrsController::class, 'export'])->name('prs.export');
    Route::post('prs/export-by-department', [PrsController::class, 'exportByDepartment'])->name('prs.export-by-department');
    Route::get('prs/{prs}/print', [PrsController::class, 'print'])->name('prs.print');

    // ===== Notification Routes =====
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/recent', [NotificationController::class, 'getRecent'])->name('notifications.recent');
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount'])->name('notifications.unread-count');
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::post('/clear-read', [NotificationController::class, 'clearRead'])->name('notifications.clear-read');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/cek-csv', [MainController::class, 'cekCsv'])->name('cek.csv');

});

require __DIR__.'/auth.php';
