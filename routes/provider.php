<?php

use App\Http\Controllers\Provider\ProviderProfileController;
use App\Http\Controllers\Provider\RequestFeedController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('provider')->name('provider.')->group(function () {
    Route::get('/profile', [ProviderProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProviderProfileController::class, 'submit'])->name('profile.submit');
    Route::patch('/profile', [ProviderProfileController::class, 'update'])->name('profile.update');
    Route::get('/requests', [RequestFeedController::class, 'index'])->name('requests.index');
});
