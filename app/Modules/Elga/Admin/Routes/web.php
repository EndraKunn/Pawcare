<?php

use App\Modules\Elga\Admin\Http\Controllers\ClientController;
use App\Modules\Elga\Admin\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
    Route::get('/pengguna/{user}', [UserController::class, 'show'])->name('users.show');
    Route::patch('/pengguna/{user}/status', [UserController::class, 'toggle'])->name('users.toggle');
    Route::get('/klien', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/klien/{business}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('/klien/{business}/ubah', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('/klien/{business}', [ClientController::class, 'update'])->name('clients.update');
    Route::patch('/klien/{business}/status', [ClientController::class, 'toggle'])->name('clients.toggle');
});
