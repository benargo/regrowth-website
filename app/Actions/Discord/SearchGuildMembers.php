<?php

namespace App\Actions\Discord;

use App\Services\Discord\Discord;
use App\Services\Discord\Resources\GuildMember;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Searches the Discord server's members by username or nickname prefix, for
 * any page that offers the UserSearch component through an optional
 * `discord_members` prop.
 */
class SearchGuildMembers
{
    use AsAction;

    public function __construct(
        protected Discord $discord
    ) {}

    /**
     * @param  array{member_search?: mixed, member_limit?: mixed}  $input
     * @return array<int, array{id: string|null, nickname: string|null, username: string|null}>
     *
     * @throws ValidationException
     */
    public function handle(array $input): array
    {
        $validated = validator($input, [
            'member_search' => ['required', 'string'],
            'member_limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ])->validate();

        $query = $validated['member_search'];
        $limit = (int) ($validated['member_limit'] ?? 1);

        // Cache plain arrays: cache.serializable_classes is false, so cached objects can't be unserialized.
        return Cache::tags(['discord'])->remember(
            "discord:members:search:{$limit}:".mb_strtolower($query),
            now()->addMinutes(5),
            fn (): array => $this->discord->searchGuildMembers($query, $limit)
                ->map(fn (GuildMember $member): array => [
                    'id' => $member->user?->id,
                    'nickname' => $member->nick,
                    'username' => $member->user?->username,
                ])
                ->all()
        );
    }
}
