<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PrsController;
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

    Route::prefix('master')->group(function () {
        Route::resource('user', UserController::class);
    });
    Route::post('/change-password', [UserController::class, 'changePassword'])->name('password.change');
    Route::resource('prs', PrsController::class);
    Route::post('prs/export', [PrsController::class, 'export'])->name('prs.export');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

});

require __DIR__.'/auth.php';
