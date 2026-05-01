<?php

use App\Providers\AppServiceProvider;
use App\Providers\AttendanceServiceProvider;
use App\Providers\BlizzardServiceProvider;
use App\Providers\DiscordServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\RaidHelperServiceProvider;
use App\Providers\WarcraftLogsServiceProvider;

return [
    AppServiceProvider::class,
    AttendanceServiceProvider::class,
    BlizzardServiceProvider::class,
    DiscordServiceProvider::class,
    HorizonServiceProvider::class,
    RaidHelperServiceProvider::class,
    WarcraftLogsServiceProvider::class,
];
