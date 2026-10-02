<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\CountGuildMembers;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRequest;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class CountGuildMembersTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    #[Group('happy-path')]
    #[Test]
    public function it_returns_the_guilds_member_count(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $this->mockGetGuild(['member_count' => 623]);
        $this->applyBlizzardMocks();

        $this->assertSame(623, CountGuildMembers::run($gameVersion));
    }

    #[Test]
    public function it_requests_the_given_game_versions_guild_realm_and_namespace(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create(['guild_name' => 'Regrowth']);
        $this->mockGetGuild();
        $this->applyBlizzardMocks();

        CountGuildMembers::run($gameVersion);

        Saloon::assertSent(fn (Request $request, Response $response) => $request instanceof GetGuildRequest
            && $request->resolveEndpoint() === '/data/wow/guild/thunderstrike/regrowth'
            && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === 'profile-classicann-eu');
    }

    /**
     * @return array<string, array{int}>
     */
    public static function blizzardFailureStatuses(): array
    {
        return [
            'not found' => [404],
            'rate limited' => [429],
            'server error' => [500],
        ];
    }

    #[DataProvider('blizzardFailureStatuses')]
    #[Group('error-handling')]
    #[Test]
    public function it_returns_null_when_blizzard_fails_with_status(int $status): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $this->pendingBlizzardMocks = [
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
            GetGuildRequest::class => MockResponse::make(
                body: ['code' => $status, 'type' => "BLZWEBAPI00000{$status}", 'detail' => 'Failed'],
                status: $status,
            ),
        ];
        $this->applyBlizzardMocks();

        $this->assertNull(CountGuildMembers::run($gameVersion));
        // The Action also swallows a missing fake, so prove the faked failure is what produced null.
        Saloon::assertSent(GetGuildRequest::class);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_returns_null_when_the_game_version_needs_a_realm_but_has_none(): void
    {
        $gameVersion = GameVersion::factory()->create(['realm' => null, 'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY]);
        $this->mockGetGuild();
        $this->applyBlizzardMocks();

        $this->assertNull(CountGuildMembers::run($gameVersion));
        Saloon::assertNotSent(GetGuildRequest::class);
    }
}
