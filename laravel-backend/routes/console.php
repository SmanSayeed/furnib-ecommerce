<?php

use App\Jobs\ReconcilePendingPayments;
use App\Jobs\SyncCourierStatuses;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recover payments where the bank captured the money but our browser callback
// AND server IPN were both lost (a false-negative). Idempotent + unique, so it
// is safe to run often.
Schedule::job(new ReconcilePendingPayments)
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Steadfast has no delivery webhook — poll in-flight consignments so order
// tracking and the customer fraud/return-ratio stats stay current.
Schedule::job(new SyncCourierStatuses)
    ->hourly()
    ->withoutOverlapping();
