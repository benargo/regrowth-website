<?php

namespace App\Services\Discord;

use App\Services\Discord\Exceptions\UserNotInGuildException;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;

class DiscordGuildService extends DiscordService
{
    protected string $guildId;

    public function __construct(string $botToken, string $guildId)
    {
        parent::__construct($botToken);
        $this->guildId = $guildId;
    }

    /**
     * Get guild member data for a Discord user.
     *
     * @param  string  $userId  The Discord user ID to fetch guild member data for.
     * @return array{
     *     user: array,
     *     nick: string|null,
     *     avatar: string|null,
     *     banner: string|null,
     *     roles: array,
     * }
     *
     * @throws UserNotInGuildException
     * @throws \RuntimeException
     */
    public function getGuildMember(string $userId): array
    {
        $response = $this->get("/guilds/{$this->guildId}/members/{$userId}");

        if ($response->status() === 404) {
            throw new UserNotInGuildException("User {$userId} is not a member of guild {$this->guildId}");
        }

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch guild member data: '.$response->body());
        }

        $data = $response->json();

        return [
            'user' => $data['user'] ?? [],
            'nick' => $data['nick'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'banner' => $data['banner'] ?? null,
            'roles' => $data['roles'] ?? [],
        ];
    }

    /**
     * List guild members with cursor-based pagination.
     *
     * Discord does not use page numbers — instead, the cursor encodes the highest Discord user
     * ID from the previous page, which is passed as the `after` query parameter. Fetches one
     * extra item beyond `$perPage` so that `CursorPaginator` can detect whether another page
     * exists. Each member is augmented with a top-level `id` key (the Discord user ID) so that
     * `CursorPaginator` can build the next cursor via direct key lookup.
     *
     * The returned `CursorPaginator` works directly with `Inertia::scroll()`:
     * ```php
     * $cursor = Cursor::fromEncoded(request('cursor'));
     * 'members' => Inertia::scroll(fn () => $service->listGuildMembers(cursor: $cursor))
     * ```
     *
     * @throws \RuntimeException
     */
    public function listGuildMembers(int $perPage = 100, ?Cursor $cursor = null): CursorPaginator
    {
        $after = $cursor?->parameter('id');

        $query = ['limit' => min($perPage + 1, 1000)];

        if ($after) {
            $query['after'] = $after;
        }

        $response = $this->get("/guilds/{$this->guildId}/members", $query);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to list guild members: '.$response->body());
        }

        // Surface the Discord user ID as a top-level 'id' so CursorPaginator can build
        // the next cursor via direct key lookup (dot-notation is not traversed in arrays).
        $members = array_map(
            fn (array $member) => ['id' => $member['user']['id']] + $member,
            $response->json(),
        );

        return new CursorPaginator(
            items: $members,
            perPage: $perPage,
            cursor: $cursor,
            options: [
                'path' => Paginator::resolveCurrentPath(),
                'cursorName' => 'cursor',
                'parameters' => ['id'],
            ],
        );
    }

    /**
     * Search for guild members whose username or nickname starts with the provided string.
     *
     * @param  string  $query  Query string to match username(s) and nickname(s) against.
     * @param  int  $limit  Max number of members to return (1-1000). Defaults to 1.
     * @return array<int, array{
     *     user: array,
     *     nick: string|null,
     *     avatar: string|null,
     *     roles: array,
     *     joined_at: string,
     *     deaf: bool,
     *     mute: bool
     * }>
     *
     * @throws \RuntimeException
     */
    public function searchGuildMembers(string $query, int $limit = 1): array
    {
        $response = $this->get("/guilds/{$this->guildId}/members/search", [
            'query' => $query,
            'limit' => min(max($limit, 1), 1000),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to search guild members: '.$response->body());
        }

        return $response->json();
    }
}
