<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Guild;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Data\Guild\GuildData;
use App\Http\Integrations\Blizzard\Exceptions\RealmRequiredException;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

#[Group('blizzard-integration')]
class GetGuildRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_casts_the_guild_with_its_member_count(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetGuildRequest::class => MockResponse::make(body: [
                'id' => 4761007,
                'name' => 'Regrowth',
                'faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance'],
                'member_count' => 623,
                'realm' => ['key' => ['href' => 'r'], 'name' => 'Thunderstrike', 'id' => 6409, 'slug' => 'thunderstrike'],
            ], status: 200),
        ]);

        $dto = $this->makeConnector()
            ->send(new GetGuildRequest('thunderstrike', 'regrowth', BlizzardNamespace::ANNIVERSARY))
            ->dto();

        $this->assertInstanceOf(GuildData::class, $dto);
        $this->assertSame(4761007, $dto->id);
        $this->assertSame('Regrowth', $dto->name);
        $this->assertSame(623, $dto->memberCount);
    }

    #[Test]
    public function it_builds_a_slugged_endpoint_from_raw_realm_and_guild_names(): void
    {
        $request = new GetGuildRequest('Thunderstrike', 'Wild Growth');

        $this->assertSame('/data/wow/guild/thunderstrike/wild-growth', $request->resolveEndpoint());
    }

    #[Test]
    public function it_keeps_diacritics_in_the_guild_name(): void
    {
        $request = new GetGuildRequest('Thunderstrike', 'Ténèbres Éternelles');

        $this->assertSame('/data/wow/guild/thunderstrike/ténèbres-éternelles', $request->resolveEndpoint());
    }

    #[Test]
    public function it_sends_the_profile_namespace_for_the_given_game_version(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetGuildRequest::class => MockResponse::make(body: ['id' => 1, 'name' => 'Regrowth', 'member_count' => 1], status: 200),
        ]);

        $this->makeConnector()->send(new GetGuildRequest('thunderstrike', 'regrowth', BlizzardNamespace::ANNIVERSARY));

        Saloon::assertSent(fn (Request $request, Response $response) => $request instanceof GetGuildRequest
            && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === 'profile-classicann-eu');
    }

    #[Test]
    #[Group('validation')]
    public function it_throws_when_realm_is_null_for_a_namespace_that_requires_one(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
        ]);

        $this->expectException(RealmRequiredException::class);

        $this->makeConnector()->send(new GetGuildRequest(null, 'regrowth', BlizzardNamespace::RETAIL));
    }
}
