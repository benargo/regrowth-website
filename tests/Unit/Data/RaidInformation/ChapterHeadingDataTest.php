<?php

namespace Tests\Unit\Data\RaidInformation;

use App\Data\RaidInformation\ChapterHeadingData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ChapterHeadingDataTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_serialises_the_title_and_emoji(): void
    {
        $heading = new ChapterHeadingData(title: 'Tanking', emoji: '🛡️');

        $this->assertSame([
            'title' => 'Tanking',
            'emoji' => '🛡️',
        ], $heading->toArray());
    }

    #[Test]
    #[Group('contract')]
    public function it_serialises_a_missing_emoji_as_null(): void
    {
        $heading = new ChapterHeadingData(title: 'Introduction', emoji: null);

        $this->assertSame([
            'title' => 'Introduction',
            'emoji' => null,
        ], $heading->toArray());
    }
}
