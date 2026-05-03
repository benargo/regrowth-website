<?php

namespace Tests\Feature\Jobs\WarcraftLogs;

use App\Jobs\WarcraftLogs\FetchGuildTags;
use App\Models\GuildTag;
use App\Services\WarcraftLogs\GuildTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetchGuildTagsTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // Happy Path
    // ==========================================

    #[Test]
    public function it_syncs_guild_tags_via_the_service(): void
    {
        $guildTagsService = Mockery::mock(GuildTags::class);
        $guildTagsService->shouldReceive('toCollection')
            ->once()
            ->andReturn(collect([
                GuildTag::factory()->make(),
                GuildTag::factory()->make(),
            ]));

        $job = new FetchGuildTags;
        $job->handle($guildTagsService);
    }

    // ==========================================
    // Batch Cancellation
    // ==========================================

    #[Test]
    public function it_skips_execution_when_batch_is_cancelled(): void
    {
        $batch = Bus::batch([])->dispatch();
        $batch->cancel();

        $guildTagsService = Mockery::mock(GuildTags::class);
        $guildTagsService->shouldNotReceive('toCollection');
        $this->app->instance(GuildTags::class, $guildTagsService);

        $job = new FetchGuildTags;
        $job->batchId = $batch->id;
        dispatch_sync($job);
    }

    // ==========================================
    // Tags
    // ==========================================

    #[Test]
    public function it_has_the_correct_job_tags(): void
    {
        $this->assertSame(['warcraftlogs', 'guild-tags'], (new FetchGuildTags)->tags());
    }
}
