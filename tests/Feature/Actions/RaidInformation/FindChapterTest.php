<?php

namespace Tests\Feature\Actions\RaidInformation;

use App\Actions\RaidInformation\FindChapter;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class FindChapterTest extends TestCase
{
    #[Test]
    public function it_finds_a_chapter_by_slug(): void
    {
        $this->putChapter('loot', '## Loot');

        $this->assertSame('Loot', FindChapter::run('loot')->title);
    }

    #[Test]
    public function it_returns_null_for_an_unknown_slug(): void
    {
        $this->putChapter('loot', '## Loot');

        $this->assertNull(FindChapter::run('ghost'));
    }

    #[Test]
    public function it_returns_null_for_a_path_like_slug(): void
    {
        $this->putChapter('loot', '## Loot');
        Storage::disk('raid-information')->put('.trashed/old.md', '## Old');

        $this->assertNull(FindChapter::run('../addon/x'));
        $this->assertNull(FindChapter::run('.trashed/old'));
    }

    private function putChapter(string $slug, string $content, ?int $mtime = null): void
    {
        Storage::disk('raid-information')->put("{$slug}.md", $content);

        if ($mtime !== null) {
            touch(Storage::disk('raid-information')->path("{$slug}.md"), $mtime);
        }
    }
}
