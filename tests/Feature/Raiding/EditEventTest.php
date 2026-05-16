<?php

namespace Tests\Feature\Raiding;

use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\Permission;
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

class EditEventTest extends TestCase
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
    public function it_renders_event_edit_page(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $event = Event::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.plans.edit', $event));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Events/EditEvent')
        );
    }

    #[Test]
    public function it_passes_target_markers_to_edit_page(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->memberRole->id);

        $event = Event::factory()->create();
        TargetMarker::create(['slug' => 'star', 'name' => 'Star']);

        $response = $this->actingAs($user)->get(route('raiding.plans.edit', $event));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Events/EditEvent')
            ->has('event')
            ->has('targetMarkers', 1)
            ->where('targetMarkers.0.slug', 'star')
            ->where('targetMarkers.0.name', 'Star')
        );
    }

    #[Test]
    public function it_returns_403_on_edit_when_user_cannot_update_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.plans.edit', $event));

        $response->assertForbidden();
    }

    #[Test]
    public function it_returns_401_on_edit_when_unauthenticated(): void
    {
        $event = Event::factory()->create();

        $response = $this->get(route('raiding.plans.edit', $event));

        $response->assertRedirect(route('login'));
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
