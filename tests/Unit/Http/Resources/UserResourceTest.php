<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\CharacterResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\UserResource;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\PlannedAbsence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('auth')]
class UserResourceTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.discord.server_id', '123456789');
    }

    #[Test]
    public function it_returns_exactly_the_expected_keys_when_no_relations_are_loaded(): void
    {
        $user = User::factory()->create();

        $array = (new UserResource($user))->resolve(new Request);

        $this->assertSame(
            ['id', 'username', 'discriminator', 'nickname', 'display_name', 'avatar', 'banner', 'roles', 'highest_role', 'admin'],
            array_keys($array),
        );
    }

    // ==================== id ====================

    #[Test]
    public function it_returns_id(): void
    {
        $user = User::factory()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame($user->id, $array['id']);
    }

    // ==================== username, discriminator, and nickname ====================

    #[Test]
    public function it_returns_username(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('testuser', $array['username']);
    }

    #[Test]
    public function it_returns_discriminator(): void
    {
        $user = User::factory()->create(['discriminator' => '1234']);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('1234', $array['discriminator']);
    }

    #[Test]
    public function it_returns_nickname(): void
    {
        $user = User::factory()->create(['nickname' => 'TestNick']);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('TestNick', $array['nickname']);
    }

    #[Test]
    public function it_returns_null_nickname_when_not_set(): void
    {
        $user = User::factory()->create(['nickname' => null]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['nickname']);
    }

    // ==================== display name ====================

    #[Test]
    public function it_returns_display_name_as_nickname_when_set(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'nickname' => 'TestNick',
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('TestNick', $array['display_name']);
    }

    #[Test]
    public function it_returns_display_name_as_username_when_no_nickname(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'nickname' => null,
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('testuser', $array['display_name']);
    }

    // ==================== avatar url ====================

    #[Test]
    public function it_returns_guild_avatar_url_when_guild_avatar_is_set(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'guild_avatar' => 'abc123',
            'avatar' => 'def456',
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame(
            'https://cdn.discordapp.com/guilds/123456789/users/123456789012345678/avatars/abc123.webp',
            $array['avatar']
        );
    }

    #[Test]
    public function it_returns_user_avatar_url_when_no_guild_avatar(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'guild_avatar' => null,
            'avatar' => 'def456',
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame(
            'https://cdn.discordapp.com/avatars/123456789012345678/def456.webp',
            $array['avatar']
        );
    }

    #[Test]
    public function it_returns_default_avatar_url_when_no_avatar(): void
    {
        $user = User::factory()->create([
            'id' => '175928847299117063',
            'guild_avatar' => null,
            'avatar' => null,
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('https://cdn.discordapp.com/embed/avatars/2.png', $array['avatar']);
    }

    // ==================== banner url ====================

    #[Test]
    public function it_returns_banner_url_when_banner_is_set(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'banner' => 'banner123',
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame(
            'https://cdn.discordapp.com/banners/123456789012345678/banner123.webp',
            $array['banner']
        );
    }

    #[Test]
    public function it_returns_null_banner_when_not_set(): void
    {
        $user = User::factory()->create(['banner' => null]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['banner']);
    }

    // ==================== roles ====================

    #[Test]
    public function it_returns_roles(): void
    {
        $officer = DiscordRole::find('829021769448816691') ??
            DiscordRole::factory()->officer()->create();
        $member = DiscordRole::find('829022020301094922') ??
            DiscordRole::factory()->member()->create();

        $user = User::factory()->create();
        $user->discordRoles()->attach([$officer->id, $member->id]);

        $resource = new UserResource($user->fresh()->load('discordRoles'));
        $array = $resource->toArray(new Request);

        $this->assertEqualsCanonicalizing([$officer->id, $member->id], $array['roles']);
    }

    #[Test]
    public function it_returns_empty_roles_array_when_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame([], $array['roles']);
    }

    // ==================== highest role ====================

    #[Test]
    public function it_returns_highest_role_officer(): void
    {
        $user = User::factory()->officer()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('Officer', $array['highest_role']);
    }

    #[Test]
    public function it_returns_highest_role_raider(): void
    {
        $user = User::factory()->raider()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('Raider', $array['highest_role']);
    }

    #[Test]
    public function it_returns_highest_role_member(): void
    {
        $user = User::factory()->member()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('Member', $array['highest_role']);
    }

    #[Test]
    public function it_returns_highest_role_guest(): void
    {
        $user = User::factory()->guest()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertSame('Guest', $array['highest_role']);
    }

    #[Test]
    public function it_returns_null_highest_role_when_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['highest_role']);
    }

    #[Test]
    public function it_returns_officer_as_highest_when_multiple_roles(): void
    {
        $officer = DiscordRole::find('829021769448816691') ??
            DiscordRole::factory()->officer()->create();
        $member = DiscordRole::find('829022020301094922') ??
            DiscordRole::factory()->member()->create();
        $guest = DiscordRole::find('829022292590985226') ??
            DiscordRole::factory()->guest()->create();

        $user = User::factory()->withRoles([
            $member->id,
            $officer->id,
            $guest->id,
        ])->create();

        $resource = new UserResource($user->fresh()->load('discordRoles'));
        $array = $resource->toArray(new Request);

        $this->assertSame('Officer', $array['highest_role']);
    }

    // ==================== admin ====================

    #[Test]
    public function it_returns_admin_true_for_admin_users(): void
    {
        $user = User::factory()->admin()->create();

        $array = (new UserResource($user))->toArray(new Request);

        $this->assertTrue($array['admin']);
    }

    #[Test]
    public function it_returns_admin_false_for_non_admin_users(): void
    {
        $user = User::factory()->create();

        $array = (new UserResource($user))->toArray(new Request);

        $this->assertFalse($array['admin']);
    }

    // ==================== characters ====================

    #[Test]
    public function it_omits_characters_when_not_loaded(): void
    {
        $user = User::factory()->withCharacters()->create();

        $array = (new UserResource($user))->resolve(new Request);

        $this->assertArrayNotHasKey('characters', $array);
    }

    #[Test]
    public function it_returns_characters_when_loaded(): void
    {
        $characters = Character::factory()->count(2)->create();
        $user = User::factory()->withCharacters($characters->all())->create();

        $request = new Request;
        $array = (new UserResource($user->fresh()->load('characters')))->resolve($request);

        $this->assertEqualsCanonicalizing(
            $characters->pluck('id')->all(),
            $array['characters']->map(fn (CharacterResource $character) => $character->resolve($request)['id'])->all(),
        );
    }

    // ==================== events ====================

    #[Test]
    public function it_omits_events_when_not_loaded(): void
    {
        $user = User::factory()->create();

        $resource = new UserResource($user);
        $array = $resource->resolve(new Request);

        $this->assertArrayNotHasKey('events', $array);
    }

    #[Test]
    public function it_returns_events_when_loaded(): void
    {
        $this->fakeDiscordChannel();
        $events = Event::factory()->count(2)->create();
        $user = User::factory()->withEvents($events->all())->create();

        $request = new Request;
        $resource = new UserResource($user->fresh()->load('events'));
        $array = $resource->resolve($request);

        $this->assertArrayHasKey('events', $array);
        $this->assertEqualsCanonicalizing(
            $events->pluck('id')->all(),
            $array['events']->map(fn (EventResource $event) => $event->resolve($request)['id'])->all(),
        );
    }

    #[Test]
    public function it_returns_events_in_the_member_shape(): void
    {
        $this->fakeDiscordChannel();
        $user = User::factory()->withEvents()->create();

        $request = new Request;
        $event = (new UserResource($user->fresh()->load('events')))->resolve($request)['events']->first()->resolve($request);

        $this->assertArrayHasKey('channel', $event);
        $this->assertArrayNotHasKey('composition', $event);
        $this->assertArrayNotHasKey('raids', $event);
        $this->assertArrayNotHasKey('users', $event);
    }

    // ==================== planned absences ====================

    #[Test]
    public function it_omits_planned_absences_when_not_loaded(): void
    {
        $user = User::factory()->create();

        $resource = new UserResource($user);
        $array = $resource->resolve(new Request);

        $this->assertArrayNotHasKey('planned_absences', $array);
    }

    #[Test]
    public function it_returns_planned_absences_when_loaded(): void
    {
        $user = User::factory()->create();
        PlannedAbsence::factory()->count(2)->create(['user_id' => $user->id]);

        $resource = new UserResource($user->fresh()->load('plannedAbsences'));
        $array = $resource->resolve(new Request);

        $this->assertArrayHasKey('planned_absences', $array);
        $this->assertCount(2, $array['planned_absences']);
    }

    // ==================== planned absences created ====================

    #[Test]
    public function it_omits_planned_absences_created_when_not_loaded(): void
    {
        $user = User::factory()->create();

        $resource = new UserResource($user);
        $array = $resource->resolve(new Request);

        $this->assertArrayNotHasKey('planned_absences_created', $array);
    }

    #[Test]
    public function it_returns_planned_absences_created_when_loaded(): void
    {
        $user = User::factory()->create();
        PlannedAbsence::factory()->count(2)->create(['created_by' => $user->id]);

        $resource = new UserResource($user->fresh()->load('plannedAbsencesCreated'));
        $array = $resource->resolve(new Request);

        $this->assertArrayHasKey('planned_absences_created', $array);
        $this->assertCount(2, $array['planned_absences_created']);
    }
}
