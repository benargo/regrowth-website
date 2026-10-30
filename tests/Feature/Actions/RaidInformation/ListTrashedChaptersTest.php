<?php

namespace Tests\Feature\Actions\RaidInformation;

use App\Actions\RaidInformation\ListTrashedChapters;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ListTrashedChaptersTest extends TestCase
{
    #[Test]
    public function it_lists_trashed_chapters_newest_first_with_purge_dates(): void
    {
        $this->freezeSecond();
        $disk = Storage::disk('raid-information');
        $disk->put('.trashed/old.md', "## 🐉 Old\nBody");
        $disk->put('.trashed/recent.md', "## Recent\nBody");
        touch($disk->path('.trashed/old.md'), now()->subDays(31)->getTimestamp());
        touch($disk->path('.trashed/recent.md'), now()->subDays(3)->getTimestamp());

        $trashed = ListTrashedChapters::run();

        $this->assertSame(['recent', 'old'], $trashed->pluck('slug')->all());
        $this->assertSame('Recent', $trashed[0]->title);
        $this->assertNull($trashed[0]->emoji);
        $this->assertSame('🐉', $trashed[1]->emoji);
        $this->assertSame(now()->subDays(3)->getTimestamp(), $trashed[0]->trashedAt->getTimestamp());
        $this->assertSame(now()->addDays(27)->getTimestamp(), $trashed[0]->purgesAt->getTimestamp());
        $this->assertSame(27, $trashed[0]->daysRemaining);
        $this->assertSame(0, $trashed[1]->daysRemaining);
    }

    #[Test]
    public function it_ignores_trashed_files_whose_name_is_not_a_valid_slug(): void
    {
        $disk = Storage::disk('raid-information');
        $disk->put('.trashed/loot.md', '## Loot');
        $disk->put('.trashed/Loot Rules.md', '## Loot Rules');

        $this->assertSame(['loot'], ListTrashedChapters::run()->pluck('slug')->all());
    }

    #[Test]
    public function it_shows_the_full_retention_for_a_chapter_trashed_a_moment_ago(): void
    {
        $this->freezeSecond();
        $disk = Storage::disk('raid-information');
        $disk->put('.trashed/fresh.md', "## Fresh\nBody");
        touch($disk->path('.trashed/fresh.md'), now()->subSeconds(1)->getTimestamp());

        $this->assertSame(30, ListTrashedChapters::run()->first()->daysRemaining);
    }
}
