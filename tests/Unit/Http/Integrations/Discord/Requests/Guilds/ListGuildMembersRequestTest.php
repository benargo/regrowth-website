<?php

namespace Tests\Unit\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Http\Integrations\Discord\Requests\Guilds\ListGuildMembersRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class ListGuildMembersRequestTest extends DiscordTestCase
{
    use MocksDiscordService;

    #[Test]
    #[Group('happy-path')]
    public function it_lists_guild_members(): void
    {
        Saloon::fake([ListGuildMembersRequest::class => MockResponse::make([
            $this->makeDiscordGuildMemberWithUser('111111111111111111', 'thrall'),
            $this->makeDiscordGuildMemberWithUser('222222222222222222', 'jaina'),
        ])]);

        $members = $this->makeConnector()
            ->send(new ListGuildMembersRequest('829020506907869214', 500, '100'))
            ->dto();

        $this->assertCount(2, $members);
        $this->assertContainsOnlyInstancesOf(GuildMemberData::class, $members);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof ListGuildMembersRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/members'
            && $request->query()->all() === ['limit' => 500, 'after' => '100']);
    }

    #[Test]
    public function it_defaults_to_the_first_page_of_one_thousand(): void
    {
        $this->assertSame(['limit' => 1000, 'after' => '0'], (new ListGuildMembersRequest('829020506907869214'))->query()->all());
    }

    #[Test]
    #[TestWith([0, 1])]
    #[TestWith([-5, 1])]
    #[TestWith([1000, 1000])]
    #[TestWith([1001, 1000])]
    public function it_clamps_the_limit_to_discords_range(int $limit, int $expected): void
    {
        $this->assertSame($expected, (new ListGuildMembersRequest('829020506907869214', $limit))->query()->get('limit'));
    }

    #[Test]
    public function it_pages_forward_by_user_id(): void
    {
        $request = new ListGuildMembersRequest('829020506907869214');

        $this->assertSame('user.id', $request->cursorKey());
        $this->assertSame('after', $request->cursorDirection());
    }
}
