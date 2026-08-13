<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run')->dailyAt(env('BACKUP_HOUR_ONE', '9:00'));
Schedule::command('backup:run')->dailyAt(env('BACKUP_HOUR_TWO', '15:00'));
Schedule::command('backup:clean')->dailyAt(env('BACKUP_HOUR_CLEAN', '22:00'));
