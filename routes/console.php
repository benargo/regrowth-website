<?php

use App\Jobs\CheckLevelCapAchievements;
use Illuminate\Support\Facades\Schedule;

// Schedule::job(CheckLevelCapAchievements::class)->everyFiveMinutes();
Schedule::call('model:prune')->dailyAt('03:00');
