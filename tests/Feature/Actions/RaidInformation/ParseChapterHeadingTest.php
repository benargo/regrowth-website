<?php

namespace Tests\Feature\Actions\RaidInformation;

use App\Actions\RaidInformation\ParseChapterHeading;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ParseChapterHeadingTest extends TestCase
{
    #[Test]
    #[DataProvider('headings')]
    public function it_parses_the_title_and_emoji(string $content, string $title, ?string $emoji): void
    {
        $heading = ParseChapterHeading::run($content);

        $this->assertSame($title, $heading->title);
        $this->assertSame($emoji, $heading->emoji);
    }

    #[Test]
    public function it_reads_only_the_first_line(): void
    {
        $heading = ParseChapterHeading::run("## A\n### B");

        $this->assertSame('A', $heading->title);
        $this->assertNull($heading->emoji);
    }

    /**
     * @return array<string, array{string, string, string|null}>
     */
    public static function headings(): array
    {
        return [
            'unicode emoji' => ['## 🌳 What is Regrowth?', 'What is Regrowth?', '🌳'],
            'emoji with variation selector' => ["## 🗓\u{FE0F} Raid Schedule", 'Raid Schedule', "🗓\u{FE0F}"],
            'level one heading' => ['# 📋 Regrowth Raid Information', 'Regrowth Raid Information', '📋'],
            'custom emoji' => ['## <:regrowth:123456789012345678> Loot', 'Loot', '<:regrowth:123456789012345678>'],
            'animated custom emoji' => ['## <a:party:123> Fun', 'Fun', '<a:party:123>'],
            'flag' => ['## 🇬🇧 UK raiders', 'UK raiders', '🇬🇧'],
            'no emoji' => ['## Benching', 'Benching', null],
            'emoji only' => ['## 🌳', '', '🌳'],
            'carriage return line ending' => ["## A\rB", 'A', null],
            'invalid utf-8' => ["## \xB1 Loot", '', null],
        ];
    }
}
