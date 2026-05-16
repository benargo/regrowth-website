<?php

namespace Tests\Feature\Raiding;

use App\Models\Boss;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\Permission;
use App\Models\Raid;
use App\Models\TargetMarker;
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

class ShowEventTest extends TestCase
{
    use RefreshDatabase;

    protected DiscordRole $memberRole;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Cache::tags(['raiding', 'events'])->flush();

        $this->memberRole = DiscordRole::create([
            'id' => '829022020301094922',
            'name' => 'Member',
            'position' => 1,
            'is_visible' => true,
        ]);
        $this->memberRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-raid-plans', 'guard_name' => 'web']));

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
    public function it_returns_a_single_event_prop(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $event = Event::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('event')
            ->missing('raids')
            ->missing('benched')
            ->missing('bosses')
            ->missing('groups')
        );
    }

    #[Test]
    public function it_returns_event_resource_with_expected_keys(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $event = Event::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('event.id')
            ->has('event.title')
            ->has('event.start_time')
            ->has('event.end_time')
            ->has('event.duration')
            ->has('event.assignments')
            ->has('event.composition')
            ->has('event.raids')
        );
    }

    #[Test]
    public function it_returns_raids_nested_inside_event(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $raid = Raid::factory()->create();
        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('event.raids', 1)
            ->where('event.raids.0.name', $raid->name)
            ->where('event.raids.0.slug', $raid->slug)
            ->where('event.raids.0.max_players', $raid->max_players)
            ->has('event.raids.0.bosses')
        );
    }

    #[Test]
    public function it_returns_bosses_nested_inside_raids(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $raid = Raid::factory()->create();
        $bosses = Boss::factory()->count(2)->for($raid)->create();
        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('event.raids.0.bosses', 2)
        );
    }

    #[Test]
    public function it_returns_composition_groups_nested_inside_event(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $raid = Raid::factory()->tenPlayer()->create();
        $event = Event::factory()->hasAttached($raid, [], 'raids')->create();
        $character = Character::factory()->withRank()->create();
        $event->characters()->attach($character->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'is_confirmed' => true,
        ]);

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('event.composition.groups', 1)
            ->where('event.composition.groups.0.group_number', 1)
            ->has('event.composition.groups.0.characters', 1)
        );
    }

    #[Test]
    public function it_returns_benched_characters_nested_inside_composition(): void
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
            ->has('event.composition.bench', 1)
            ->where('event.composition.bench.0.name', $benched->name)
        );
    }

    #[Test]
    public function it_returns_empty_bench_when_event_has_no_characters_in_comp(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $event = Event::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('event.composition.bench', 0)
        );
    }

    #[Test]
    public function it_excludes_characters_in_the_comp_from_bench(): void
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
            ->has('event.composition.bench', 1)
            ->where('event.composition.bench.0.name', $benched->name)
        );
    }

    #[Test]
    public function it_returns_event_level_assignments_nested_inside_event(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $event = Event::factory()->create();
        EventAssignment::factory()->for($event)->create(['boss_id' => null]);

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('event.assignments.groups')
            ->has('event.assignments.ungrouped', 1)
        );
    }

    #[Test]
    public function it_returns_boss_level_assignments_nested_inside_boss(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $raid = Raid::factory()->create();
        $boss = Boss::factory()->for($raid)->create();
        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);
        EventAssignment::factory()->for($event)->create(['boss_id' => $boss->id]);

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('event.raids.0.bosses.0.assignments.groups')
            ->has('event.raids.0.bosses.0.assignments.ungrouped', 1)
        );
    }

    #[Test]
    public function it_does_not_include_boss_assignments_in_event_level_assignments(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $raid = Raid::factory()->create();
        $boss = Boss::factory()->for($raid)->create();
        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);
        EventAssignment::factory()->for($event)->create(['boss_id' => $boss->id]);

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('event.assignments.ungrouped', 0)
        );
    }

    #[Test]
    public function it_caches_the_event_resource(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $event = Event::factory()->create();

        $this->mock(RaidHelper::class, function (MockInterface $mock) {
            $mock->shouldReceive('getEvent')->once()->andReturn(
                RaidHelperEvent::from($this->minimalRaidHelperEventPayload([
                    'signUps' => [],
                ])),
            );
        });

        $event->characters()->attach(
            Character::factory()->withRank()->create()->id,
            ['slot_number' => 1, 'group_number' => 1, 'is_confirmed' => true]
        );

        $this->actingAs($user)->get(route('raiding.plans.show', $event));
        $this->actingAs($user)->get(route('raiding.plans.show', $event));
    }

    #[Test]
    public function it_allows_guest_to_view_a_recent_event(): void
    {
        $event = Event::factory()->create(['end_time' => now()->subHour()]);

        $response = $this->get(route('raiding.plans.show', $event));

        $response->assertOk();
    }

    #[Test]
    public function it_denies_guest_access_to_an_old_event(): void
    {
        $event = Event::factory()->create(['end_time' => now()->subHours(3)]);

        $response = $this->get(route('raiding.plans.show', $event));

        $response->assertForbidden();
    }

    #[Test]
    public function it_denies_authenticated_user_without_permission_access_to_old_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['end_time' => now()->subHours(3)]);

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertForbidden();
    }

    #[Test]
    public function it_allows_user_with_view_old_raid_plans_permission_to_view_old_event(): void
    {
        Permission::firstOrCreate(['name' => 'view-old-raid-plans', 'guard_name' => 'web']);

        $role = DiscordRole::factory()->create();
        $role->givePermissionTo('view-old-raid-plans');

        $user = User::factory()->create();
        $user->discordRoles()->attach($role->id);

        $event = Event::factory()->create(['end_time' => now()->subHours(3)]);

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertOk();
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
