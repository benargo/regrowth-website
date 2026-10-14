<?php

namespace Tests\Unit\Http\Integrations\Discord;

use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\RateLimitCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

abstract class DiscordTestCase extends TestCase
{
    protected function makeConnector(): DiscordConnector
    {
        return new DiscordConnector('test-token', 'DiscordBot (https://regrowth.test, 1.0)', new RateLimitCache(Cache::store()));
    }
}
