<?php

namespace Tests\Unit\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Http\Integrations\Discord\Requests\Guilds\SearchGuildMembersRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class SearchGuildMembersRequestTest extends DiscordTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_searches_guild_members_by_query(): void
    {
        Saloon::fake([SearchGuildMembersRequest::class => MockResponse::make([
            ['user' => ['id' => '111111111111111111', 'username' => 'thrall', 'discriminator' => '0', 'flags' => 0, 'public_flags' => 0], 'nick' => null, 'roles' => [], 'deaf' => false, 'mute' => false, 'flags' => 0],
        ])]);

        $members = $this->makeConnector()
            ->send(new SearchGuildMembersRequest('829020506907869214', 'thr', 5))
            ->dto();

        $this->assertContainsOnlyInstancesOf(GuildMemberData::class, $members);
        $this->assertCount(1, $members);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof SearchGuildMembersRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/members/search'
            && $request->query()->all() === ['query' => 'thr', 'limit' => 5]);
    }

    #[Test]
    public function it_defaults_the_limit_to_one(): void
    {
        $this->assertSame(['query' => 'thr', 'limit' => 1], (new SearchGuildMembersRequest('829020506907869214', 'thr'))->query()->all());
    }

    #[Test]
    #[TestWith([0, 1])]
    #[TestWith([-5, 1])]
    #[TestWith([1000, 1000])]
    #[TestWith([1001, 1000])]
    public function it_clamps_the_limit_to_discords_range(int $limit, int $expected): void
    {
        $request = new SearchGuildMembersRequest('829020506907869214', 'thr', $limit);

        $this->assertSame($expected, $request->query()->get('limit'));
    }
}
