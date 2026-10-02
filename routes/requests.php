<?php

use App\Http\Controllers\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('requests')->name('requests.')->group(function () {
    Route::get('/', [ServiceRequestController::class, 'index'])->name('index');
    Route::get('/create', [ServiceRequestController::class, 'create'])->name('create');
    Route::post('/', [ServiceRequestController::class, 'store'])->name('store')->middleware('throttle:content-create');
    Route::get('/{serviceRequest}/edit', [ServiceRequestController::class, 'edit'])->name('edit');
    Route::patch('/{serviceRequest}', [ServiceRequestController::class, 'update'])->name('update')->middleware('throttle:content-create');
    Route::get('/{serviceRequest}', [ServiceRequestController::class, 'show'])->name('show');
    Route::patch('/{serviceRequest}/cancel', [ServiceRequestController::class, 'cancel'])->name('cancel')->middleware('throttle:authenticated-write');
});
