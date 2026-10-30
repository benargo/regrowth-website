<?php

namespace Tests\Feature\Actions\RaidInformation;

use App\Actions\RaidInformation\FindChapter;
use App\Actions\RaidInformation\ReadChapterOrder;
use App\Actions\RaidInformation\RestoreChapter;
use App\Actions\RaidInformation\WriteChapterOrder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class RestoreChapterTest extends TestCase
{
    #[Test]
    public function it_restores_to_the_end_of_the_order(): void
    {
        $disk = Storage::disk('raid-information');
        $disk->put('introduction.md', "## Intro\nBody");
        $disk->put('faq.md', "## FAQ\nBody");
        $disk->put('.trashed/loot.md', "## Loot\nBody");
        WriteChapterOrder::run(['introduction', 'faq']);

        RestoreChapter::run('loot');

        $this->assertSame("## Loot\nBody", $disk->get('loot.md'));
        $this->assertFalse($disk->exists('.trashed/loot.md'));
        $this->assertSame(['introduction', 'faq', 'loot'], ReadChapterOrder::run());
    }

    #[Test]
    public function it_dates_a_restored_chapter_to_the_restore(): void
    {
        $this->freezeSecond();
        $disk = Storage::disk('raid-information');
        $disk->put('.trashed/loot.md', "## Loot\nBody");
        touch($disk->path('.trashed/loot.md'), now()->subDays(10)->getTimestamp());

        RestoreChapter::run('loot');

        $this->assertGreaterThanOrEqual(now()->startOfSecond()->getTimestamp(), FindChapter::run('loot')->updatedAt->getTimestamp());
    }

    #[Test]
    #[Group('validation')]
    public function it_refuses_when_a_live_chapter_has_the_slug(): void
    {
        $disk = Storage::disk('raid-information');
        $disk->put('loot.md', "## Loot\nLive");
        $disk->put('.trashed/loot.md', "## Loot\nTrashed");

        try {
            RestoreChapter::run('loot');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertSame('A live chapter already uses “loot”. Delete or rename it first.', $exception->errors()['chapter'][0]);
        }

        $this->assertSame("## Loot\nLive", $disk->get('loot.md'));
        $this->assertSame("## Loot\nTrashed", $disk->get('.trashed/loot.md'));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_fails_without_creating_a_live_chapter_when_the_trashed_chapter_does_not_exist(): void
    {
        $disk = Storage::disk('raid-information');
        WriteChapterOrder::run(['introduction']);

        try {
            RestoreChapter::run('loot');
            $this->fail('Expected an UnableToReadFile exception.');
        } catch (UnableToReadFile) {
            $this->assertFalse($disk->exists('loot.md'));
            $this->assertSame(['introduction'], ReadChapterOrder::run());
        }
    }

    #[Test]
    public function it_does_not_duplicate_a_slug_already_in_the_order(): void
    {
        $disk = Storage::disk('raid-information');
        $disk->put('.trashed/loot.md', "## Loot\nBody");
        WriteChapterOrder::run(['introduction', 'loot', 'faq']);

        RestoreChapter::run('loot');

        $this->assertSame(['introduction', 'loot', 'faq'], ReadChapterOrder::run());
    }
}
