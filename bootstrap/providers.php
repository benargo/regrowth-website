<?php

use App\Providers\AppServiceProvider;
use App\Providers\AttendanceCalculatorServiceProvider;
use App\Providers\BlizzardServiceProvider;
use App\Providers\DiscordServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\WarcraftLogsServiceProvider;

return [
    AppServiceProvider::class,
    BlizzardServiceProvider::class,
    DiscordServiceProvider::class,
    HorizonServiceProvider::class,
    AttendanceCalculatorServiceProvider::class,
    WarcraftLogsServiceProvider::class,
];
