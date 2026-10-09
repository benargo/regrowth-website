<?php

namespace Tests\Feature\Jobs\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Http\Integrations\WarcraftLogs\Requests\GetGuildTagsRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Jobs\WarcraftLogs\FetchGuildTags;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Tests\Concerns\FakesWarcraftLogs;
use Tests\TestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class FetchGuildTagsTest extends TestCase
{
    use FakesWarcraftLogs;
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_creates_the_guild_tags_returned_by_warcraft_logs_in_the_guild(): void
    {
        $guild = $this->guild();
        $this->fakeGuildTags([
            ['id' => 101, 'name' => 'Main Raid'],
            ['id' => 102, 'name' => 'Alt Raid'],
        ]);

        $this->runJob(new FetchGuildTags($guild));

        $this->assertDatabaseHas('warcraft_logs_guild_tags', ['id' => 101, 'name' => 'Main Raid', 'warcraft_logs_guild_id' => $guild->id]);
        $this->assertDatabaseHas('warcraft_logs_guild_tags', ['id' => 102, 'name' => 'Alt Raid', 'warcraft_logs_guild_id' => $guild->id]);
    }

    #[Test]
    public function it_renames_an_existing_tag_without_touching_its_attendance_flag(): void
    {
        $guild = $this->guild();
        GuildTag::factory()->forGuild($guild)->countsAttendance()->create(['id' => 101, 'name' => 'Old Name']);
        $this->fakeGuildTags([['id' => 101, 'name' => 'New Name']]);

        $this->runJob(new FetchGuildTags($guild));

        $guildTag = GuildTag::find(101);
        $this->assertSame('New Name', $guildTag->name);
        $this->assertTrue($guildTag->count_attendance);
    }

    #[Test]
    public function it_adopts_an_existing_tag_that_has_no_guild(): void
    {
        $guild = $this->guild();
        GuildTag::factory()->create(['id' => 101, 'name' => 'Main Raid']);
        $this->fakeGuildTags([['id' => 101, 'name' => 'Main Raid']]);

        $this->runJob(new FetchGuildTags($guild));

        $this->assertSame($guild->id, GuildTag::find(101)->warcraft_logs_guild_id);
    }

    #[Test]
    #[Group('edge-case')]
    public function it_does_not_move_a_tag_that_belongs_to_another_guild(): void
    {
        Log::spy();
        $otherGuild = Guild::factory()->create();
        GuildTag::factory()->forGuild($otherGuild)->create(['id' => 101, 'name' => 'Theirs']);
        $guild = $this->guild();
        $this->fakeGuildTags([['id' => 101, 'name' => 'Ours'], ['id' => 102, 'name' => 'Alt Raid']]);

        $this->runJob(new FetchGuildTags($guild));

        $this->assertDatabaseHas('warcraft_logs_guild_tags', ['id' => 101, 'name' => 'Theirs', 'warcraft_logs_guild_id' => $otherGuild->id]);
        $this->assertDatabaseHas('warcraft_logs_guild_tags', ['id' => 102, 'warcraft_logs_guild_id' => $guild->id]);
        Log::shouldHaveReceived('warning')->once()->with(
            "Skipping Warcraft Logs tag 101 for guild {$guild->id}: it already belongs to guild {$otherGuild->id}."
        );
    }

    #[Test]
    public function it_queries_the_guild_on_its_namespace_host(): void
    {
        $this->fakeGuildTags([]);

        $this->runJob(new FetchGuildTags($this->guild(774848, WarcraftLogsNamespace::Anniversary)));

        Saloon::assertSent(function (Request $request, Response $response): bool {
            return $request instanceof GetGuildTagsRequest
                && str_starts_with($response->getPendingRequest()->getUrl(), 'https://fresh.warcraftlogs.com/')
                && data_get($request->body()->all(), 'variables.id') === 774848;
        });
    }

    #[Test]
    #[Group('error-handling')]
    public function it_lets_a_missing_guild_propagate(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => MockResponse::make(['data' => ['guildData' => ['guild' => null]]]),
        ]);

        $this->expectException(GuildNotFoundException::class);

        $this->runJob(new FetchGuildTags($this->guild()));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_lets_a_rate_limit_propagate(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => MockResponse::make(['error' => 'Too Many Requests'], 429),
        ]);

        $this->expectException(RateLimitReachedException::class);

        $this->runJob(new FetchGuildTags($this->guild()));
    }

    // ==================== middleware ====================

    #[Test]
    public function it_skips_execution_when_batch_is_cancelled(): void
    {
        Saloon::fake([]);

        $batch = Bus::batch([])->dispatch();
        $batch->cancel();

        $job = new FetchGuildTags($this->guild());
        $job->batchId = $batch->id;
        dispatch_sync($job);

        Saloon::assertNothingSent();
    }

    // ==================== tags ====================

    #[Test]
    #[Group('contract')]
    public function it_tags_the_job_with_its_guild(): void
    {
        $guild = $this->guild();

        $this->assertSame(
            ['warcraftlogs', 'guild-tags', "warcraft-logs-guild:{$guild->id}"],
            (new FetchGuildTags($guild))->tags(),
        );
    }

    // ==================== helpers ====================

    private function guild(int $id = 774848, WarcraftLogsNamespace $namespace = WarcraftLogsNamespace::Anniversary): Guild
    {
        return Guild::factory()->create(['id' => $id, 'namespace' => $namespace]);
    }

    private function runJob(FetchGuildTags $job): void
    {
        $job->handle($this->makeConnector());
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $tags
     */
    private function fakeGuildTags(array $tags): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => MockResponse::make($this->guildTagsPayload($tags)),
        ]);
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $tags
     * @return array<string, mixed>
     */
    private function guildTagsPayload(array $tags): array
    {
        return ['data' => ['guildData' => ['guild' => ['id' => 774848, 'tags' => $tags]]]];
    }
}
