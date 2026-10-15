<?php

namespace App\Actions\Discord;

use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Models\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

/**
 * Creates or updates a user from a member of the Discord server and syncs their Discord roles.
 */
class UpsertUserFromGuildMember
{
    use AsAction;

    public function __construct(protected SyncUserDiscordRoles $syncUserDiscordRoles) {}

    /**
     * Store the member's user, returning null without writing when the member carries no user object.
     */
    public function handle(GuildMemberData $member): ?User
    {
        if ($member->user instanceof Optional) {
            return null;
        }

        $user = User::updateOrCreate(
            ['id' => $member->user->id],
            Arr::reject([
                'username' => $member->user->username,
                'discriminator' => $member->user->discriminator,
                'nickname' => $member->nick,
                'avatar' => $member->user->avatar,
                'guild_avatar' => $member->avatar,
            ], fn (mixed $value): bool => $value instanceof Optional),
        );

        $this->syncUserDiscordRoles->handle($user, $member->roles);

        return $user;
    }
}
