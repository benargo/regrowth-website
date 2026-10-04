<?php

namespace Tests\Feature\Jobs\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Http\Integrations\WarcraftLogs\Requests\GetGuildTagsRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Jobs\WarcraftLogs\FetchGuildTags;
use App\Models\GameVersion;
use App\Models\Phase;
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
    public function it_creates_the_guild_tags_returned_by_warcraft_logs(): void
    {
        $this->fakeGuildTags([
            ['id' => 101, 'name' => 'Main Raid'],
            ['id' => 102, 'name' => 'Alt Raid'],
        ]);

        $this->runJob(new FetchGuildTags($this->fetchableGameVersion()));

        $this->assertSame('Main Raid', GuildTag::find(101)?->name);
        $this->assertSame('Alt Raid', GuildTag::find(102)?->name);
    }

    #[Test]
    public function it_renames_an_existing_tag_without_touching_its_phase_or_attendance_flag(): void
    {
        $phase = Phase::factory()->create();
        GuildTag::factory()->withPhase($phase)->countsAttendance()->create(['id' => 101, 'name' => 'Old Name']);
        $this->fakeGuildTags([['id' => 101, 'name' => 'New Name']]);

        $this->runJob(new FetchGuildTags($this->fetchableGameVersion()));

        $guildTag = GuildTag::find(101);
        $this->assertSame('New Name', $guildTag->name);
        $this->assertTrue($guildTag->count_attendance);
        $this->assertSame($phase->id, $guildTag->phase_id);
    }

    #[Test]
    public function it_queries_the_game_versions_guild_on_its_namespace_host(): void
    {
        $this->fakeGuildTags([]);

        $this->runJob(new FetchGuildTags($this->fetchableGameVersion([
            'warcraftlogs_guild' => 774848,
            'warcraftlogs_namespace' => WarcraftLogsNamespace::Anniversary,
        ])));

        Saloon::assertSent(function (Request $request, Response $response): bool {
            return $request instanceof GetGuildTagsRequest
                && str_starts_with($response->getPendingRequest()->getUrl(), 'https://fresh.warcraftlogs.com/')
                && data_get($request->body()->all(), 'variables.id') === 774848;
        });
    }

    #[Test]
    public function it_skips_a_game_version_without_a_warcraft_logs_guild(): void
    {
        Saloon::fake([]);
        Log::spy();

        $this->runJob(new FetchGuildTags($this->fetchableGameVersion(['warcraftlogs_guild' => null])));

        Saloon::assertNothingSent();
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message): bool => str_contains($message, 'no Warcraft Logs guild')
        );
    }

    #[Test]
    public function it_skips_a_game_version_without_a_warcraft_logs_namespace(): void
    {
        Saloon::fake([]);
        Log::spy();

        $this->runJob(new FetchGuildTags($this->fetchableGameVersion(['warcraftlogs_namespace' => null])));

        Saloon::assertNothingSent();
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message): bool => str_contains($message, 'no Warcraft Logs namespace')
        );
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

        $this->runJob(new FetchGuildTags($this->fetchableGameVersion()));
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

        $this->runJob(new FetchGuildTags($this->fetchableGameVersion()));
    }

    // ==================== middleware ====================

    #[Test]
    public function it_skips_execution_when_batch_is_cancelled(): void
    {
        Saloon::fake([]);

        $batch = Bus::batch([])->dispatch();
        $batch->cancel();

        $job = new FetchGuildTags($this->fetchableGameVersion());
        $job->batchId = $batch->id;
        dispatch_sync($job);

        Saloon::assertNothingSent();
    }

    // ==================== tags ====================

    #[Test]
    #[Group('contract')]
    public function it_tags_the_job_with_its_game_version(): void
    {
        $gameVersion = $this->fetchableGameVersion();

        $this->assertSame(
            ['warcraftlogs', 'guild-tags', "game-version:{$gameVersion->id}"],
            (new FetchGuildTags($gameVersion))->tags(),
        );
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fetchableGameVersion(array $attributes = []): GameVersion
    {
        return GameVersion::factory()->create([
            'warcraftlogs_guild' => 774848,
            'warcraftlogs_namespace' => WarcraftLogsNamespace::Anniversary,
            ...$attributes,
        ]);
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
