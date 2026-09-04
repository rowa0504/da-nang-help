<?php

use App\Http\Controllers\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('requests')->name('requests.')->group(function () {
    Route::get('/', [ServiceRequestController::class, 'index'])->name('index');
    Route::get('/create', [ServiceRequestController::class, 'create'])->name('create');
    Route::post('/', [ServiceRequestController::class, 'store'])->name('store');
    Route::get('/{serviceRequest}', [ServiceRequestController::class, 'show'])->name('show');
    Route::patch('/{serviceRequest}/cancel', [ServiceRequestController::class, 'cancel'])->name('cancel');
});
