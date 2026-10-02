<?php

use App\Http\Controllers\OfferController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('/requests/{serviceRequest}/offers', [OfferController::class, 'store'])->name('requests.offers.store')->middleware('throttle:content-create');
    Route::get('/requests/{serviceRequest}/offers', [OfferController::class, 'index'])->name('requests.offers.index');

    Route::prefix('offers')->name('offers.')->group(function () {
        Route::patch('/{offer}', [OfferController::class, 'update'])->name('update')->middleware('throttle:content-create');
        Route::patch('/{offer}/withdraw', [OfferController::class, 'withdraw'])->name('withdraw')->middleware('throttle:authenticated-write');
        Route::patch('/{offer}/accept', [OfferController::class, 'accept'])->name('accept')->middleware('throttle:authenticated-write');
        Route::patch('/{offer}/reject', [OfferController::class, 'reject'])->name('reject')->middleware('throttle:authenticated-write');
    });
});
