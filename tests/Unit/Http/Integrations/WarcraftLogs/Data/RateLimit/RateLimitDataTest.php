<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Data\RateLimit;

use App\Http\Integrations\WarcraftLogs\Data\RateLimit\RateLimitData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class RateLimitDataTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_casts_from_the_rate_limit_data_payload(): void
    {
        $rateLimit = RateLimitData::from($this->samplePayload());

        $this->assertSame(3600, $rateLimit->limitPerHour);
        $this->assertSame(1842.5, $rateLimit->pointsSpentThisHour);
        $this->assertSame(1260, $rateLimit->pointsResetIn);
    }

    #[Test]
    public function it_accepts_whole_points_spent_as_a_float(): void
    {
        $rateLimit = RateLimitData::from([...$this->samplePayload(), 'pointsSpentThisHour' => 12]);

        $this->assertSame(12.0, $rateLimit->pointsSpentThisHour);
    }

    /**
     * @return array{limitPerHour: int, pointsSpentThisHour: float, pointsResetIn: int}
     */
    private function samplePayload(): array
    {
        return [
            'limitPerHour' => 3600,
            'pointsSpentThisHour' => 1842.5,
            'pointsResetIn' => 1260,
        ];
    }
}
