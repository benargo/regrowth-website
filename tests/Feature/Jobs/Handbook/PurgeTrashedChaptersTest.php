<?php

namespace Tests\Feature\Jobs\Handbook;

use App\Jobs\Handbook\PurgeTrashedChapters;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class PurgeTrashedChaptersTest extends TestCase
{
    #[Test]
    public function it_deletes_chapters_trashed_more_than_thirty_days_ago(): void
    {
        $this->freezeSecond();
        Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context): bool => $context === ['purged' => 1]);
        $disk = Storage::disk('handbook');
        $disk->put('.trashed/old.md', "## Old\nBody");
        touch($disk->path('.trashed/old.md'), now()->subDays(31)->getTimestamp());

        (new PurgeTrashedChapters)->handle(Storage::disk('handbook'));

        $this->assertFalse($disk->exists('.trashed/old.md'));
    }

    #[Test]
    public function it_flushes_the_chapter_cache_after_purging(): void
    {
        $this->freezeSecond();
        Cache::tags(['handbook'])->put('canary', 'value', 60);
        $disk = Storage::disk('handbook');
        $disk->put('.trashed/old.md', "## Old\nBody");
        touch($disk->path('.trashed/old.md'), now()->subDays(31)->getTimestamp());

        (new PurgeTrashedChapters)->handle(Storage::disk('handbook'));

        $this->assertNull(Cache::tags(['handbook'])->get('canary'));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_deletes_nothing_while_another_write_holds_the_lock(): void
    {
        $this->freezeSecond();
        Sleep::fake(syncWithCarbon: true);
        $disk = Storage::disk('handbook');
        $disk->put('.trashed/old.md', "## Old\nBody");
        touch($disk->path('.trashed/old.md'), now()->subDays(31)->getTimestamp());
        Cache::lock('handbook:write', 10)->get();

        try {
            (new PurgeTrashedChapters)->handle(Storage::disk('handbook'));
            $this->fail('Expected a LockTimeoutException.');
        } catch (LockTimeoutException) {
            $this->assertTrue($disk->exists('.trashed/old.md'));
        }
    }

    #[Test]
    public function it_keeps_chapters_trashed_within_thirty_days(): void
    {
        $this->freezeSecond();
        $disk = Storage::disk('handbook');
        $disk->put('.trashed/recent.md', "## Recent\nBody");
        touch($disk->path('.trashed/recent.md'), now()->subDays(29)->getTimestamp());

        (new PurgeTrashedChapters)->handle(Storage::disk('handbook'));

        $this->assertTrue($disk->exists('.trashed/recent.md'));
    }

    #[Test]
    public function it_never_touches_live_chapters(): void
    {
        $this->freezeSecond();
        $disk = Storage::disk('handbook');
        $disk->put('loot.md', "## Loot\nBody");
        touch($disk->path('loot.md'), now()->subDays(90)->getTimestamp());

        (new PurgeTrashedChapters)->handle(Storage::disk('handbook'));

        $this->assertTrue($disk->exists('loot.md'));
    }
}
