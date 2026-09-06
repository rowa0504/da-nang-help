<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

// No auth middleware: guests may switch locale too (session-only).
Route::patch('/locale', [LocaleController::class, 'update'])->name('locale.update');
