<?php

use App\Modules\Endra\PetHealth\Http\Controllers\PetCareNoteController;
use App\Modules\Endra\PetHealth\Http\Controllers\PetController;
use App\Modules\Endra\PetHealth\Http\Controllers\PetHealthAttachmentController;
use App\Modules\Endra\PetHealth\Http\Controllers\PetHealthController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/kesehatan-hewan/{record}/lampiran', [PetHealthAttachmentController::class, 'show'])
        ->name('pets.health.attachment');
});

Route::middleware(['auth', 'role:customer'])
    ->prefix('customer')
    ->name('customer.')
    ->group(function (): void {
        Route::patch('/pets/{pet}/archive', [PetController::class, 'archive'])
            ->name('pets.archive');

        Route::patch('/pets/{pet}/restore', [PetController::class, 'restore'])
            ->name('pets.restore');

        Route::get('/pets/{pet}/kesehatan', [PetHealthController::class, 'show'])
            ->name('pets.health.show');

        Route::post('/pets/{pet}/kesehatan', [PetHealthController::class, 'store'])
            ->name('pets.health.store');

        Route::put('/pets/{pet}/kesehatan/{record}', [PetHealthController::class, 'update'])
            ->name('pets.health.update');

        Route::delete('/pets/{pet}/kesehatan/{record}', [PetHealthController::class, 'destroy'])
            ->name('pets.health.destroy');

        Route::patch('/pets/{pet}/kesehatan-izin', [PetHealthController::class, 'toggleShare'])
            ->name('pets.health.share');

        Route::patch('/pets/{pet}/kesehatan/{record}/izin', [PetHealthController::class, 'updatePermission'])
            ->name('pets.health.permission');

        Route::resource('pets', PetController::class)->except(['show']);
    });

Route::middleware(['auth', 'role:business_owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function (): void {
        Route::post('/booking/{booking}/perawatan', [PetCareNoteController::class, 'store'])
            ->name('care-notes.store');

        Route::delete('/perawatan/{note}', [PetCareNoteController::class, 'destroy'])
            ->name('care-notes.destroy');
    });
