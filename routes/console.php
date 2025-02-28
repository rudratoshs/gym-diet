<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\DailyReminderTask;
use App\Console\Commands\WeeklySummaryTask;


Artisan::command('send:daily-reminders', DailyReminderTask::class)->daily();
Artisan::command('send:weekly-summaries', WeeklySummaryTask::class)->weekly()->sundays()->at('18:00');
