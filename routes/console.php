<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 6: auto-confirm jobs whose customer never responded within the
// configured window (services.auto_confirm_days). The actual cron trigger
// (php artisan schedule:run) is provisioned in Phase 10's infrastructure.
Schedule::command('app:auto-confirm-jobs')->everyMinute();
