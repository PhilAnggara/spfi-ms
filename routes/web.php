<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\UnitOfMeasureController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PrsApprovalController;
use App\Http\Controllers\PrsController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => redirect()->route('login'));
});

Route::middleware('auth')->group(function () {

    Route::get('/', function () {
        return view('pages.dashboard');
    })->name('dashboard');

    Route::middleware('role:administrator')->prefix('master')->group(function () {
        Route::resource('user', UserController::class);
        Route::resource('product', ProductController::class);
        Route::resource('product-category', ItemCategoryController::class);
        Route::resource('unit-of-measurement', UnitOfMeasureController::class);
        Route::resource('supplier', SupplierController::class);
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

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

});

require __DIR__.'/auth.php';
