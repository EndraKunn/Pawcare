<?php

use App\Modules\Elga\Business\Http\Controllers\BusinessController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:business_owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/bisnis', [BusinessController::class, 'edit'])->name('business.edit');
    Route::put('/bisnis', [BusinessController::class, 'update'])->name('business.update');
    Route::put('/bisnis/jadwal', [BusinessController::class, 'updateSchedule'])->name('business.schedule.update');
    Route::post('/bisnis/libur', [BusinessController::class, 'storeClosure'])->name('business.closures.store');
    Route::patch('/bisnis/libur/{closure}/nonaktif', [BusinessController::class, 'deactivateClosure'])->name('business.closures.deactivate');
    // Backward-compatible endpoint: DELETE now deactivates, never physically deletes.
    Route::delete('/bisnis/libur/{closure}', [BusinessController::class, 'deactivateClosure'])->name('business.closures.destroy');
});
