<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Guild;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterData;
use App\Http\Integrations\Blizzard\Exceptions\RealmRequiredException;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

#[Group('blizzard-integration')]
class GetGuildRosterRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_casts_roster_with_members(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetGuildRosterRequest::class => MockResponse::make(body: [
                'guild' => ['key' => ['href' => 'g'], 'name' => 'Regrowth', 'id' => 1234],
                'members' => [
                    [
                        'character' => [
                            'key' => ['href' => 'c'],
                            'name' => 'Foo', 'id' => 111, 'level' => 60,
                            'playable_class' => ['key' => ['href' => 'pc'], 'id' => 1],
                            'playable_race' => ['key' => ['href' => 'pr'], 'id' => 1],
                            'realm' => ['key' => ['href' => 'r'], 'id' => 1234, 'name' => 'Thunderstrike'],
                        ],
                        'rank' => 0,
                    ],
                ],
            ], status: 200),
        ]);

        $dto = $this->makeConnector()
            ->send(new GetGuildRosterRequest('thunderstrike', 'regrowth'))
            ->dto();

        $this->assertInstanceOf(GuildRosterData::class, $dto);
        $this->assertSame('Regrowth', $dto->guild->name);
        $this->assertCount(1, $dto->members);
        $this->assertSame('Foo', $dto->members[0]->character->name);
        $this->assertSame(0, $dto->members[0]->rank);

        Saloon::assertSent(fn (GetGuildRosterRequest $r) => $r->resolveEndpoint() === '/data/wow/guild/thunderstrike/regrowth/roster'
        );
    }

    #[Test]
    public function it_builds_a_slugged_endpoint_from_raw_realm_and_guild_names(): void
    {
        $request = new GetGuildRosterRequest('Thunderstrike', 'Wild Growth');

        $this->assertSame('/data/wow/guild/thunderstrike/wild-growth/roster', $request->resolveEndpoint());
    }

    #[Test]
    public function it_keeps_diacritics_in_the_guild_name(): void
    {
        $request = new GetGuildRosterRequest('Thunderstrike', 'Ténèbres Éternelles');

        $this->assertSame('/data/wow/guild/thunderstrike/ténèbres-éternelles/roster', $request->resolveEndpoint());
    }

    #[Test]
    #[Group('validation')]
    public function it_throws_when_realm_is_null_for_a_namespace_that_requires_one(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
        ]);

        $this->expectException(RealmRequiredException::class);

        $this->makeConnector()->send(
            new GetGuildRosterRequest(null, 'regrowth', BlizzardNamespace::RETAIL),
        );
    }

    #[Test]
    public function it_omits_the_realm_segment_when_forever_has_no_realm(): void
    {
        $request = new GetGuildRosterRequest(null, 'Wild Growth', BlizzardNamespace::FOREVER);

        $this->assertSame('/data/wow/guild/wild-growth/roster', $request->resolveEndpoint());
    }

    #[Test]
    public function it_ignores_a_realm_given_in_the_forever_namespace(): void
    {
        $request = new GetGuildRosterRequest('Thunderstrike', 'Wild Growth', BlizzardNamespace::FOREVER);

        $this->assertSame('/data/wow/guild/wild-growth/roster', $request->resolveEndpoint());
    }

    // ==================== namespace shapes ====================

    #[Test]
    #[Group('happy-path')]
    public function it_casts_forever_members_with_surnames_and_no_realm(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetGuildRosterRequest::class => MockResponse::make(body: [
                'guild' => ['key' => ['href' => 'g'], 'name' => 'Regrowth', 'id' => 1234],
                'members' => [
                    [
                        'character' => [
                            'key' => ['href' => 'c'],
                            'name' => 'Thrall',
                            'surname' => 'Stormrage',
                            'id' => 111,
                            'level' => 60,
                            'playable_class' => ['key' => ['href' => 'pc'], 'id' => 7],
                            'playable_race' => ['key' => ['href' => 'pr'], 'id' => 2],
                        ],
                        'rank' => 0,
                    ],
                ],
            ], status: 200),
        ]);

        $dto = $this->makeConnector()
            ->send(new GetGuildRosterRequest(null, 'regrowth', BlizzardNamespace::FOREVER))
            ->dto();

        $this->assertSame('Thrall Stormrage', $dto->members[0]->character->fullName());
    }
}
