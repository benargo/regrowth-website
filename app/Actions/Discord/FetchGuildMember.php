<?php

namespace App\Actions\Discord;

use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildMemberRequest;
use Illuminate\Container\Attributes\Config;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Fetches a member of the Discord server by their Discord user ID.
 */
class FetchGuildMember
{
    use AsAction;

    public function __construct(
        protected DiscordConnector $discord,
        #[Config('services.discord.server_id')] protected string $serverId,
    ) {}

    /**
     * @throws UserNotInGuildException
     * @throws ApiException
     */
    public function handle(string $memberId): GuildMemberData
    {
        return $this->discord->send(new GetGuildMemberRequest($this->serverId, $memberId))->dto();
    }
}
