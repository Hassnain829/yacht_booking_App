<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| On the server one cron job runs `php artisan schedule:run` every minute (CLAUDE.md, PLAN §12).
*/

// Queued emails (security emails, and later booking emails): a short worker each minute — shared hosting has no daemon.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')->everyMinute()->withoutOverlapping();

// Holds that ran out become expired and their open payment forms are cancelled (PLAN §7.3, 4.5).
Schedule::command('bookings:expire-holds')->everyMinute()->withoutOverlapping();

// Delete rows past their retention, e.g. login_events older than a year (LoginEvent::prunable).
Schedule::command('model:prune')->daily();
