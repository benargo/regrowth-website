<?php

use App\Providers\AppServiceProvider;
use App\Providers\BlizzardServiceProvider;
use App\Providers\DiscordServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\RaidHelperServiceProvider;
use App\Providers\WarcraftLogsServiceProvider;

return [
    AppServiceProvider::class,
    BlizzardServiceProvider::class,
    DiscordServiceProvider::class,
    EventServiceProvider::class,
    HorizonServiceProvider::class,
    RaidHelperServiceProvider::class,
    WarcraftLogsServiceProvider::class,
];
