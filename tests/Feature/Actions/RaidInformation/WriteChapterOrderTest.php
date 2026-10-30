<?php

namespace Tests\Feature\Actions\RaidInformation;

use App\Actions\RaidInformation\ListChapters;
use App\Actions\RaidInformation\ReadChapterOrder;
use App\Actions\RaidInformation\WriteChapterOrder;
use Illuminate\Support\Facades\Storage;
use JsonException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class WriteChapterOrderTest extends TestCase
{
    #[Test]
    public function it_puts_the_introduction_first(): void
    {
        WriteChapterOrder::run(['loot', 'introduction', 'benching']);

        $this->assertSame(['introduction', 'loot', 'benching'], ReadChapterOrder::run());
    }

    #[Test]
    public function it_removes_duplicates(): void
    {
        WriteChapterOrder::run(['loot', 'benching', 'loot']);

        $this->assertSame(['introduction', 'loot', 'benching'], ReadChapterOrder::run());
    }

    #[Test]
    public function it_writes_a_pretty_printed_json_list(): void
    {
        WriteChapterOrder::run(['loot']);

        $this->assertSame(
            "[\n    \"introduction\",\n    \"loot\"\n]",
            Storage::disk('raid-information')->get('chapters.json'),
        );
    }

    #[Test]
    public function it_throws_and_keeps_the_existing_order_when_a_slug_cannot_be_encoded(): void
    {
        Storage::disk('raid-information')->put('chapters.json', '["introduction","loot"]');

        try {
            WriteChapterOrder::run(["\xB1"]);
            $this->fail('Expected a JsonException.');
        } catch (JsonException) {
        }

        $this->assertSame(['introduction', 'loot'], ReadChapterOrder::run());
    }

    #[Test]
    public function it_flushes_the_chapter_cache(): void
    {
        Storage::disk('raid-information')->put('introduction.md', '## Intro');
        $this->assertCount(1, ListChapters::run());

        Storage::disk('raid-information')->put('loot.md', '## Loot');
        WriteChapterOrder::run(['loot']);

        $this->assertCount(2, ListChapters::run());
    }
}
