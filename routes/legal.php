<?php

use App\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;

// No 'auth' middleware: guests must be able to read these before
// registering. 'legal.configured' is a no-op outside production (see
// EnsureLegalConfigurationIsFinalized) and only blocks real traffic if the
// operator identity / document version / effective date were never
// finalized.
Route::get('/terms', [LegalController::class, 'terms'])->name('terms')->middleware('legal.configured');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy')->middleware('legal.configured');
