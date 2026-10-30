<?php

namespace Tests\Feature\Actions\RaidInformation;

use App\Actions\RaidInformation\CreateChapter;
use App\Actions\RaidInformation\ReadChapterOrder;
use App\Actions\RaidInformation\WriteChapterOrder;
use App\Jobs\RaidInformation\ResolveChapterChannels;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class CreateChapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([ResolveChapterChannels::class]);
    }

    #[Test]
    public function it_creates_a_chapter_from_its_heading(): void
    {
        WriteChapterOrder::run(['introduction', 'loot']);
        Storage::disk('raid-information')->put('loot.md', "## Loot\nBody");

        $chapter = CreateChapter::run("## 🔄 Changing Mains or Specs\nBody");

        $this->assertSame('changing-mains-or-specs', $chapter->slug);
        $this->assertSame("## 🔄 Changing Mains or Specs\nBody", Storage::disk('raid-information')->get('changing-mains-or-specs.md'));
        $this->assertSame(['introduction', 'loot', 'changing-mains-or-specs'], ReadChapterOrder::run());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_slug_already_in_use(): void
    {
        WriteChapterOrder::run(['introduction', 'loot']);
        Storage::disk('raid-information')->put('loot.md', "## Loot\nBody");

        try {
            CreateChapter::run("## Loot\nOther");
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['content' => ['A chapter at “loot” already exists or is in Recently deleted. Change the heading.']], $e->errors());
        }

        $this->assertSame("## Loot\nBody", Storage::disk('raid-information')->get('loot.md'));
        $this->assertSame(['introduction', 'loot'], ReadChapterOrder::run());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_slug_in_the_trash(): void
    {
        WriteChapterOrder::run(['introduction']);
        Storage::disk('raid-information')->put('.trashed/loot.md', "## Loot\nBody");

        try {
            CreateChapter::run("## Loot\nOther");
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['content' => ['A chapter at “loot” already exists or is in Recently deleted. Change the heading.']], $e->errors());
        }

        Storage::disk('raid-information')->assertMissing('loot.md');
        $this->assertSame(['introduction'], ReadChapterOrder::run());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_the_reserved_introduction_slug(): void
    {
        WriteChapterOrder::run(['introduction']);

        try {
            CreateChapter::run("## Introduction\nBody");
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['content' => ['“introduction” is reserved. Change the heading.']], $e->errors());
        }

        Storage::disk('raid-information')->assertMissing('introduction.md');
        $this->assertSame(['introduction'], ReadChapterOrder::run());
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('headingsWithoutSlugText')]
    public function it_rejects_a_heading_with_no_text_for_the_slug(string $heading): void
    {
        WriteChapterOrder::run(['introduction']);

        try {
            CreateChapter::run("{$heading}\nBody");
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('content', $e->errors());
        }

        $this->assertSame(['introduction'], ReadChapterOrder::run());
        $this->assertSame([CreateChapter::ORDER_FILE], Storage::disk('raid-information')->allFiles());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function headingsWithoutSlugText(): array
    {
        return [
            'emoji only' => ['## 🎉'],
            'custom emoji and punctuation' => ['## <:pog:123> !!'],
        ];
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_content_over_the_limit_without_touching_the_order(): void
    {
        WriteChapterOrder::run(['introduction']);

        try {
            CreateChapter::run("## Long\n".str_repeat('a', 2100));
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('content', $e->errors());
        }

        $this->assertSame(['introduction'], ReadChapterOrder::run());
        Storage::disk('raid-information')->assertMissing('long.md');
    }
}
