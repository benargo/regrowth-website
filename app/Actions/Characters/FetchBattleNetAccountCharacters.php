<?php

namespace App\Actions\Characters;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\Account\AccountCharacterData;
use App\Http\Integrations\Blizzard\Data\Account\WowAccountData;
use App\Http\Integrations\Blizzard\Exceptions\ApiException;
use App\Http\Integrations\Blizzard\Requests\Account\GetAccountProfileSummaryRequest;
use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lorisleiva\Actions\Concerns\AsAction;
use Saloon\Exceptions\Request\FatalRequestException;
use SensitiveParameter;

/**
 * Find the roster characters in a game version that belong to the Battle.net
 * account owning the access token.
 */
class FetchBattleNetAccountCharacters
{
    use AsAction;

    public function __construct(protected BlizzardConnector $blizzard) {}

    /**
     * @return Collection<int, Character>
     *
     * @throws ApiException when Blizzard rejects the token (401) or fails.
     * @throws FatalRequestException when Blizzard is unreachable.
     */
    public function handle(#[SensitiveParameter] string $accessToken, GameVersion $gameVersion): Collection
    {
        try {
            $summary = $this->blizzard
                ->send(new GetAccountProfileSummaryRequest($accessToken, $gameVersion->blizzard_namespace))
                ->dto();
        } catch (ApiException $exception) {
            if ($exception->getResponse()->status() === 404) {
                return new Collection;
            }

            throw $exception;
        }

        $characterIds = collect($summary->wowAccounts)
            ->flatMap(fn (WowAccountData $wowAccount): array => $wowAccount->characters)
            ->map(fn (AccountCharacterData $character): int => $character->id)
            ->unique()
            ->values();

        return Character::whereIn('id', $characterIds)
            ->where('game_version_id', $gameVersion->id)
            ->with(['playableRace', 'playableClass', 'linkedCharacters' => fn (BelongsToMany $query) => $query->where('is_main', true)])
            ->orderBy('name')
            ->get();
    }
}
