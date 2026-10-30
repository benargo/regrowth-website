<?php

namespace Tests\Feature\Actions\RaidInformation;

use App\Actions\RaidInformation\ListChapters;
use App\Actions\RaidInformation\PutChapter;
use App\Jobs\RaidInformation\ResolveChapterChannels;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class PutChapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([ResolveChapterChannels::class]);
    }

    // ==================== put ====================

    #[Test]
    public function it_writes_a_new_chapter_file(): void
    {
        $this->assertTrue(PutChapter::run('loot', "## Loot\nBody"));

        $this->assertSame("## Loot\nBody", Storage::disk('raid-information')->get('loot.md'));
    }

    #[Test]
    public function it_does_not_write_unchanged_content(): void
    {
        $this->putChapter('loot', "## Loot\nBody");
        $this->freezeSecond();
        $aged = $this->ageChapter('loot');

        $this->assertFalse(PutChapter::run('loot', "## Loot\nBody"));

        $this->assertSame($aged, $this->mtime('loot'));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_refuses_a_slug_outside_the_pattern(): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            PutChapter::run('../addon/x', '## X');
        } finally {
            $this->assertSame([], Storage::disk('raid-information')->allFiles());
        }
    }

    #[Test]
    public function it_flushes_the_chapter_cache(): void
    {
        $this->putChapter('loot', '## Loot');
        $this->assertSame('## Loot', ListChapters::run()->first()->content);

        PutChapter::run('loot', '## Changed');

        $this->assertSame('## Changed', ListChapters::run()->first()->content);
    }

    #[Test]
    public function it_does_not_flush_the_chapter_cache_for_unchanged_content(): void
    {
        $this->putChapter('loot', '## Loot');
        $this->assertSame('## Loot', ListChapters::run()->first()->content);
        $this->putChapter('loot', '## Changed');

        PutChapter::run('loot', '## Changed');

        $this->assertSame('## Loot', ListChapters::run()->first()->content);
    }

    // ==================== put — validation ====================

    #[Test]
    #[Group('validation')]
    public function it_rejects_content_over_the_limit(): void
    {
        try {
            PutChapter::run('loot', "## Loot\n".str_repeat('a', 2000));
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException) {
            $this->assertFalse(Storage::disk('raid-information')->exists('loot.md'));
        }
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_chapter_without_a_level_two_heading(): void
    {
        try {
            PutChapter::run('loot', "# Loot\nBody");
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException) {
            $this->assertFalse(Storage::disk('raid-information')->exists('loot.md'));
        }
    }

    #[Test]
    #[Group('validation')]
    public function it_accepts_a_level_one_heading_for_the_introduction(): void
    {
        $this->assertTrue(PutChapter::run('introduction', "# Welcome\nBody"));

        $this->assertSame("# Welcome\nBody", Storage::disk('raid-information')->get('introduction.md'));
    }

    // ==================== channel resolution ====================

    #[Test]
    public function it_dispatches_channel_resolution_for_mentioned_channels(): void
    {
        PutChapter::run('loot', "## Loot\n<#111> <#222> <#111> <@&333>");

        Queue::assertPushed(
            ResolveChapterChannels::class,
            fn (ResolveChapterChannels $job): bool => $job->channelIds === ['111', '222'],
        );
    }

    #[Test]
    public function it_does_not_dispatch_channel_resolution_without_channel_mentions(): void
    {
        PutChapter::run('loot', "## Loot\n<@&333> only");

        Queue::assertNotPushed(ResolveChapterChannels::class);
    }

    #[Test]
    public function it_does_not_dispatch_channel_resolution_for_unchanged_content(): void
    {
        $this->putChapter('loot', "## Loot\n<#111>");

        PutChapter::run('loot', "## Loot\n<#111>");

        Queue::assertNotPushed(ResolveChapterChannels::class);
    }

    private function putChapter(string $slug, string $content): void
    {
        Storage::disk('raid-information')->put("{$slug}.md", $content);
    }

    private function ageChapter(string $slug): int
    {
        $timestamp = now()->subDays(3)->getTimestamp();

        touch(Storage::disk('raid-information')->path("{$slug}.md"), $timestamp);

        return $timestamp;
    }

    private function mtime(string $slug): int
    {
        clearstatcache();

        return filemtime(Storage::disk('raid-information')->path("{$slug}.md"));
    }
}
