<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\UpsertUserFromGuildMember;
use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
class UpsertUserFromGuildMemberTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_creates_a_user_from_the_guild_member(): void
    {
        $member = GuildMemberData::from($this->makeDiscordGuildMemberResponse([
            'user' => [
                'id' => '400000000000000001',
                'username' => 'newraider',
                'discriminator' => '0',
                'avatar' => 'useravatarhash',
                'flags' => 0,
                'public_flags' => 0,
            ],
            'nick' => 'New Raider',
            'avatar' => 'guildavatarhash',
            'banner' => 'guildbannerhash',
        ]));

        $user = app(UpsertUserFromGuildMember::class)->handle($member);

        $this->assertSame('400000000000000001', $user->id);
        $this->assertDatabaseHas('users', [
            'id' => '400000000000000001',
            'username' => 'newraider',
            'discriminator' => '0',
            'nickname' => 'New Raider',
            'avatar' => 'useravatarhash',
            'guild_avatar' => 'guildavatarhash',
        ]);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_updates_an_existing_user(): void
    {
        User::factory()->create(['id' => '400000000000000001', 'nickname' => 'Old Nick']);

        app(UpsertUserFromGuildMember::class)->handle(GuildMemberData::from(
            $this->makeDiscordGuildMemberWithUser('400000000000000001', 'newraider', 'New Nick'),
        ));

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => '400000000000000001', 'nickname' => 'New Nick']);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_syncs_the_members_recognised_discord_roles(): void
    {
        $role = DiscordRole::factory()->create();

        $user = app(UpsertUserFromGuildMember::class)->handle(GuildMemberData::from(
            $this->makeDiscordGuildMemberResponse([
                'user' => ['id' => '400000000000000001', 'username' => 'newraider', 'discriminator' => '0', 'avatar' => null, 'flags' => 0, 'public_flags' => 0],
                'roles' => [$role->id, '999999999999999999'],
            ]),
        ));

        $this->assertSame([$role->id], $user->discordRoles()->pluck('id')->all());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_returns_null_and_stores_nothing_when_the_member_has_no_user(): void
    {
        $payload = $this->makeDiscordGuildMemberResponse();
        unset($payload['user']);

        $user = app(UpsertUserFromGuildMember::class)->handle(GuildMemberData::from($payload));

        $this->assertNull($user);
        $this->assertDatabaseCount('users', 0);
    }
}
