<?php

namespace Tests\Feature\Actions\RaidInformation;

use App\Actions\RaidInformation\ListChapters;
use App\Actions\RaidInformation\ReadChapterOrder;
use App\Actions\RaidInformation\ReorderChapters;
use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ReorderChaptersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['introduction', 'loot', 'benching'] as $slug) {
            $this->putChapter($slug, "## {$slug}");
        }
    }

    #[Test]
    public function it_writes_the_new_order(): void
    {
        ReorderChapters::run(['benching', 'loot']);

        $this->assertSame(['introduction', 'benching', 'loot'], ReadChapterOrder::run());
        $this->assertSame(['introduction', 'benching', 'loot'], ListChapters::run()->pluck('slug')->all());
    }

    #[Test]
    public function it_validates_and_writes_inside_the_write_lock(): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')
            ->once()
            ->with(5, Mockery::type(Closure::class))
            ->andReturnUsing(function (int $seconds, Closure $callback): mixed {
                $this->assertSame([], ReadChapterOrder::run());

                return $callback();
            });

        $cache = Mockery::mock(Cache::getFacadeRoot());
        $cache->shouldReceive('lock')
            ->once()
            ->with(StoresRaidInformation::WRITE_LOCK, 10)
            ->andReturn($lock);
        Cache::swap($cache);

        ReorderChapters::run(['benching', 'loot']);

        $this->assertSame(['introduction', 'benching', 'loot'], ReadChapterOrder::run());
    }

    #[Test]
    public function it_does_not_touch_chapter_files(): void
    {
        $this->freezeSecond();
        $timestamp = now()->subDays(10)->startOfSecond()->getTimestamp();
        foreach (['introduction', 'loot', 'benching'] as $slug) {
            $this->putChapter($slug, "## {$slug}", $timestamp);
        }

        ReorderChapters::run(['benching', 'loot']);

        foreach (['introduction', 'loot', 'benching'] as $slug) {
            $this->assertSame($timestamp, Storage::disk('raid-information')->lastModified("{$slug}.md"));
            $this->assertSame("## {$slug}", Storage::disk('raid-information')->get("{$slug}.md"));
        }
    }

    #[Test]
    public function it_rejects_a_list_missing_a_chapter(): void
    {
        $this->assertRejected(['loot']);
    }

    #[Test]
    public function it_rejects_an_unknown_slug(): void
    {
        $this->assertRejected(['loot', 'ghost']);
    }

    #[Test]
    public function it_rejects_duplicates(): void
    {
        $this->assertRejected(['loot', 'loot']);
    }

    #[Test]
    public function it_rejects_the_introduction_in_the_list(): void
    {
        $this->assertRejected(['introduction', 'loot', 'benching']);
    }

    /**
     * @param  array<int, string>  $slugs
     */
    private function assertRejected(array $slugs): void
    {
        try {
            ReorderChapters::run($slugs);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['The chapter list has changed; reload the page and try again.'],
                $e->errors()['slugs'],
            );
        }

        $this->assertSame([], ReadChapterOrder::run());
    }

    private function putChapter(string $slug, string $content, ?int $mtime = null): void
    {
        Storage::disk('raid-information')->put("{$slug}.md", $content);

        if ($mtime !== null) {
            touch(Storage::disk('raid-information')->path("{$slug}.md"), $mtime);
        }
    }
}
