<?php

namespace Tests\Unit\Enums;

use App\Enums\RankSource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('characters')]
class RankSourceTest extends TestCase
{
    #[Test]
    public function it_has_the_expected_cases_and_values(): void
    {
        $this->assertSame(
            ['grm', 'blizzard'],
            array_map(fn (RankSource $source) => $source->value, RankSource::cases()),
        );
    }
}
