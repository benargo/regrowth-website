<?php

namespace App\Actions\Discord;

use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Exceptions\RateLimitedException;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use App\Http\Integrations\Discord\Requests\Guilds\AddGuildMemberRequest;
use Illuminate\Container\Attributes\Config;
use Lorisleiva\Actions\Concerns\AsAction;
use SensitiveParameter;
use Spatie\LaravelData\Optional;

/**
 * Adds a user to the Discord server with their `guilds.join` access token.
 */
class AddGuildMember
{
    use AsAction;

    public function __construct(
        protected DiscordConnector $discord,
        #[Config('services.discord.server_id')] protected string $serverId,
    ) {}

    /**
     * Runs the action with the access token redacted from stack traces.
     *
     * @throws ApiException
     * @throws RateLimitedException
     * @throws UserNotInGuildException
     */
    public static function run(string $userId, #[SensitiveParameter] string $accessToken): GuildMemberData
    {
        return static::make()->handle($userId, $accessToken);
    }

    /**
     * Returns the member from Discord's response, or fetches them when the
     * response has no member body.
     *
     * @throws ApiException
     * @throws RateLimitedException
     * @throws UserNotInGuildException
     */
    public function handle(string $userId, #[SensitiveParameter] string $accessToken): GuildMemberData
    {
        $member = $this->discord->send(new AddGuildMemberRequest($this->serverId, $userId, $accessToken))->dto();

        if ($member === null || $member->user instanceof Optional) {
            return FetchGuildMember::run($userId);
        }

        return $member;
    }
}
