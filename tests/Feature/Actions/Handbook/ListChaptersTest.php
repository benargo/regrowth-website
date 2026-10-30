<?php

namespace Tests\Feature\Actions\Handbook;

use App\Actions\Handbook\CountDiscordCharacters;
use App\Actions\Handbook\ListChapters;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ListChaptersTest extends TestCase
{
    #[Test]
    public function it_lists_chapters_in_order_with_their_details(): void
    {
        $this->freezeSecond();
        $timestamp = now()->subDays(3)->startOfSecond()->getTimestamp();
        $this->putChapter('introduction', '## Intro');
        $this->putChapter('loot', "## 💰 Loot\nBody", $timestamp);
        $this->order(['introduction', 'loot']);

        $chapter = ListChapters::run()->last();

        $this->assertSame('loot', $chapter->slug);
        $this->assertSame('Loot', $chapter->title);
        $this->assertSame('💰', $chapter->emoji);
        $this->assertSame("## 💰 Loot\nBody", $chapter->content);
        $this->assertSame(CountDiscordCharacters::run("## 💰 Loot\nBody"), $chapter->characterCount);
        $this->assertSame(sha1("## 💰 Loot\nBody"), $chapter->hash);
        $this->assertTrue($chapter->updatedAt->equalTo(now()->subDays(3)->startOfSecond()));
        $this->assertFalse($chapter->isIntroduction);
        $this->assertTrue(ListChapters::run()->first()->isIntroduction);
    }

    #[Test]
    public function it_skips_listed_slugs_without_a_file(): void
    {
        $this->putChapter('introduction', '## Intro');
        $this->putChapter('loot', '## Loot');
        $this->order(['introduction', 'ghost', 'loot']);

        $this->assertSame(['introduction', 'loot'], ListChapters::run()->pluck('slug')->all());
    }

    #[Test]
    public function it_appends_unlisted_files_alphabetically(): void
    {
        foreach (['introduction', 'loot', 'zebra', 'alpha'] as $slug) {
            $this->putChapter($slug, "## {$slug}");
        }
        $this->order(['introduction', 'loot']);

        $this->assertSame(['introduction', 'loot', 'alpha', 'zebra'], ListChapters::run()->pluck('slug')->all());
    }

    #[Test]
    public function it_always_lists_the_introduction_first(): void
    {
        $this->putChapter('introduction', '## Intro');
        $this->putChapter('benching', '## Benching');
        $this->order(['benching', 'introduction']);

        $this->assertSame(['introduction', 'benching'], ListChapters::run()->pluck('slug')->all());

        Cache::tags(['handbook'])->flush();
        $this->order(['benching']);

        $this->assertSame(['introduction', 'benching'], ListChapters::run()->pluck('slug')->all());
    }

    #[Test]
    public function it_ignores_trashed_files(): void
    {
        $this->putChapter('introduction', '## Intro');
        Storage::disk('handbook')->put('.trashed/old.md', '## Old');

        $this->assertSame(['introduction'], ListChapters::run()->pluck('slug')->all());
    }

    #[Test]
    public function it_ignores_files_whose_name_is_not_a_valid_slug(): void
    {
        foreach (['introduction', 'loot', 'Loot Rules', 'UPPER', 'snake_case', ''] as $slug) {
            $this->putChapter($slug, '## Chapter');
        }

        $this->assertSame(['introduction', 'loot'], ListChapters::run()->pluck('slug')->all());
    }

    #[Test]
    public function it_lists_a_digit_only_slug_as_a_string(): void
    {
        $this->putChapter('introduction', '## Intro');
        $this->putChapter('2024', '## 2024');

        $this->assertSame(['introduction', '2024'], ListChapters::run()->pluck('slug')->all());
    }

    #[Test]
    public function it_caches_the_listing(): void
    {
        $this->putChapter('introduction', '## Intro');
        $this->assertCount(1, ListChapters::run());

        $this->putChapter('loot', '## Loot');
        $this->assertCount(1, ListChapters::run());

        Cache::tags(['handbook'])->flush();
        $this->assertCount(2, ListChapters::run());
    }

    #[Test]
    public function it_returns_the_same_chapters_from_a_serializing_cache_store(): void
    {
        config([
            'cache.serializable_classes' => false,
            'cache.stores.array.serialize' => true,
        ]);
        Cache::purge('array');

        $this->putChapter('introduction', '## Intro');
        $this->putChapter('loot', "## 💰 Loot\nBody");
        $this->order(['introduction', 'loot']);

        $first = ListChapters::run();
        $second = ListChapters::run();

        $this->assertEquals($first, $second);
        $this->assertSame(['introduction', 'loot'], $second->pluck('slug')->all());
    }

    /**
     * @param  array<int, string>  $slugs
     */
    private function order(array $slugs): void
    {
        Storage::disk('handbook')->put('chapters.json', json_encode($slugs));
    }

    private function putChapter(string $slug, string $content, ?int $mtime = null): void
    {
        Storage::disk('handbook')->put("{$slug}.md", $content);

        if ($mtime !== null) {
            touch(Storage::disk('handbook')->path("{$slug}.md"), $mtime);
        }
    }
}
