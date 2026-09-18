<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('auth')->get('/dashboard', DashboardController::class)->name('dashboard');

require __DIR__.'/auth.php';
require __DIR__.'/provider.php';
require __DIR__.'/admin.php';
require __DIR__.'/requests.php';
require __DIR__.'/offers.php';
require __DIR__.'/jobs.php';
require __DIR__.'/reviews.php';
require __DIR__.'/locale.php';
