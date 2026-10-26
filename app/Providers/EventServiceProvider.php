<?php

namespace App\Providers;

use App\Listeners\GuildRosterManager\BroadcastUploadRetry;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobReleasedAfterException;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        JobReleasedAfterException::class => [
            BroadcastUploadRetry::class,
        ],
    ];
}
