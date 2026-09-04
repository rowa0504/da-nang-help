<?php

use App\Http\Controllers\Admin\ProviderReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/providers', [ProviderReviewController::class, 'index'])->name('providers.index');
    Route::get('/providers/{providerProfile}', [ProviderReviewController::class, 'show'])->name('providers.show');
    Route::patch('/providers/{providerProfile}/approve', [ProviderReviewController::class, 'approve'])->name('providers.approve');
    Route::patch('/providers/{providerProfile}/reject', [ProviderReviewController::class, 'reject'])->name('providers.reject');
});
