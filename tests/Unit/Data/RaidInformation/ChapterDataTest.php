<?php

namespace Tests\Unit\Data\RaidInformation;

use App\Data\RaidInformation\ChapterData;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ChapterDataTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_serialises_to_snake_case_keys_with_an_atom_date(): void
    {
        $chapter = new ChapterData(
            slug: 'tanking',
            title: 'Tanking',
            emoji: '🛡️',
            content: "# 🛡️ Tanking\n\nHold aggro.",
            characterCount: 26,
            hash: 'abc123',
            updatedAt: CarbonImmutable::parse('2026-10-01 18:30:00', 'UTC'),
            isIntroduction: false,
        );

        $this->assertSame([
            'slug' => 'tanking',
            'title' => 'Tanking',
            'emoji' => '🛡️',
            'content' => "# 🛡️ Tanking\n\nHold aggro.",
            'character_count' => 26,
            'hash' => 'abc123',
            'updated_at' => '2026-10-01T18:30:00+00:00',
            'is_introduction' => false,
        ], $chapter->toArray());
    }

    #[Test]
    #[Group('contract')]
    public function it_serialises_a_missing_emoji_as_null(): void
    {
        $chapter = new ChapterData(
            slug: 'introduction',
            title: 'Introduction',
            emoji: null,
            content: '# Introduction',
            characterCount: 14,
            hash: 'def456',
            updatedAt: CarbonImmutable::parse('2026-10-01 18:30:00', 'UTC'),
            isIntroduction: true,
        );

        $array = $chapter->toArray();

        $this->assertNull($array['emoji']);
        $this->assertTrue($array['is_introduction']);
    }
}
