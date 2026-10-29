<?php

namespace App\Actions\GuildRosterManager;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Exceptions\RealmRequiredException;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRequest;
use App\Models\GameVersion;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Saloon\Exceptions\SaloonException;

/**
 * Reads a game version's live guild member count from Blizzard's guild
 * endpoint, which is far cheaper than fetching and counting the full roster.
 */
class CountGuildMembers
{
    use AsAction;

    public function __construct(
        protected BlizzardConnector $blizzardConnector,
    ) {}

    public function handle(GameVersion $gameVersion): ?int
    {
        try {
            return $this->blizzardConnector->send(new GetGuildRequest(
                $gameVersion->realm_slug,
                $gameVersion->guild_slug,
                $gameVersion->blizzard_namespace,
            ))->dto()->memberCount;
        } catch (BlizzardRequestException|SaloonException|RealmRequiredException $exception) {
            Log::warning('Skipped guild member count: Blizzard could not return the guild.', [
                'game_version_id' => $gameVersion->id,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
