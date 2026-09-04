<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});

Route::middleware('auth')->get('/dashboard', DashboardController::class)->name('dashboard');

require __DIR__.'/auth.php';
require __DIR__.'/provider.php';
require __DIR__.'/admin.php';
require __DIR__.'/requests.php';
