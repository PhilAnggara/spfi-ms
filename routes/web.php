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
use App\Http\Controllers\OpeningBalanceCorrectionController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PrsApprovalController;
use App\Http\Controllers\PrsController;
use App\Http\Controllers\PurchaseOrderApprovalController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchasingReportController;
use App\Http\Controllers\ReceivingReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StoreWithdrawalController;
use App\Http\Controllers\SupplierComparisonController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransferSlipController;
use App\Http\Controllers\UnitOfMeasureController;
use App\Http\Controllers\UserAccessController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VesselController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => redirect()->route('login'));
});

Route::middleware('auth')->group(function () {

    Route::get('/', [MainController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/charts/open-prs-heatmap', [MainController::class, 'openPrsHeatmap'])
        ->name('dashboard.charts.open-prs-heatmap');

    Route::middleware('permission:view-products')->prefix('master')->group(function () {
        Route::get('product', [ProductController::class, 'index'])->name('product.index');
        Route::get('product/datatables', [ProductController::class, 'datatable'])->name('product.datatables');
    });

    Route::middleware('permission:view-purchase-history')->prefix('master')->group(function () {
        Route::get('product/{item}/purchase-history', [ProductController::class, 'purchaseHistory'])->name('product.purchase-history');
        Route::get('supplier/{supplier}/purchase-history', [SupplierController::class, 'purchaseHistory'])->name('supplier.purchase-history');
    });

    Route::middleware('permission:create-products')->prefix('master')->group(function () {
        Route::get('product/check-code', [ProductController::class, 'checkCode'])->name('product.check-code');
        Route::post('product', [ProductController::class, 'store'])->name('product.store');
    });

    Route::middleware('permission:update-products')->prefix('master')->group(function () {
        Route::put('product/{product}', [ProductController::class, 'update'])->name('product.update');
    });

    Route::middleware('permission:delete-products')->prefix('master')->group(function () {
        Route::delete('product/{product}', [ProductController::class, 'destroy'])->name('product.destroy');
    });

    Route::middleware('permission:view-suppliers')->prefix('master')->group(function () {
        Route::get('supplier', [SupplierController::class, 'index'])->name('supplier.index');
        Route::get('supplier/datatables', [SupplierController::class, 'datatable'])->name('supplier.datatables');
    });

    Route::middleware('permission:create-suppliers')->prefix('master')->group(function () {
        Route::get('supplier/check-code', [SupplierController::class, 'checkCode'])->name('supplier.check-code');
        Route::post('supplier', [SupplierController::class, 'store'])->name('supplier.store');
    });

    Route::middleware('permission:update-suppliers')->prefix('master')->group(function () {
        Route::put('supplier/{supplier}', [SupplierController::class, 'update'])->name('supplier.update');
    });

    Route::middleware('permission:delete-suppliers')->prefix('master')->group(function () {
        Route::delete('supplier/{supplier}', [SupplierController::class, 'destroy'])->name('supplier.destroy');
    });

    Route::middleware('permission:view-users')->prefix('master')->group(function () {
        Route::get('user', [UserController::class, 'index'])->name('user.index');
        Route::get('user/create', [UserController::class, 'create'])->name('user.create');
        Route::get('user/{user}', [UserController::class, 'show'])->name('user.show');
        Route::get('user/{user}/edit', [UserController::class, 'edit'])->name('user.edit');
    });

    Route::middleware('permission:create-users')->prefix('master')->group(function () {
        Route::post('user', [UserController::class, 'store'])->name('user.store');
    });

    Route::middleware('permission:update-users')->prefix('master')->group(function () {
        Route::put('user/{user}', [UserController::class, 'update'])->name('user.update');
        Route::patch('user/{user}', [UserController::class, 'update']);
    });

    Route::middleware('permission:delete-users')->prefix('master')->group(function () {
        Route::delete('user/{user}', [UserController::class, 'destroy'])->name('user.destroy');
    });

    Route::middleware('permission:view-active-sessions')->prefix('master')->group(function () {
        Route::get('active-sessions', [ActiveSessionController::class, 'index'])->name('active-sessions.index');
        Route::get('active-sessions/{user}', [ActiveSessionController::class, 'show'])->name('active-sessions.show');
    });

    Route::middleware('permission:force-logout-users')->prefix('master')->group(function () {
        Route::delete('active-sessions/{user}/sessions', [ActiveSessionController::class, 'destroySessions'])->name('active-sessions.destroy-sessions');
    });

    Route::middleware('permission:reset-activity-logs')->prefix('master')->group(function () {
        Route::delete('active-sessions/activity-logs', [ActiveSessionController::class, 'resetActivityLogs'])->name('active-sessions.reset-activity-logs');
    });

    Route::middleware('permission:view-product-categories')->prefix('master')->group(function () {
        Route::get('product-category', [ItemCategoryController::class, 'index'])->name('product-category.index');
        Route::get('product-category/create', [ItemCategoryController::class, 'create'])->name('product-category.create');
        Route::get('product-category/{product_category}', [ItemCategoryController::class, 'show'])->name('product-category.show');
        Route::get('product-category/{product_category}/edit', [ItemCategoryController::class, 'edit'])->name('product-category.edit');
    });

    Route::middleware('permission:create-product-categories')->prefix('master')->group(function () {
        Route::post('product-category', [ItemCategoryController::class, 'store'])->name('product-category.store');
    });

    Route::middleware('permission:update-product-categories')->prefix('master')->group(function () {
        Route::put('product-category/{product_category}', [ItemCategoryController::class, 'update'])->name('product-category.update');
        Route::patch('product-category/{product_category}', [ItemCategoryController::class, 'update']);
    });

    Route::middleware('permission:delete-product-categories')->prefix('master')->group(function () {
        Route::delete('product-category/{product_category}', [ItemCategoryController::class, 'destroy'])->name('product-category.destroy');
    });

    Route::middleware('permission:view-uom')->prefix('master')->group(function () {
        Route::get('unit-of-measurement', [UnitOfMeasureController::class, 'index'])->name('unit-of-measurement.index');
        Route::get('unit-of-measurement/create', [UnitOfMeasureController::class, 'create'])->name('unit-of-measurement.create');
        Route::get('unit-of-measurement/{unit_of_measurement}', [UnitOfMeasureController::class, 'show'])->name('unit-of-measurement.show');
        Route::get('unit-of-measurement/{unit_of_measurement}/edit', [UnitOfMeasureController::class, 'edit'])->name('unit-of-measurement.edit');
    });

    Route::middleware('permission:create-uom')->prefix('master')->group(function () {
        Route::post('unit-of-measurement', [UnitOfMeasureController::class, 'store'])->name('unit-of-measurement.store');
    });

    Route::middleware('permission:update-uom')->prefix('master')->group(function () {
        Route::put('unit-of-measurement/{unit_of_measurement}', [UnitOfMeasureController::class, 'update'])->name('unit-of-measurement.update');
        Route::patch('unit-of-measurement/{unit_of_measurement}', [UnitOfMeasureController::class, 'update']);
    });

    Route::middleware('permission:delete-uom')->prefix('master')->group(function () {
        Route::delete('unit-of-measurement/{unit_of_measurement}', [UnitOfMeasureController::class, 'destroy'])->name('unit-of-measurement.destroy');
    });

    Route::middleware('permission:view-buyers')->prefix('master')->group(function () {
        Route::get('buyer', [BuyerController::class, 'index'])->name('buyer.index');
        Route::get('buyer/create', [BuyerController::class, 'create'])->name('buyer.create');
        Route::get('buyer/{buyer}', [BuyerController::class, 'show'])->name('buyer.show');
        Route::get('buyer/{buyer}/edit', [BuyerController::class, 'edit'])->name('buyer.edit');
    });

    Route::middleware('permission:create-buyers')->prefix('master')->group(function () {
        Route::post('buyer', [BuyerController::class, 'store'])->name('buyer.store');
    });

    Route::middleware('permission:update-buyers')->prefix('master')->group(function () {
        Route::put('buyer/{buyer}', [BuyerController::class, 'update'])->name('buyer.update');
        Route::patch('buyer/{buyer}', [BuyerController::class, 'update']);
    });

    Route::middleware('permission:delete-buyers')->prefix('master')->group(function () {
        Route::delete('buyer/{buyer}', [BuyerController::class, 'destroy'])->name('buyer.destroy');
    });

    Route::middleware('permission:view-currencies')->prefix('master')->group(function () {
        Route::get('currency', [CurrencyController::class, 'index'])->name('currency.index');
        Route::get('currency/create', [CurrencyController::class, 'create'])->name('currency.create');
        Route::get('currency/{currency}', [CurrencyController::class, 'show'])->name('currency.show');
        Route::get('currency/{currency}/edit', [CurrencyController::class, 'edit'])->name('currency.edit');
    });

    Route::middleware('permission:create-currencies')->prefix('master')->group(function () {
        Route::post('currency', [CurrencyController::class, 'store'])->name('currency.store');
    });

    Route::middleware('permission:update-currencies')->prefix('master')->group(function () {
        Route::put('currency/{currency}', [CurrencyController::class, 'update'])->name('currency.update');
        Route::patch('currency/{currency}', [CurrencyController::class, 'update']);
    });

    Route::middleware('permission:delete-currencies')->prefix('master')->group(function () {
        Route::delete('currency/{currency}', [CurrencyController::class, 'destroy'])->name('currency.destroy');
    });

    Route::middleware('permission:view-batches')->prefix('master')->group(function () {
        Route::get('batch', [BatchController::class, 'index'])->name('batch.index');
        Route::get('batch/create', [BatchController::class, 'create'])->name('batch.create');
        Route::get('batch/{batch}', [BatchController::class, 'show'])->name('batch.show');
        Route::get('batch/{batch}/edit', [BatchController::class, 'edit'])->name('batch.edit');
    });

    Route::middleware('permission:create-batches')->prefix('master')->group(function () {
        Route::post('batch', [BatchController::class, 'store'])->name('batch.store');
    });

    Route::middleware('permission:update-batches')->prefix('master')->group(function () {
        Route::put('batch/{batch}', [BatchController::class, 'update'])->name('batch.update');
        Route::patch('batch/{batch}', [BatchController::class, 'update']);
    });

    Route::middleware('permission:delete-batches')->prefix('master')->group(function () {
        Route::delete('batch/{batch}', [BatchController::class, 'destroy'])->name('batch.destroy');
    });

    Route::middleware('permission:view-fish-suppliers')->prefix('master')->group(function () {
        Route::get('fish-supplier', [FishSupplierController::class, 'index'])->name('fish-supplier.index');
        Route::get('fish-supplier/create', [FishSupplierController::class, 'create'])->name('fish-supplier.create');
        Route::get('fish-supplier/{fish_supplier}', [FishSupplierController::class, 'show'])->name('fish-supplier.show');
        Route::get('fish-supplier/{fish_supplier}/edit', [FishSupplierController::class, 'edit'])->name('fish-supplier.edit');
    });

    Route::middleware('permission:create-fish-suppliers')->prefix('master')->group(function () {
        Route::post('fish-supplier', [FishSupplierController::class, 'store'])->name('fish-supplier.store');
    });

    Route::middleware('permission:update-fish-suppliers')->prefix('master')->group(function () {
        Route::put('fish-supplier/{fish_supplier}', [FishSupplierController::class, 'update'])->name('fish-supplier.update');
        Route::patch('fish-supplier/{fish_supplier}', [FishSupplierController::class, 'update']);
    });

    Route::middleware('permission:delete-fish-suppliers')->prefix('master')->group(function () {
        Route::delete('fish-supplier/{fish_supplier}', [FishSupplierController::class, 'destroy'])->name('fish-supplier.destroy');
    });

    Route::middleware('permission:view-vessels')->prefix('master')->group(function () {
        Route::get('vessel', [VesselController::class, 'index'])->name('vessel.index');
        Route::get('vessel/create', [VesselController::class, 'create'])->name('vessel.create');
        Route::get('vessel/{vessel}', [VesselController::class, 'show'])->name('vessel.show');
        Route::get('vessel/{vessel}/edit', [VesselController::class, 'edit'])->name('vessel.edit');
    });

    Route::middleware('permission:create-vessels')->prefix('master')->group(function () {
        Route::post('vessel', [VesselController::class, 'store'])->name('vessel.store');
    });

    Route::middleware('permission:update-vessels')->prefix('master')->group(function () {
        Route::put('vessel/{vessel}', [VesselController::class, 'update'])->name('vessel.update');
        Route::patch('vessel/{vessel}', [VesselController::class, 'update']);
    });

    Route::middleware('permission:delete-vessels')->prefix('master')->group(function () {
        Route::delete('vessel/{vessel}', [VesselController::class, 'destroy'])->name('vessel.destroy');
    });

    Route::middleware('permission:view-fish')->prefix('master')->group(function () {
        Route::get('fish', [FishController::class, 'index'])->name('fish.index');
        Route::get('fish/create', [FishController::class, 'create'])->name('fish.create');
        Route::get('fish/{fish}', [FishController::class, 'show'])->name('fish.show');
        Route::get('fish/{fish}/edit', [FishController::class, 'edit'])->name('fish.edit');
    });

    Route::middleware('permission:create-fish')->prefix('master')->group(function () {
        Route::post('fish', [FishController::class, 'store'])->name('fish.store');
        Route::post('fish-size', [FishSizeController::class, 'store'])->name('fish-size.store');
    });

    Route::middleware('permission:update-fish')->prefix('master')->group(function () {
        Route::put('fish/{fish}', [FishController::class, 'update'])->name('fish.update');
        Route::patch('fish/{fish}', [FishController::class, 'update']);
    });

    Route::middleware('permission:delete-fish')->prefix('master')->group(function () {
        Route::delete('fish/{fish}', [FishController::class, 'destroy'])->name('fish.destroy');
        Route::delete('fish-size/{fishSize}', [FishSizeController::class, 'destroy'])->name('fish-size.destroy');
    });

    Route::middleware('permission:view-accounting-master')->prefix('master')->name('accounting.')->group(function () {
        Route::get('accounting/groupings', [GroupingController::class, 'index'])->name('groupings.index');
        Route::get('accounting/group-codes', [AccountingGroupCodeController::class, 'index'])->name('group-codes.index');
        Route::get('accounting/codes', [AccountingCodeController::class, 'index'])->name('codes.index');
        Route::get('accounting/balance-sheet', [BsGroupingController::class, 'index'])->name('balance-sheet.index');
        Route::get('accounting/balance-sheet/datatables', [BsGroupingController::class, 'datatable'])->name('balance-sheet.datatables');
    });

    Route::middleware('permission:create-accounting-master')->prefix('master')->name('accounting.')->group(function () {
        Route::post('accounting/groupings', [GroupingController::class, 'store'])->name('groupings.store');
        Route::post('accounting/group-codes', [AccountingGroupCodeController::class, 'store'])->name('group-codes.store');
        Route::post('accounting/codes', [AccountingCodeController::class, 'store'])->name('codes.store');
        Route::post('accounting/balance-sheet', [BsGroupingController::class, 'store'])->name('balance-sheet.store');
    });

    Route::middleware('permission:update-accounting-master')->prefix('master')->name('accounting.')->group(function () {
        Route::put('accounting/groupings/{grouping}', [GroupingController::class, 'update'])->name('groupings.update');
        Route::put('accounting/group-codes/{groupCode}', [AccountingGroupCodeController::class, 'update'])->name('group-codes.update');
        Route::put('accounting/codes/{code}', [AccountingCodeController::class, 'update'])->name('codes.update');
        Route::put('accounting/balance-sheet/{balanceSheet}', [BsGroupingController::class, 'update'])->name('balance-sheet.update');
    });

    Route::middleware('permission:delete-accounting-master')->prefix('master')->name('accounting.')->group(function () {
        Route::delete('accounting/groupings/{grouping}', [GroupingController::class, 'destroy'])->name('groupings.destroy');
        Route::delete('accounting/group-codes/{groupCode}', [AccountingGroupCodeController::class, 'destroy'])->name('group-codes.destroy');
        Route::delete('accounting/codes/{code}', [AccountingCodeController::class, 'destroy'])->name('codes.destroy');
        Route::delete('accounting/balance-sheet/{balanceSheet}', [BsGroupingController::class, 'destroy'])->name('balance-sheet.destroy');
    });

    Route::middleware('permission:view-employees')->prefix('master')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('employees/id-cards/print', [EmployeeController::class, 'printIdCards'])->name('employees.id-cards.print');
    });

    Route::middleware('permission:create-employees')->prefix('master')->group(function () {
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    });

    Route::middleware('permission:update-employees')->prefix('master')->group(function () {
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::patch('employees/{employee}', [EmployeeController::class, 'update']);
    });

    Route::middleware('permission:delete-employees')->prefix('master')->group(function () {
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    });

    Route::middleware('permission:view-roles')->prefix('master')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    });

    Route::middleware('permission:create-roles')->prefix('master')->group(function () {
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    });

    Route::middleware('permission:update-roles')->prefix('master')->group(function () {
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::patch('roles/{role}', [RoleController::class, 'update']);
    });

    Route::middleware('permission:delete-roles')->prefix('master')->group(function () {
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::middleware('permission:view-permissions')->prefix('master')->group(function () {
        Route::resource('permissions', PermissionController::class)->except(['show']);
    });

    Route::middleware('permission:assign-user-access')->prefix('master')->group(function () {
        Route::get('users/{user}/access', [UserAccessController::class, 'edit'])->name('users.access.edit');
        Route::put('users/{user}/access', [UserAccessController::class, 'update'])->name('users.access.update');
    });

    Route::middleware('permission:assign-canvasser')->prefix('procurement')->group(function () {
        Route::get('/approval', [PrsApprovalController::class, 'index'])->name('prs.approval.index');
        Route::get('/approval/{prs}', [PrsApprovalController::class, 'show'])->name('prs.approval.show');
        Route::post('/approval/{prs}/approve', [PrsApprovalController::class, 'approve'])->name('prs.approve');
        Route::post('/approval/{prs}/reassign', [PrsApprovalController::class, 'reassign'])->name('prs.reassign');
        Route::post('/approval/{prs}/hold', [PrsApprovalController::class, 'hold'])->name('prs.hold');
        Route::post('/approval/{prs}/reject', [PrsApprovalController::class, 'reject'])->name('prs.reject');
    });

    Route::middleware('permission:view-supplier-comparison')->prefix('procurement')->group(function () {
        Route::get('/supplier-comparison', [SupplierComparisonController::class, 'index'])->name('procurement.supplier-comparison.index');
        Route::get('/supplier-comparison/{prsItem}/report', [SupplierComparisonController::class, 'report'])->name('procurement.supplier-comparison.report');
    });

    Route::middleware('permission:select-supplier-comparison')->prefix('procurement')->group(function () {
        Route::post('/supplier-comparison/{prsItem}', [SupplierComparisonController::class, 'select'])->name('procurement.supplier-comparison.select');
        Route::post('/supplier-comparison/{prsItem}/reject', [SupplierComparisonController::class, 'reject'])->name('procurement.supplier-comparison.reject');
    });

    Route::middleware('permission:view-procurement-reports')->prefix('procurement')->group(function () {
        Route::get('/reports', [PurchasingReportController::class, 'index'])->name('procurement.reports.index');
        Route::post('/reports/prs-not-yet-po', [PurchasingReportController::class, 'prsNotYetPo'])->name('procurement.reports.prs-not-yet-po');
        Route::post('/reports/po-not-yet-delivered', [PurchasingReportController::class, 'poNotYetDelivered'])->name('procurement.reports.po-not-yet-delivered');
        Route::post('/reports/po-registered-period', [PurchasingReportController::class, 'poRegisteredPerPeriod'])->name('procurement.reports.po-registered-period');
        Route::post('/reports/po-registered-department', [PurchasingReportController::class, 'poRegisteredPerDepartment'])->name('procurement.reports.po-registered-department');
        Route::post('/reports/po-registered-item', [PurchasingReportController::class, 'poRegisteredPerItem'])->name('procurement.reports.po-registered-item');
        Route::post('/reports/po-registered-supplier', [PurchasingReportController::class, 'poRegisteredPerSupplier'])->name('procurement.reports.po-registered-supplier');
        Route::post('/reports/purchasing-lead-time', [PurchasingReportController::class, 'purchasingLeadTime'])->name('procurement.reports.purchasing-lead-time');
    });

    Route::middleware('permission:view-accounting-reports')->prefix('accounting')->group(function () {
        Route::get('/reports', [AccountingReportController::class, 'index'])->name('accounting.reports.index');
        Route::post('/reports/stock-card', [AccountingReportController::class, 'stockCard'])->name('accounting.reports.stock-card');
        Route::post('/reports/transaction', [AccountingReportController::class, 'transaction'])->name('accounting.reports.transaction');
        Route::post('/reports/restatement', [AccountingReportController::class, 'restatement'])->name('accounting.reports.restatement');
        Route::post('/reports/stock-card-count', [AccountingReportController::class, 'stockCardCount'])->name('accounting.reports.stock-card-count');
        Route::post('/reports/document-summary', [AccountingReportController::class, 'documentSummary'])->name('accounting.reports.document-summary');
        Route::post('/reports/purchase', [AccountingReportController::class, 'purchase'])->name('accounting.reports.purchase');
    });

    Route::middleware('permission:view-im-reports')->prefix('im')->group(function () {
        Route::get('/reports', [ImReportController::class, 'index'])->name('im.reports.index');
        Route::post('/reports/stock-inventory', [ImReportController::class, 'stockInventory'])->name('im.reports.stock-inventory');
        Route::post('/reports/transaction', [ImReportController::class, 'transaction'])->name('im.reports.transaction');
        Route::post('/reports/receiving-register', [ImReportController::class, 'receivingRegister'])->name('im.reports.receiving-register');
        Route::post('/reports/sws-register', [ImReportController::class, 'swsRegister'])->name('im.reports.sws-register');
        Route::post('/reports/transfer-register', [ImReportController::class, 'transferRegister'])->name('im.reports.transfer-register');
        Route::post('/reports/delivery-register', [ImReportController::class, 'deliveryRegister'])->name('im.reports.delivery-register');
    });

    Route::middleware('permission:view-exchange-rates')
        ->prefix('accounting')
        ->name('accounting.')
        ->group(function () {
            Route::get('exchange-rates', [CurrencyExchangeRateController::class, 'index'])->name('exchange-rates.index');
        });

    Route::middleware('permission:create-exchange-rates')
        ->prefix('accounting')
        ->name('accounting.')
        ->group(function () {
            Route::post('exchange-rates', [CurrencyExchangeRateController::class, 'store'])->name('exchange-rates.store');
        });

    Route::middleware('permission:view-doc-entries')
        ->prefix('accounting')
        ->name('accounting.')
        ->group(function () {
            Route::get('doc-entries', [AccountingDocEntryController::class, 'index'])->name('doc-entries.index');
            Route::get('doc-entries/account-lookup', [AccountingDocEntryController::class, 'lookupAccount'])->name('doc-entries.account-lookup');
            Route::get('doc-entries/transaction/{transaction}', [AccountingDocEntryController::class, 'showTransaction'])
                ->name('doc-entries.transaction');
            Route::get('doc-entries/{docType}/{id}', [AccountingDocEntryController::class, 'show'])
                ->where(['docType' => 'rr|dr', 'id' => '[0-9]+'])
                ->name('doc-entries.show');
        });

    Route::middleware('permission:update-doc-entries')
        ->prefix('accounting')
        ->name('accounting.')
        ->group(function () {
            Route::put('doc-entries/{transaction}', [AccountingDocEntryController::class, 'update'])->name('doc-entries.update');
        });

    Route::middleware('permission:view-canvassing')->group(function () {
        Route::get('/canvassing', [CanvassingController::class, 'index'])->name('canvassing.index');
        Route::get('/canvassing/reports/print', [CanvassingController::class, 'printReports'])->name('canvassing.reports.print');
        Route::get('/canvassing/{prsItem}', [CanvassingController::class, 'show'])->name('canvassing.show');
        Route::get('/canvassing/{prsItem}/report', [CanvassingController::class, 'report'])->name('canvassing.report');
    });

    Route::middleware('permission:update-canvassing')->group(function () {
        Route::post('/canvassing/{prsItem}', [CanvassingController::class, 'store'])->name('canvassing.store');
        Route::post('/canvassing/{prsItem}/toggle-direct-purchase', [CanvassingController::class, 'toggleDirectPurchase'])->name('canvassing.toggle-direct-purchase');
        Route::post('/canvassing/{prsItem}/hold', [CanvassingController::class, 'hold'])->name('canvassing.hold');
    });

    Route::middleware('permission:create-po')->prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/draft', [PurchaseOrderController::class, 'draft'])->name('draft');
        Route::post('/preview', [PurchaseOrderController::class, 'preview'])->name('preview');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
        Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');
        Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('submit');
        Route::post('/{purchaseOrder}/withdraw', [PurchaseOrderController::class, 'withdraw'])->name('withdraw');
        Route::delete('/{purchaseOrder}/items/{purchaseOrderItem}', [PurchaseOrderController::class, 'destroyItem'])
            ->name('items.destroy');
    });

    Route::middleware('permission:approve-po')->prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/approval', [PurchaseOrderApprovalController::class, 'index'])->name('approval');
        Route::post('/{purchaseOrder}/approve', [PurchaseOrderApprovalController::class, 'approve'])->name('approve');
        Route::post('/{purchaseOrder}/request-changes', [PurchaseOrderApprovalController::class, 'requestChanges'])->name('request-changes');
    });

    Route::middleware('permission:view-po')->prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
            ->whereNumber('purchaseOrder')
            ->name('show');
        Route::match(['get', 'post'], '/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])
            ->whereNumber('purchaseOrder')
            ->name('print');
        Route::post('/{purchaseOrder}/number', [PurchaseOrderController::class, 'updateNumber'])
            ->whereNumber('purchaseOrder')
            ->name('number');
    });

    Route::middleware('permission:cancel-po')->prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->whereNumber('purchaseOrder')
            ->name('cancel');
    });

    Route::middleware('permission:view-rr')->prefix('receiving-reports')->name('receiving-reports.')->group(function () {
        Route::get('/', [ReceivingReportController::class, 'index'])->name('index');
        Route::match(['get', 'post'], '/{receivingReport}/print', [ReceivingReportController::class, 'print'])->name('print');
    });

    Route::middleware('permission:create-rr')->prefix('receiving-reports')->name('receiving-reports.')->group(function () {
        Route::get('/po-by-number', [ReceivingReportController::class, 'poByNumber'])->name('po-by-number');
        Route::post('/', [ReceivingReportController::class, 'store'])->name('store');
    });

    Route::middleware('permission:update-rr')->prefix('receiving-reports')->name('receiving-reports.')->group(function () {
        Route::put('/{receivingReport}', [ReceivingReportController::class, 'update'])->name('update');
    });

    Route::middleware('permission:delete-rr')->prefix('receiving-reports')->name('receiving-reports.')->group(function () {
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

    Route::middleware('permission:create-stock-adjustment')->prefix('stock-adjustments')->name('stock-adjustments.')->group(function () {
        Route::get('/create', [StockAdjustmentController::class, 'create'])->name('create');
        Route::get('/items/search', [StockAdjustmentController::class, 'searchItems'])->name('items.search');
        Route::post('/', [StockAdjustmentController::class, 'store'])->name('store');
    });

    Route::middleware('permission:view-stock-adjustment')->prefix('stock-adjustments')->name('stock-adjustments.')->group(function () {
        Route::get('/', [StockAdjustmentController::class, 'index'])->name('index');
        Route::get('/{stockAdjustment}', [StockAdjustmentController::class, 'show'])->name('show');
    });

    Route::middleware('permission:delete-stock-adjustment')->prefix('stock-adjustments')->name('stock-adjustments.')->group(function () {
        Route::delete('/{stockAdjustment}', [StockAdjustmentController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('permission:create-opening-balance-correction')->prefix('opening-balance-corrections')->name('opening-balance-corrections.')->group(function () {
        Route::get('/create', [OpeningBalanceCorrectionController::class, 'create'])->name('create');
        Route::get('/items/search', [OpeningBalanceCorrectionController::class, 'searchItems'])->name('items.search');
        Route::post('/preview', [OpeningBalanceCorrectionController::class, 'preview'])->name('preview');
        Route::post('/', [OpeningBalanceCorrectionController::class, 'store'])->name('store');
    });

    Route::middleware('permission:view-opening-balance-correction')->prefix('opening-balance-corrections')->name('opening-balance-corrections.')->group(function () {
        Route::get('/', [OpeningBalanceCorrectionController::class, 'index'])->name('index');
        Route::get('/{openingBalanceCorrection}', [OpeningBalanceCorrectionController::class, 'show'])->name('show');
    });

    Route::middleware('permission:delete-opening-balance-correction')->prefix('opening-balance-corrections')->name('opening-balance-corrections.')->group(function () {
        Route::post('/{openingBalanceCorrection}/reverse', [OpeningBalanceCorrectionController::class, 'reverse'])->name('reverse');
    });

    Route::post('/change-password', [UserController::class, 'changePassword'])->name('password.change');
    Route::resource('prs', PrsController::class);
    Route::post('prs/export', [PrsController::class, 'export'])->name('prs.export');
    Route::post('prs/export-by-department', [PrsController::class, 'exportByDepartment'])->name('prs.export-by-department');
    Route::get('prs/{prs}/print', [PrsController::class, 'print'])->name('prs.print');

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
