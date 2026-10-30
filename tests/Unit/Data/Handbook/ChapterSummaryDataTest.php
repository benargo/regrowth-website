<?php

namespace Tests\Unit\Data\Handbook;

use App\Data\Handbook\ChapterData;
use App\Data\Handbook\ChapterSummaryData;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ChapterSummaryDataTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_serialises_to_snake_case_keys_with_an_atom_date(): void
    {
        $summary = new ChapterSummaryData(
            slug: 'tanking',
            title: 'Tanking',
            emoji: '🛡️',
            characterCount: 26,
            updatedAt: CarbonImmutable::parse('2026-10-01 18:30:00', 'UTC'),
            isIntroduction: false,
        );

        $this->assertSame([
            'slug' => 'tanking',
            'title' => 'Tanking',
            'emoji' => '🛡️',
            'character_count' => 26,
            'updated_at' => '2026-10-01T18:30:00+00:00',
            'is_introduction' => false,
        ], $summary->toArray());
    }

    #[Test]
    public function it_builds_from_a_chapter_without_its_content_or_hash(): void
    {
        $updatedAt = CarbonImmutable::parse('2026-10-01 18:30:00', 'UTC');

        $summary = ChapterSummaryData::from(new ChapterData(
            slug: 'introduction',
            title: 'Introduction',
            emoji: null,
            content: '# Introduction',
            characterCount: 14,
            hash: 'def456',
            updatedAt: $updatedAt,
            isIntroduction: true,
        ));

        $this->assertSame('introduction', $summary->slug);
        $this->assertSame('Introduction', $summary->title);
        $this->assertNull($summary->emoji);
        $this->assertSame(14, $summary->characterCount);
        $this->assertTrue($summary->updatedAt->equalTo($updatedAt));
        $this->assertTrue($summary->isIntroduction);
        $this->assertArrayNotHasKey('content', $summary->toArray());
        $this->assertArrayNotHasKey('hash', $summary->toArray());
    }
}
