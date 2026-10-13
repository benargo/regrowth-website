<?php

namespace Tests\Unit\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\RoleData;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildRolesRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class GetGuildRolesRequestTest extends DiscordTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_lists_the_guild_roles(): void
    {
        Saloon::fake([GetGuildRolesRequest::class => MockResponse::make([
            ['id' => '111111111111111111', 'name' => 'Officer', 'colors' => ['primary_color' => 0], 'hoist' => false, 'position' => 10, 'permissions' => '0', 'managed' => false, 'mentionable' => false, 'flags' => 0],
        ])]);

        $roles = $this->makeConnector()
            ->send(new GetGuildRolesRequest('829020506907869214'))
            ->dto();

        $this->assertContainsOnlyInstancesOf(RoleData::class, $roles);
        $this->assertSame('Officer', $roles->first()->name);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetGuildRolesRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/roles');
    }
}
