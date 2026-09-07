<?php

use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProviderReviewController;
use App\Http\Controllers\Admin\ReviewModerationController;
use App\Http\Controllers\Admin\ServiceRequestModerationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/providers', [ProviderReviewController::class, 'index'])->name('providers.index');
    Route::get('/providers/{providerProfile}', [ProviderReviewController::class, 'show'])->name('providers.show');
    Route::patch('/providers/{providerProfile}/approve', [ProviderReviewController::class, 'approve'])->name('providers.approve');
    Route::patch('/providers/{providerProfile}/reject', [ProviderReviewController::class, 'reject'])->name('providers.reject');
    Route::patch('/providers/{providerProfile}/suspend', [ProviderReviewController::class, 'suspend'])->name('providers.suspend');

    Route::get('/reviews', [ReviewModerationController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}/hide', [ReviewModerationController::class, 'hide'])->name('reviews.hide');

    Route::get('/requests', [ServiceRequestModerationController::class, 'index'])->name('requests.index');
    Route::patch('/requests/{serviceRequest}/hide', [ServiceRequestModerationController::class, 'hide'])->name('requests.hide');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');

    Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');
    Route::get('/areas/create', [AreaController::class, 'create'])->name('areas.create');
    Route::post('/areas', [AreaController::class, 'store'])->name('areas.store');
    Route::get('/areas/{area}/edit', [AreaController::class, 'edit'])->name('areas.edit');
    Route::patch('/areas/{area}', [AreaController::class, 'update'])->name('areas.update');
});
