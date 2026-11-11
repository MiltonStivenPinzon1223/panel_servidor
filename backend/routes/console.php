<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('panel:tick')->everyMinute()->withoutOverlapping(1);
Schedule::call(fn () => DB::table('alert_events')->where('state', 'resolved')->where('resolved_at', '<', now()->subDays(90))->delete())
    ->weekly()
    ->name('panel-purge-resolved-alerts');
