<?php

namespace Tests\Feature\Raiding;

use App\Models\Boss;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\Permission;
use App\Models\Raid;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use App\Services\RaidHelper\RaidHelper;
use App\Services\RaidHelper\Resources\Event as RaidHelperEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    protected DiscordRole $memberRole;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Cache::tags(['events'])->flush();

        $this->memberRole = DiscordRole::create([
            'id' => '829022020301094922',
            'name' => 'Member',
            'position' => 1,
            'is_visible' => true,
        ]);
        $this->memberRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-raid-plans', 'guard_name' => 'web']));

        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getChannel')->andReturn(
                new Channel(
                    id: '123456789',
                    name: 'raids',
                    position: 1,
                ),
            )->byDefault();
        });

        $this->mock(RaidHelper::class, function (MockInterface $mock) {
            $mock->shouldReceive('getEvent')->andReturn(
                RaidHelperEvent::from($this->minimalRaidHelperEventPayload()),
            )->byDefault();
        });
    }

    #[Test]
    public function it_renders_event_show_page(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $event = Event::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Events/ShowEvent')
        );
    }

    #[Test]
    public function it_returns_event_resource(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $event = Event::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('event')
        );
    }

    #[Test]
    public function it_returns_raids_collection(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $raids = Raid::factory()->count(2)->create();
        $event = Event::factory()->create();
        $event->raids()->attach($raids->pluck('id'));

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('raids', 2)
        );
    }

    #[Test]
    public function it_loads_benched_characters_via_deferred_prop(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $inComp = Character::factory()->withRank()->create(['name' => 'Jaina']);
        $benched = Character::factory()->withRank()->create(['name' => 'Thrall']);
        $event = Event::factory()->create();

        $event->characters()->attach($inComp->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'is_confirmed' => true,
        ]);

        $this->mock(RaidHelper::class, function (MockInterface $mock) use ($inComp, $benched) {
            $mock->shouldReceive('getEvent')->andReturn(
                RaidHelperEvent::from($this->minimalRaidHelperEventPayload([
                    'signUps' => [
                        ['id' => 1, 'name' => $inComp->name, 'userId' => '123456789', 'entryTime' => 1700000000],
                        ['id' => 2, 'name' => $benched->name, 'userId' => '987654321', 'entryTime' => 1700000001],
                    ],
                ])),
            );
        });

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('benched')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('benched.data', 1)
            )
        );
    }

    #[Test]
    public function it_returns_empty_benched_when_event_has_no_characters_in_comp(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $event = Event::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('benched')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('benched', 0)
            )
        );
    }

    #[Test]
    public function it_excludes_characters_in_the_comp_from_benched(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $inComp = Character::factory()->withRank()->create(['name' => 'Jaina']);
        $benched = Character::factory()->withRank()->create(['name' => 'Thrall']);
        $event = Event::factory()->create();

        $event->characters()->attach($inComp->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'is_confirmed' => true,
        ]);

        $this->mock(RaidHelper::class, function (MockInterface $mock) use ($inComp, $benched) {
            $mock->shouldReceive('getEvent')->andReturn(
                RaidHelperEvent::from($this->minimalRaidHelperEventPayload([
                    'signUps' => [
                        ['id' => 1, 'name' => $inComp->name, 'userId' => '111111111', 'entryTime' => 1700000000],
                        ['id' => 2, 'name' => $benched->name, 'userId' => '222222222', 'entryTime' => 1700000001],
                    ],
                ])),
            );
        });

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('benched')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('benched.data', 1)
                ->where('benched.data.0.name', $benched->name)
            )
        );
    }

    #[Test]
    public function it_loads_bosses_via_deferred_prop(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $raid = Raid::factory()->create();
        $bosses = Boss::factory()->count(2)->for($raid)->create();

        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('bosses')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('bosses', 2)
            )
        );
    }

    #[Test]
    public function it_loads_groups_via_deferred_prop(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);
        $event = Event::factory()
            ->hasAttached(
                Raid::factory(),
                [],
                'raids'
            )
            ->create();

        $character = Character::factory()->create();
        $event->characters()->attach($character->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'is_confirmed' => true,
        ]);

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('groups')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('groups')
            )
        );
    }

    #[Test]
    public function it_returns_401_when_unauthenticated(): void
    {
        $event = Event::factory()->create();

        $response = $this->get(route('raiding.plans.show', $event));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function it_returns_403_when_user_cannot_view_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalRaidHelperEventPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => '999000000000000001',
            'serverId' => '111222333444555666',
            'leaderId' => '200000000000000001',
            'leaderName' => 'Raid Leader',
            'channelId' => '100000000000000001',
            'channelName' => 'raid-signups',
            'channelType' => 'GUILD_TEXT',
            'templateId' => 'wowclassic',
            'templateEmoteId' => '0',
            'title' => 'Weekly Raid',
            'description' => '',
            'startTime' => 1700000000,
            'endTime' => 1700007200,
            'closingTime' => 1699999800,
            'date' => '2023-11-14',
            'time' => '20:00',
            'advancedSettings' => [],
            'classes' => [],
            'roles' => [],
            'signUps' => [],
            'lastUpdated' => 1699999000,
            'color' => '0,0,0',
        ], $overrides);
    }
}
