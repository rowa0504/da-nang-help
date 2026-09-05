<?php

use App\Http\Controllers\JobController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('jobs')->name('jobs.')->group(function () {
    Route::get('/', [JobController::class, 'index'])->name('index');
    Route::get('/{job}', [JobController::class, 'show'])->name('show');
    Route::patch('/{job}/start', [JobController::class, 'start'])->name('start');
    Route::patch('/{job}/report-completion', [JobController::class, 'reportCompletion'])->name('reportCompletion');
    Route::patch('/{job}/confirm-completion', [JobController::class, 'confirmCompletion'])->name('confirmCompletion');
    Route::patch('/{job}/cancel', [JobController::class, 'cancel'])->name('cancel');
});
