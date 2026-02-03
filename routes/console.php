<?php

use Illuminate\Support\Facades\Schedule;

Schedule::call('model:prune')->dailyAt('03:00');
