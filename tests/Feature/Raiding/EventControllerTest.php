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
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $event = Event::factory()
            ->hasAttached(
                Raid::factory(),
                [],
                'raids'
            )
            ->create();

        $character = Character::factory()->create();
        $event->characters()->attach($character->id, [
            'slot_number' => 10,
            'group_number' => 3,
            'is_confirmed' => true,
        ]);

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('benched')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('benched')
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
}
