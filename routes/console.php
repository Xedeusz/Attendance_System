<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('accounts:prune-local-mail')->hourly()->withoutOverlapping();
Schedule::command('attendance:prune-evidence')->daily()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('leave:refresh-routing')->hourly()->withoutOverlapping();
Schedule::command('leave:purge-documents')->daily()->withoutOverlapping();
