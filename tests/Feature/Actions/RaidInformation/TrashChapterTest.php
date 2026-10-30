<?php

namespace Tests\Feature\Actions\RaidInformation;

use App\Actions\RaidInformation\FindChapter;
use App\Actions\RaidInformation\ReadChapterOrder;
use App\Actions\RaidInformation\TrashChapter;
use App\Actions\RaidInformation\WriteChapterOrder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class TrashChapterTest extends TestCase
{
    #[Test]
    public function it_moves_the_chapter_to_the_trash_and_out_of_the_order(): void
    {
        $disk = Storage::disk('raid-information');
        $disk->put('introduction.md', "## Intro\nBody");
        $disk->put('loot.md', "## Loot\nBody");
        WriteChapterOrder::run(['introduction', 'loot']);

        TrashChapter::run('loot');

        $this->assertFalse($disk->exists('loot.md'));
        $this->assertSame("## Loot\nBody", $disk->get('.trashed/loot.md'));
        $this->assertSame(['introduction'], ReadChapterOrder::run());
        $this->assertNull(FindChapter::run('loot'));
    }

    #[Test]
    public function it_starts_the_trash_clock_when_the_chapter_is_trashed(): void
    {
        $this->freezeSecond();
        $disk = Storage::disk('raid-information');
        $disk->put('loot.md', "## Loot\nBody");
        touch($disk->path('loot.md'), now()->subDays(40)->getTimestamp());

        TrashChapter::run('loot');

        $this->assertGreaterThanOrEqual(now()->startOfSecond()->getTimestamp(), $disk->lastModified('.trashed/loot.md'));
    }

    #[Test]
    #[Group('validation')]
    public function it_refuses_to_trash_the_introduction(): void
    {
        $disk = Storage::disk('raid-information');
        $disk->put('introduction.md', "## Intro\nBody");

        try {
            TrashChapter::run('introduction');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertSame('The introduction can’t be deleted.', $exception->errors()['chapter'][0]);
        }

        $this->assertTrue($disk->exists('introduction.md'));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_fails_without_trashing_anything_when_the_chapter_does_not_exist(): void
    {
        $disk = Storage::disk('raid-information');
        WriteChapterOrder::run(['introduction', 'loot']);

        try {
            TrashChapter::run('loot');
            $this->fail('Expected an UnableToReadFile exception.');
        } catch (UnableToReadFile) {
            $this->assertFalse($disk->exists('.trashed/loot.md'));
            $this->assertSame(['introduction', 'loot'], ReadChapterOrder::run());
        }
    }
}
