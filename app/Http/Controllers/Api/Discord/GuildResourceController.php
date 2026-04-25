<?php

namespace App\Http\Controllers\Api\Discord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discord\SearchGuildMembersRequest;
use App\Services\Discord\Discord;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class GuildResourceController extends Controller
{
    public function __construct(
        protected Discord $discord
    ) {}

    /**
     * Search for guild members by username or nickname prefix.
     *
     * @return JsonResponse<array<int, array{id: string, nickname: string|null, username: string}>>
     */
    public function searchMembers(SearchGuildMembersRequest $request): JsonResponse
    {
        $query = $request->string('query')->toString();
        $limit = $request->integer('limit', 1);

        $results = Cache::tags(['discord'])->remember(
            'discord:members:search:'.mb_strtolower($query),
            now()->addMinutes(5),
            fn () => $this->discord->searchGuildMembers($query, $limit)
        );

        return response()->json(
            collect($results)->map(fn ($member) => [
                'id' => $member->user?->id,
                'nickname' => $member->nick,
                'username' => $member->user?->username,
            ])
        );
    }
}
