<?php

namespace Tests\Unit\Data\Handbook;

use App\Data\Handbook\TrashedChapterData;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class TrashedChapterDataTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_serialises_to_snake_case_keys_with_atom_dates(): void
    {
        $chapter = new TrashedChapterData(
            slug: 'tanking',
            title: 'Tanking',
            emoji: '🛡️',
            trashedAt: CarbonImmutable::parse('2026-10-01 18:30:00', 'UTC'),
            purgesAt: CarbonImmutable::parse('2026-10-31 18:30:00', 'UTC'),
            daysRemaining: 30,
        );

        $this->assertSame([
            'slug' => 'tanking',
            'title' => 'Tanking',
            'emoji' => '🛡️',
            'trashed_at' => '2026-10-01T18:30:00+00:00',
            'purges_at' => '2026-10-31T18:30:00+00:00',
            'days_remaining' => 30,
        ], $chapter->toArray());
    }

    #[Test]
    #[Group('contract')]
    public function it_serialises_a_missing_emoji_as_null(): void
    {
        $chapter = new TrashedChapterData(
            slug: 'introduction',
            title: 'Introduction',
            emoji: null,
            trashedAt: CarbonImmutable::parse('2026-10-01 18:30:00', 'UTC'),
            purgesAt: CarbonImmutable::parse('2026-10-31 18:30:00', 'UTC'),
            daysRemaining: 30,
        );

        $this->assertNull($chapter->toArray()['emoji']);
    }
}
