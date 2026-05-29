<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Profile;

use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterData;
use App\Http\Integrations\Blizzard\Requests\Profile\GetGuildRosterRequest;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

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
}
