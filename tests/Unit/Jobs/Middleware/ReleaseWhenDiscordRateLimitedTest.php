<?php

namespace Tests\Unit\Jobs\Middleware;

use App\Http\Integrations\Discord\Exceptions\RateLimitedException;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('discord-integration')]
#[Group('error-handling')]
class ReleaseWhenDiscordRateLimitedTest extends TestCase
{
    #[Test]
    public function it_releases_the_job_until_the_rate_limit_resets(): void
    {
        $job = $this->makeJob();

        (new ReleaseWhenDiscordRateLimited)->handle(
            $job,
            fn () => throw new RateLimitedException('GET', 'channels/111', retryAfter: 2.2),
        );

        $this->assertSame(3, $job->releasedFor);
    }

    #[Test]
    public function it_lets_other_exceptions_fail_the_job(): void
    {
        $job = $this->makeJob();

        $this->expectException(RuntimeException::class);

        try {
            (new ReleaseWhenDiscordRateLimited)->handle($job, fn () => throw new RuntimeException('Discord is down'));
        } finally {
            $this->assertNull($job->releasedFor);
        }
    }

    // ==================== helpers ====================

    private function makeJob(): object
    {
        return new class
        {
            public ?int $releasedFor = null;

            public function release(int $delay = 0): void
            {
                $this->releasedFor = $delay;
            }
        };
    }
}
