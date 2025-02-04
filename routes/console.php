<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;
use App\Jobs\WebsiteMonitorJob;

// Schedule Artisan command
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Correct way to schedule the job
$schedule = app(Schedule::class);
$schedule->job(WebsiteMonitorJob::class)->everyThirtySeconds(); // Pass class name as an argument

