<?php

use App\Http\Controllers\BatchController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\Accounting\AccountingCodeController;
use App\Http\Controllers\Accounting\AccountingGroupCodeController;
use App\Http\Controllers\Accounting\BsGroupingController;
use App\Http\Controllers\Accounting\GroupingController;
use App\Http\Controllers\FishController;
use App\Http\Controllers\FishSizeController;
use App\Http\Controllers\FishSupplierController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\MainController;
use App\Http\Controllers\UnitOfMeasureController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PrsApprovalController;
use App\Http\Controllers\PrsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CanvasingController;
use App\Http\Controllers\PurchasingReportController;
use App\Http\Controllers\PurchaseOrderApprovalController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SupplierComparisonController;
use App\Http\Controllers\SupplierController;
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

    Route::middleware('role:administrator')->prefix('master')->group(function () {
        Route::resource('user', UserController::class);
        // Endpoint khusus untuk DataTables (server-side)
        Route::get('product/datatables', [ProductController::class, 'datatable'])->name('product.datatables');
        Route::resource('product', ProductController::class);
        Route::resource('product-category', ItemCategoryController::class);
        Route::resource('unit-of-measurement', UnitOfMeasureController::class);
        Route::resource('supplier', SupplierController::class);
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
    Route::middleware('role:administrator|purchasing-manager')->prefix('procurement')->group(function () {
        Route::get('/approval', [PrsApprovalController::class, 'index'])->name('prs.approval.index');
        Route::get('/approval/{prs}', [PrsApprovalController::class, 'show'])->name('prs.approval.show');
        Route::post('/approval/{prs}/approve', [PrsApprovalController::class, 'approve'])->name('prs.approve');
        Route::post('/approval/{prs}/hold', [PrsApprovalController::class, 'hold'])->name('prs.hold');
        Route::post('/approval/{prs}/reject', [PrsApprovalController::class, 'reject'])->name('prs.reject');

        Route::get('/supplier-comparison', [SupplierComparisonController::class, 'index'])->name('procurement.supplier-comparison.index');
        Route::post('/supplier-comparison/{prsItem}', [SupplierComparisonController::class, 'select'])->name('procurement.supplier-comparison.select');
        Route::get('/supplier-comparison/{prsItem}/report', [SupplierComparisonController::class, 'report'])->name('procurement.supplier-comparison.report');

        Route::get('/reports', [PurchasingReportController::class, 'index'])->name('procurement.reports.index');
        Route::post('/reports/prs-not-yet-po', [PurchasingReportController::class, 'prsNotYetPo'])->name('procurement.reports.prs-not-yet-po');
        Route::post('/reports/po-not-yet-delivered', [PurchasingReportController::class, 'poNotYetDelivered'])->name('procurement.reports.po-not-yet-delivered');
        Route::post('/reports/po-registered-period', [PurchasingReportController::class, 'poRegisteredPerPeriod'])->name('procurement.reports.po-registered-period');
        Route::post('/reports/po-registered-department', [PurchasingReportController::class, 'poRegisteredPerDepartment'])->name('procurement.reports.po-registered-department');
        Route::post('/reports/po-registered-item', [PurchasingReportController::class, 'poRegisteredPerItem'])->name('procurement.reports.po-registered-item');
        Route::post('/reports/po-registered-supplier', [PurchasingReportController::class, 'poRegisteredPerSupplier'])->name('procurement.reports.po-registered-supplier');
    });

    Route::middleware('role:administrator|canvaser')->group(function () {
        Route::get('/canvasing', [CanvasingController::class, 'index'])->name('canvasing.index');
        Route::get('/canvasing/{prsItem}', [CanvasingController::class, 'show'])->name('canvasing.show');
        Route::get('/canvasing/{prsItem}/report', [CanvasingController::class, 'report'])->name('canvasing.report');
        Route::post('/canvasing/{prsItem}', [CanvasingController::class, 'store'])->name('canvasing.store');
        Route::post('/canvasing/{prsItem}/toggle-direct-purchase', [CanvasingController::class, 'toggleDirectPurchase'])->name('canvasing.toggle-direct-purchase');

        Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
            Route::get('/draft', [PurchaseOrderController::class, 'draft'])->name('draft');
            Route::post('/preview', [PurchaseOrderController::class, 'preview'])->name('preview');
            Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
            Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');
            Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('submit');
        });
    });

    Route::middleware('role:administrator|purchasing-manager')->group(function () {
        Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
            Route::get('/approval', [PurchaseOrderApprovalController::class, 'index'])->name('approval');
            Route::post('/{purchaseOrder}/approve', [PurchaseOrderApprovalController::class, 'approve'])->name('approve');
            Route::post('/{purchaseOrder}/request-changes', [PurchaseOrderApprovalController::class, 'requestChanges'])->name('request-changes');
        });
    });

    Route::middleware('role:administrator|canvaser|purchasing-manager|general-manager')->group(function () {
        Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
            Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
            Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
                ->whereNumber('purchaseOrder')
                ->name('show');
            Route::post('/{purchaseOrder}/number', [PurchaseOrderController::class, 'updateNumber'])
                ->whereNumber('purchaseOrder')
                ->name('number');
            Route::match(['get', 'post'], '/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])
                ->whereNumber('purchaseOrder')
                ->name('print');
        });
    });
    Route::post('/change-password', [UserController::class, 'changePassword'])->name('password.change');
    Route::resource('prs', PrsController::class);
    Route::post('prs/export', [PrsController::class, 'export'])->name('prs.export');
    Route::get('prs/{prs}/print', [PrsController::class, 'print'])->name('prs.print');

    // ===== Notification Routes =====
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
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
