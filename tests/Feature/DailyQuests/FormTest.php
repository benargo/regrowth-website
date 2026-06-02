<?php

namespace Tests\Feature\DailyQuests;

use App\Models\DailyQuest;
use App\Models\User;
use App\Notifications\DailyQuestsMessage;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel as ChannelResource;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

class FormTest extends DashboardTestCase
{
    #[Test]
    public function form_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.daily-quests.form'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function form_requires_dashboard_access(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard.daily-quests.form'));

        $response->assertForbidden();
    }

    #[Test]
    public function form_displays_quests_grouped_by_type(): void
    {

        $cookingQuest = DailyQuest::factory()->cooking()->create();
        $fishingQuest = DailyQuest::factory()->fishing()->create();
        $dungeonQuest = DailyQuest::factory()->dungeon()->create();
        $heroicQuest = DailyQuest::factory()->heroic()->create();
        $pvpQuest = DailyQuest::factory()->pvp()->create();

        $response = $this->actingAs($this->officer)->get(route('dashboard.daily-quests.form'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/DailyQuests/Form')
            ->has('cookingQuests', 1)
            ->has('fishingQuests', 1)
            ->has('dungeonQuests', 1)
            ->has('heroicQuests', 1)
            ->has('pvpQuests', 1)
        );
    }

    #[Test]
    public function form_returns_signed_icon_urls(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.daily-quests.form'));

        $response->assertSuccessful();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/DailyQuests/Form')
            ->where('icons.cooking', function (mixed $url) {
                $urlString = (string) $url;

                return str_contains($urlString, '/icons/56/')
                    && URL::hasValidSignature(request()->create($urlString));
            })
            ->where('icons.fishing', function (mixed $url) {
                return str_contains((string) $url, '/icons/56/');
            })
            ->where('icons.dungeon', function (mixed $url) {
                return str_contains((string) $url, '/icons/56/');
            })
            ->where('icons.heroic', function (mixed $url) {
                return str_contains((string) $url, '/icons/56/');
            })
            ->where('icons.pvp', function (mixed $url) {
                return str_contains((string) $url, '/icons/56/');
            })
        );
    }

    #[Test]
    public function store_creates_daily_quest_notification(): void
    {
        Queue::fake();

        $this->mock(Discord::class)
            ->shouldReceive('getChannel')
            ->once()
            ->andReturn(ChannelResource::from(['id' => '123456789']));

        $cookingQuest = DailyQuest::factory()->cooking()->create();
        $fishingQuest = DailyQuest::factory()->fishing()->create();
        $dungeonQuest = DailyQuest::factory()->dungeon()->create();
        $heroicQuest = DailyQuest::factory()->heroic()->create();
        $pvpQuest = DailyQuest::factory()->pvp()->create();

        $response = $this->actingAs($this->officer)->post(route('dashboard.daily-quests.store'), [
            'cooking_quest_id' => $cookingQuest->id,
            'fishing_quest_id' => $fishingQuest->id,
            'dungeon_quest_id' => $dungeonQuest->id,
            'heroic_quest_id' => $heroicQuest->id,
            'pvp_quest_id' => $pvpQuest->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Daily quests set and posted to Discord!');

        Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof DailyQuestsMessage);
    }

    #[Test]
    public function store_validates_quest_types(): void
    {
        $cookingQuest = DailyQuest::factory()->cooking()->create();

        // Try to use a cooking quest for fishing (wrong type)
        $response = $this->actingAs($this->officer)->post(route('dashboard.daily-quests.store'), [
            'cooking_quest_id' => $cookingQuest->id,
            'fishing_quest_id' => $cookingQuest->id, // Wrong type!
            'dungeon_quest_id' => DailyQuest::factory()->dungeon()->create()->id,
            'heroic_quest_id' => DailyQuest::factory()->heroic()->create()->id,
            'pvp_quest_id' => DailyQuest::factory()->pvp()->create()->id,
        ]);

        $response->assertSessionHasErrors(['fishing_quest_id']);
    }

    #[Test]
    public function store_requires_authentication(): void
    {
        $response = $this->post(route('dashboard.daily-quests.store'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function store_requires_dashboard_access(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('dashboard.daily-quests.store'), [
            'cooking_quest_id' => 1,
            'fishing_quest_id' => 2,
            'dungeon_quest_id' => 3,
            'heroic_quest_id' => 4,
            'pvp_quest_id' => 5,
        ]);

        $response->assertForbidden();
    }
}
