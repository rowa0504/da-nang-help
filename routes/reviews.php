<?php

use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->post('/jobs/{job}/review', [ReviewController::class, 'store'])->name('jobs.review.store');
