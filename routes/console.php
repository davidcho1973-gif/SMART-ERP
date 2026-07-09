<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Close the finished work day just after midnight (Phoenix time): scheduled
// workers with no punch and no leave become unexcused no-shows (무단결근).
Schedule::command('attendance:close-day')->dailyAt('00:20')->timezone('America/Phoenix');
