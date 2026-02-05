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
            Route::resource('balance-sheet', BsGroupingController::class)->except(['create', 'show', 'edit']);
        });
    });
    Route::middleware('role:administrator|purchasing-manager')->prefix('procurement')->group(function () {
        Route::get('/approval', [PrsApprovalController::class, 'index'])->name('prs.approval.index');
        Route::get('/approval/{prs}', [PrsApprovalController::class, 'show'])->name('prs.approval.show');
        Route::post('/approval/{prs}/approve', [PrsApprovalController::class, 'approve'])->name('prs.approve');
        Route::post('/approval/{prs}/reject', [PrsApprovalController::class, 'reject'])->name('prs.reject');
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
