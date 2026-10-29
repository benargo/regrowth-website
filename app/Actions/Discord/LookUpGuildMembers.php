<?php

namespace App\Actions\Discord;

use App\Jobs\Discord\SearchGuildMembers;
use App\Models\User;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Backs the optional `discord_members` prop for any page that offers UserSearch.
 * Never calls Discord: it returns cached results, or queues SearchGuildMembers on
 * the `high` queue and reports `pending`.
 */
class LookUpGuildMembers
{
    use AsAction;

    public function __construct(
        #[Config('services.discord.server_id')] protected string $serverId,
    ) {}

    /**
     * @param  array{member_search?: mixed, member_limit?: mixed}  $input
     * @return array{status: 'ready'|'pending', members: array<int, array{id: string|null, nickname?: string|null, username: string|null}>}
     *
     * @throws ValidationException
     */
    public function handle(array $input, User $requestedBy): array
    {
        $validated = validator($input, [
            'member_search' => ['required', 'string'],
            'member_limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ])->validate();

        $query = $validated['member_search'];
        $limit = (int) ($validated['member_limit'] ?? 1);

        $members = Cache::tags(['discord'])->get(SearchGuildMembers::cacheKey($this->serverId, $limit, $query));

        if (is_array($members)) {
            return ['status' => 'ready', 'members' => $members];
        }

        SearchGuildMembers::dispatch($query, $limit, $requestedBy->id);

        return ['status' => 'pending', 'members' => []];
    }
}
